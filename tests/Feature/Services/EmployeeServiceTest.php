<?php

namespace Tests\Feature\Services;

use App\Enums\BorrowStatus;
use App\Enums\EmployeeStatus;
use App\Enums\ExitType;
use App\Jobs\GenerateSalarySlips;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\EmployeeLeave;
use App\Models\EmployeeSalaryRevision;
use App\Models\LeaveType;
use App\Models\Overtime;
use App\Models\PayrollItem;
use App\Models\WorkShift;
use App\Services\BorrowCalculationService;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\SalaryRevisionService;
use App\Services\WorkingHoursCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class EmployeeServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_creating_an_employee_sets_up_the_shift_salary_and_existing_borrow(): void
    {
        $this->prepareCompany();
        $shift = WorkShift::factory()->timing('09:00', '17:00', 420)->create(['name' => 'Short Shift']);

        $employee = $this->service()->create([
            'employee_code' => 'EMP-0001',
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'joining_date' => '2026-09-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            'work_shift_id' => $shift->id,
            'salary_components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => 30000],
                ['name' => 'HRA', 'type' => 'earning', 'amount' => 5000],
            ],
            'existing_borrow' => ['amount' => 20000, 'monthly_deduction' => 5000],
        ]);

        $date = CarbonImmutable::parse('2026-09-10');
        $this->assertSame('Short Shift', app(WorkingHoursCalculationService::class)->shiftFor($employee, $date)->name);

        $revision = app(SalaryRevisionService::class)->revisionOn($employee, $date);
        $this->assertSame(35000.0, $revision->new_gross);
        $this->assertSame('2026-09-01', $revision->effective_date->toDateString());

        $borrow = EmployeeBorrow::query()->sole();
        $this->assertSame(EmployeeBorrow::KIND_EXISTING, $borrow->kind);
        $this->assertSame(BorrowStatus::Active, $borrow->status);
        $this->assertSame(20000.0, $borrow->outstanding_amount);
        $this->assertSame('2026-09-01', $borrow->borrow_date->toDateString());

        $this->assertTrue(AuditLog::query()->where('action', 'employee.created')->where('employee_id', $employee->id)->exists());
    }

    public function test_exit_turns_an_active_employee_into_a_past_employee_and_keeps_all_history(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed', 'amount' => 1500, 'status' => 'approved',
        ]);
        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01',
        ]);
        app(LeaveService::class)->create($employee, [
            'leave_type_id' => LeaveType::query()->where('code', 'CL')->sole()->id,
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-08',
            'status' => 'approved',
        ]);
        $payrolls = app(PayrollService::class);
        $payrolls->finalize($payrolls->calculate($payrolls->create(2026, 9)));
        $before = $this->historyCounts($employee);

        $this->service()->exit($employee, [
            'exit_date' => '2026-10-01',
            'last_working_date' => '2026-09-30',
            'exit_type' => 'resignation',
            'exit_reason' => 'Better opportunity',
        ]);

        $employee->refresh();
        $this->assertSame(EmployeeStatus::Past, $employee->status);
        $this->assertSame('2026-09-30', $employee->last_working_date->toDateString());
        $this->assertSame(ExitType::Resignation, $employee->exit_type);
        $this->assertSame('Better opportunity', $employee->exit_reason);
        $this->assertSame(
            ['attendance' => 2, 'salary_revisions' => 1, 'payroll_items' => 1, 'borrows' => 1, 'overtime' => 1, 'leaves' => 1],
            $before,
        );
        $this->assertSame($before, $this->historyCounts($employee));
        $this->assertTrue(AuditLog::query()->where('action', 'employee.exited')->where('employee_id', $employee->id)->exists());
    }

    public function test_past_employee_is_left_out_of_a_newly_calculated_payroll(): void
    {
        $this->prepareCompany();
        $staying = $this->createEmployee([], 26000);
        $leaving = $this->createEmployee([], 30000);
        $this->service()->exit($leaving, [
            'exit_date' => '2026-09-15',
            'last_working_date' => '2026-09-15',
            'exit_type' => 'termination',
        ]);
        $payrolls = app(PayrollService::class);

        $payroll = $payrolls->calculate($payrolls->create(2026, 9));

        $this->assertSame(1, $payroll->employee_count);
        $this->assertSame($staying->id, PayrollItem::query()->sole()->employee_id);
    }

    public function test_reinstating_a_past_employee_makes_them_active_and_clears_the_exit_details(): void
    {
        $this->prepareCompany();
        $employee = Employee::factory()->past('2026-08-31')->create();

        $this->service()->reinstate($employee);

        $employee->refresh();
        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertNull($employee->exit_date);
        $this->assertNull($employee->last_working_date);
        $this->assertNull($employee->exit_type);
        $this->assertTrue(AuditLog::query()->where('action', 'employee.reinstated')->where('employee_id', $employee->id)->exists());
    }

    public function test_changing_the_employee_shift_closes_the_previous_assignment(): void
    {
        $this->prepareCompany();
        $first = WorkShift::factory()->create(['name' => 'First Shift']);
        $second = WorkShift::factory()->create(['name' => 'Second Shift']);
        $employee = $this->createEmployee();
        $this->service()->assignShift($employee, $first->id, CarbonImmutable::parse('2026-09-01'));

        $this->service()->assignShift($employee, $second->id, CarbonImmutable::parse('2026-09-15'));

        $hours = app(WorkingHoursCalculationService::class);
        $this->assertSame('First Shift', $hours->shiftFor($employee, CarbonImmutable::parse('2026-09-14'))->name);
        $this->assertSame('Second Shift', $hours->shiftFor($employee, CarbonImmutable::parse('2026-09-15'))->name);
    }

    public function test_removing_the_employee_shift_falls_back_to_the_company_default(): void
    {
        $this->prepareCompany();
        $own = WorkShift::factory()->create(['name' => 'Own Shift']);
        $employee = $this->createEmployee();
        $this->service()->assignShift($employee, $own->id, CarbonImmutable::parse('2026-09-01'));

        $this->service()->assignShift($employee, null, CarbonImmutable::parse('2026-09-15'));

        $hours = app(WorkingHoursCalculationService::class);
        $this->assertSame('Own Shift', $hours->shiftFor($employee, CarbonImmutable::parse('2026-09-14'))->name);
        $this->assertSame('General Shift', $hours->shiftFor($employee, CarbonImmutable::parse('2026-09-15'))->name);
    }

    public function test_next_employee_code_follows_the_highest_existing_number(): void
    {
        $this->prepareCompany();
        $first = $this->service()->nextCode();
        Employee::factory()->create(['employee_code' => 'EMP-0007']);
        Employee::factory()->create(['employee_code' => 'A-12']);

        $next = $this->service()->nextCode();

        $this->assertSame('EMP-0001', $first);
        $this->assertSame('EMP-0013', $next);
    }

    private function prepareCompany(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
    }

    /**
     * @return array<string, int>
     */
    private function historyCounts(Employee $employee): array
    {
        return [
            'attendance' => Attendance::query()->where('employee_id', $employee->id)->count(),
            'salary_revisions' => EmployeeSalaryRevision::query()->where('employee_id', $employee->id)->count(),
            'payroll_items' => PayrollItem::query()->where('employee_id', $employee->id)->count(),
            'borrows' => EmployeeBorrow::query()->where('employee_id', $employee->id)->count(),
            'overtime' => Overtime::query()->where('employee_id', $employee->id)->count(),
            'leaves' => EmployeeLeave::query()->where('employee_id', $employee->id)->count(),
        ];
    }

    private function service(): EmployeeService
    {
        return app(EmployeeService::class);
    }
}
