<?php

namespace Tests\Feature\Jobs;

use App\Enums\PayrollStatus;
use App\Jobs\CalculatePayroll;
use App\Jobs\GenerateAttendance;
use App\Jobs\GenerateSalarySlips;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalarySlip;
use App\Services\AttendanceCalculationService;
use App\Services\PayrollService;
use App\Services\SalarySlipService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class BackgroundJobsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_payroll_job_calculates_for_its_own_company_without_a_request(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        $payroll = app(PayrollService::class)->create(2026, 9);
        $other = $this->createCompany();
        $this->useCompany($other);
        $this->configure(['attendance_mode' => 'automatic']);
        $this->createEmployee([], 99000);
        $this->tenant()->forget();

        (new CalculatePayroll($company->id, $payroll->id))->handle(app(PayrollService::class));

        $this->assertNull($this->tenant()->company());
        $this->useCompany($company);
        $payroll->refresh();
        $this->assertSame(PayrollStatus::Calculated, $payroll->status);
        $this->assertSame(1, $payroll->employee_count);
        $this->assertSame(26000.0, $payroll->total_net_payable);
        $this->assertSame(0, PayrollItem::withoutTenancy()->where('company_id', $other->id)->count());
    }

    public function test_large_payroll_is_queued_instead_of_calculated_in_the_request(): void
    {
        Queue::fake([CalculatePayroll::class]);
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        $payroll = app(PayrollService::class)->create(2026, 9);
        config(['hrms.sync_payroll_employee_limit' => 0]);

        $response = $this->actingAs($this->adminOf($company))->post("/payroll/{$payroll->id}/calculate");

        $response->assertSessionHasNoErrors();
        Queue::assertPushed(CalculatePayroll::class, fn (CalculatePayroll $job): bool => $job->companyId === $company->id && $job->payrollId === $payroll->id);
        $this->assertSame(PayrollStatus::Draft, $payroll->refresh()->status);
    }

    public function test_salary_slip_job_stores_a_pdf_for_every_slip_of_the_payroll(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        Storage::fake('local');
        $company = $this->prepareCompany();
        $this->createEmployee(['employee_code' => 'EMP-0001'], 26000);
        $this->createEmployee(['employee_code' => 'EMP-0002'], 30000);
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->finalize($payrolls->calculate($payrolls->create(2026, 9)));
        $this->tenant()->forget();

        (new GenerateSalarySlips($company->id, $payroll->id))->handle(app(SalarySlipService::class));

        $this->useCompany($company);
        $slips = SalarySlip::query()->orderBy('slip_number')->get();
        $this->assertSame(['SLIP-202609-EMP-0001', 'SLIP-202609-EMP-0002'], $slips->pluck('slip_number')->all());

        foreach ($slips as $slip) {
            $this->assertNotNull($slip->generated_at);
            Storage::disk('local')->assertExists($slip->file_path);
            $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($slip->file_path));
        }
    }

    public function test_finalizing_generates_the_slips_and_reopening_removes_them(): void
    {
        Storage::fake('local');
        $company = $this->prepareCompany();
        $this->createEmployee(['employee_code' => 'EMP-0001'], 26000);
        $payrolls = app(PayrollService::class);

        // The test queue runs jobs immediately, as a worker would shortly after.
        $payroll = $payrolls->finalize($payrolls->calculate($payrolls->create(2026, 9)));

        $path = "salary-slips/{$company->id}/{$payroll->id}/SLIP-202609-EMP-0001.pdf";
        $this->assertSame($path, SalarySlip::query()->sole()->file_path);
        Storage::disk('local')->assertExists($path);

        $payrolls->reopen($payroll, 'Attendance correction');

        $this->assertSame(0, SalarySlip::query()->count());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_attendance_job_generates_the_range_for_its_company(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee();
        $this->tenant()->forget();

        (new GenerateAttendance($company->id, '2026-09-26', '2026-09-28'))->handle(app(AttendanceCalculationService::class));

        $this->useCompany($company);
        $this->assertSame(
            ['present', 'weekly_off', 'present'],
            Attendance::query()->orderBy('date')->get()->map(fn (Attendance $a): string => $a->status->value)->all(),
        );
    }

    public function test_long_attendance_range_is_queued(): void
    {
        Queue::fake([GenerateAttendance::class]);
        $company = $this->prepareCompany();
        $this->createEmployee();
        config(['hrms.sync_attendance_day_limit' => 2]);

        $response = $this->actingAs($this->adminOf($company))->post('/attendance/generate', [
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
        ]);

        $response->assertSessionHasNoErrors();
        Queue::assertPushed(GenerateAttendance::class, fn (GenerateAttendance $job): bool => $job->companyId === $company->id
            && $job->startDate === '2026-09-01'
            && $job->endDate === '2026-09-30');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_daily_command_generates_attendance_for_active_companies_only(): void
    {
        // 4 October 2026 is a Sunday; 20:00 UTC on the 3rd is already the 4th in India.
        $this->travelTo('2026-10-03 20:00:00');
        $active = $this->useCompany($this->createCompany(['name' => 'Active Co']));
        $this->createEmployee();
        $inactive = $this->useCompany($this->createCompany(['name' => 'Inactive Co']));
        $this->createEmployee();
        $inactive->forceFill(['is_active' => false])->save();
        $this->tenant()->forget();

        $this->artisan('hrms:generate-attendance')->assertSuccessful();

        $records = Attendance::withoutTenancy()->get();
        $this->assertSame(1, $records->count());
        $this->assertSame($active->id, $records[0]->company_id);
        $this->assertSame('2026-10-04', $records[0]->date->toDateString());
        $this->assertSame('weekly_off', $records[0]->status->value);
        $this->assertNull($this->tenant()->company());
    }

    public function test_company_today_follows_the_company_timezone(): void
    {
        $this->travelTo('2026-10-03 20:00:00');
        $india = $this->createCompany(['timezone' => 'Asia/Kolkata']);
        $newYork = $this->createCompany(['timezone' => 'America/New_York']);

        $this->useCompany($india);
        $indiaToday = $this->tenant()->today()->toDateString();
        $this->useCompany($newYork);
        $newYorkToday = $this->tenant()->today()->toDateString();

        $this->assertSame('2026-10-04', $indiaToday);
        $this->assertSame('2026-10-03', $newYorkToday);
    }

    public function test_payroll_of_one_company_cannot_be_calculated_by_a_job_for_another_company(): void
    {
        $this->prepareCompany();
        $this->createEmployee([], 26000);
        $payroll = app(PayrollService::class)->create(2026, 9);
        $other = $this->createCompany();
        $this->tenant()->forget();

        try {
            (new CalculatePayroll($other->id, $payroll->id))->handle(app(PayrollService::class));
            $this->fail('A job reached the payroll of another company.');
        } catch (ModelNotFoundException) {
            // The payroll does not exist as far as the other company is concerned.
        }

        $this->assertSame(PayrollStatus::Draft, Payroll::withoutTenancy()->find($payroll->id)->status);
        $this->assertSame(0, PayrollItem::withoutTenancy()->count());
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);

        return $company;
    }
}
