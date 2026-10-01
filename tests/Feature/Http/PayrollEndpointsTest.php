<?php

namespace Tests\Feature\Http;

use App\Enums\PayrollStatus;
use App\Jobs\GenerateSalarySlips;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\Role;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class PayrollEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    private const string LOCKED = 'The payroll for September 2026 is finalized and locked. Reopen it to make changes.';

    public function test_starting_a_payroll_creates_a_draft_for_the_month(): void
    {
        $company = $this->prepareCompany();

        $response = $this->actingAs($this->adminOf($company))->post('/payroll', ['month' => '2026-09']);

        $payroll = Payroll::query()->sole();
        $response->assertRedirect(route('payroll.show', $payroll));
        $this->assertSame(PayrollStatus::Draft, $payroll->status);
        $this->assertSame('2026-09-01', $payroll->period_start->toDateString());
        $this->assertSame('2026-09-30', $payroll->period_end->toDateString());
    }

    public function test_a_second_payroll_for_the_same_month_is_rejected(): void
    {
        $company = $this->prepareCompany();
        app(PayrollService::class)->create(2026, 9);

        $response = $this->actingAs($this->adminOf($company))->post('/payroll', ['month' => '2026-09']);

        $response->assertSessionHasErrors(['month' => 'A payroll already exists for this month.']);
        $this->assertSame(1, Payroll::query()->count());
    }

    public function test_payroll_moves_from_draft_through_review_and_adjustment_to_finalized(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        $admin = $this->adminOf($company);
        $this->actingAs($admin);
        $payroll = app(PayrollService::class)->create(2026, 9);

        $this->post("/payroll/{$payroll->id}/calculate")->assertSessionHasNoErrors();
        $this->assertSame(PayrollStatus::Calculated, $payroll->refresh()->status);
        $item = PayrollItem::query()->sole();

        $this->post("/payroll/{$payroll->id}/review")->assertSessionHasNoErrors();
        $this->assertSame(PayrollStatus::UnderReview, $payroll->refresh()->status);

        $this->post("/payroll/{$payroll->id}/items/{$item->id}/adjustments", [
            'bucket' => 'bonus_amount', 'amount' => 1500, 'reason' => 'Performance bonus',
        ])->assertSessionHasNoErrors();
        $this->assertSame(PayrollStatus::Adjusted, $payroll->refresh()->status);
        $adjustment = PayrollAdjustment::query()->sole();
        $this->assertSame($admin->id, $adjustment->user_id);
        $this->assertSame('Performance bonus', $adjustment->reason);
        $this->assertSame(27500.0, $item->refresh()->net_payable);

        $this->post("/payroll/{$payroll->id}/finalize")->assertSessionHasNoErrors();
        $payroll->refresh();
        $this->assertSame(PayrollStatus::Finalized, $payroll->status);
        $this->assertSame($admin->id, $payroll->finalized_by);
        $this->assertSame(27500.0, $payroll->total_net_payable);
        Queue::assertPushed(GenerateSalarySlips::class);
    }

    public function test_adjustment_requires_an_amount_and_a_reason(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        [$payroll, $item] = $this->calculatedPayroll();

        $response = $this->actingAs($this->adminOf($company))
            ->post("/payroll/{$payroll->id}/items/{$item->id}/adjustments", ['bucket' => 'bonus_amount', 'amount' => 0]);

        $response->assertSessionHasErrors(['amount', 'reason' => 'The reason field is required.']);
        $this->assertSame(0, PayrollAdjustment::query()->count());
        $this->assertSame(26000.0, $item->refresh()->net_payable);
    }

    public function test_new_borrow_cannot_be_changed_through_a_payroll_adjustment(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        [$payroll, $item] = $this->calculatedPayroll();

        $response = $this->actingAs($this->adminOf($company))
            ->post("/payroll/{$payroll->id}/items/{$item->id}/adjustments", [
                'bucket' => 'borrow_given', 'amount' => 5000, 'reason' => 'Extra advance',
            ]);

        $response->assertSessionHasErrors('bucket');
        $this->assertSame(0, PayrollAdjustment::query()->count());
    }

    public function test_removing_an_adjustment_restores_the_system_amount(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        [$payroll, $item] = $this->calculatedPayroll();
        $this->actingAs($this->adminOf($company));
        $this->post("/payroll/{$payroll->id}/items/{$item->id}/adjustments", [
            'bucket' => 'other_deductions', 'amount' => 400, 'reason' => 'Damaged equipment',
        ]);
        $adjustment = PayrollAdjustment::query()->sole();

        $response = $this->delete("/payroll/{$payroll->id}/items/{$item->id}/adjustments/{$adjustment->id}");

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, PayrollAdjustment::query()->count());
        $item->refresh();
        $this->assertSame(26000.0, $item->net_payable);
        $this->assertFalse($item->is_adjusted);
        $this->assertTrue(AuditLog::query()->where('action', 'payroll.adjustment_removed')->exists());
    }

    public function test_finalized_payroll_refuses_recalculation_adjustment_and_deletion(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $company = $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        [$payroll, $item] = $this->calculatedPayroll();
        app(PayrollService::class)->finalize($payroll);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        $this->actingAs($this->adminOf($company));

        $this->post("/payroll/{$payroll->id}/calculate")->assertSessionHasErrors(['payroll' => self::LOCKED]);
        $this->post("/payroll/{$payroll->id}/review")->assertSessionHasErrors(['payroll' => self::LOCKED]);
        $this->post("/payroll/{$payroll->id}/finalize")->assertSessionHasErrors(['payroll' => self::LOCKED]);
        $this->post("/payroll/{$payroll->id}/items/{$item->id}/adjustments", [
            'bucket' => 'bonus_amount', 'amount' => 500, 'reason' => 'Late bonus',
        ])->assertSessionHasErrors(['payroll' => self::LOCKED]);
        $this->delete("/payroll/{$payroll->id}")->assertSessionHasErrors(['payroll' => self::LOCKED]);

        $payroll->refresh();
        $this->assertSame(PayrollStatus::Finalized, $payroll->status);
        $this->assertSame(26000.0, $payroll->total_net_payable);
        $this->assertSame(26000.0, $item->refresh()->net_payable);
        $this->assertSame(0, PayrollAdjustment::query()->count());
    }

    public function test_reopening_requires_a_reason(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        [$payroll] = $this->calculatedPayroll();
        app(PayrollService::class)->finalize($payroll);

        $response = $this->actingAs($this->adminOf($company))->post("/payroll/{$payroll->id}/reopen", []);

        $response->assertSessionHasErrors(['reason' => 'The reason field is required.']);
        $this->assertSame(PayrollStatus::Finalized, $payroll->refresh()->status);
        $this->assertFalse(AuditLog::query()->where('action', 'payroll.reopened')->exists());
    }

    public function test_reopening_unlocks_the_payroll_and_writes_the_reason_to_the_audit_log(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        [$payroll] = $this->calculatedPayroll();
        app(PayrollService::class)->finalize($payroll);
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post("/payroll/{$payroll->id}/reopen", ['reason' => 'Attendance correction']);

        $response->assertSessionHasNoErrors();
        $payroll->refresh();
        $this->assertSame(PayrollStatus::UnderReview, $payroll->status);
        $this->assertSame($admin->id, $payroll->reopened_by);
        $audit = AuditLog::query()->where('action', 'payroll.reopened')->sole();
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('finalized', $audit->old_values['status']);
        $this->assertSame('Attendance correction', $audit->new_values['reason']);

        $this->post("/payroll/{$payroll->id}/calculate")->assertSessionHasNoErrors();
    }

    public function test_draft_payroll_can_be_deleted(): void
    {
        $company = $this->prepareCompany();
        $payroll = app(PayrollService::class)->create(2026, 9);

        $response = $this->actingAs($this->adminOf($company))->delete("/payroll/{$payroll->id}");

        $response->assertRedirect(route('payroll.index'));
        $this->assertModelMissing($payroll);
    }

    public function test_user_without_the_finalize_permission_cannot_finalize_or_reopen(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        [$payroll] = $this->calculatedPayroll();
        $clerk = $this->userWithPermissions($company, ['payroll.view', 'payroll.manage']);
        $this->actingAs($clerk);

        $this->post("/payroll/{$payroll->id}/calculate")->assertSessionHasNoErrors();
        $this->post("/payroll/{$payroll->id}/finalize")->assertForbidden();
        $this->post("/payroll/{$payroll->id}/reopen", ['reason' => 'Trying to reopen'])->assertForbidden();

        $this->assertSame(PayrollStatus::Calculated, $payroll->refresh()->status);
    }

    public function test_viewer_role_can_read_payroll_but_not_run_it(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        [$payroll] = $this->calculatedPayroll();
        $this->actingAs($this->userWithRole($company, 'viewer'));

        $this->get("/payroll/{$payroll->id}")->assertOk();
        $this->post('/payroll', ['month' => '2026-08'])->assertForbidden();
        $this->post("/payroll/{$payroll->id}/calculate")->assertForbidden();

        $this->assertSame(1, Payroll::query()->count());
    }

    public function test_payroll_preview_lists_each_employee_with_the_breakdown_columns(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['first_name' => 'Rahul', 'last_name' => 'Sharma'], 26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        [$payroll] = $this->calculatedPayroll();

        $response = $this->actingAs($this->adminOf($company))->get("/payroll/{$payroll->id}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('payroll.label', 'September 2026')
            ->where('payroll.status', 'calculated')
            ->where('payroll.is_locked', false)
            ->where('payroll.employee_count', 1)
            ->has('items.data', 1)
            ->where('items.data.0.employee_name', 'Rahul Sharma')
            ->where('items.data.0.gross_salary', 26000)
            ->where('items.data.0.absent_days', 1)
            ->where('items.data.0.attendance_deduction', 1000)
            ->where('items.data.0.net_payable', 25000)
            ->where('items.data.0.is_adjusted', false)
            ->has('workflow', 5));
    }

    public function test_item_breakdown_explains_every_amount(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        [$payroll, $item] = $this->calculatedPayroll();

        $response = $this->actingAs($this->adminOf($company))->get("/payroll/{$payroll->id}/items/{$item->id}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('salary.per_day_rate', 1000)
            ->where('attendance.absent', 1)
            ->has('lines', 2)
            ->where('lines.0.code', 'salary.basic')
            ->where('lines.0.amount', 26000)
            ->where('lines.1.code', 'attendance.absent')
            ->where('lines.1.note', '1 absent day(s) x 1,000.00 per day')
            ->where('buckets.attendance_deduction.system', 1000)
            ->where('buckets.attendance_deduction.adjustment', 0)
            ->where('buckets.attendance_deduction.final', 1000)
            ->where('totals.net_payable', 25000)
            ->has('adjustableBuckets', 9));
    }

    public function test_salary_slip_of_a_finalized_payroll_downloads_as_a_pdf(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        Storage::fake('local');
        $company = $this->prepareCompany();
        $this->createEmployee(['employee_code' => 'EMP-0001'], 26000);
        [$payroll] = $this->calculatedPayroll();
        app(PayrollService::class)->finalize($payroll);
        $slip = SalarySlip::query()->sole();

        $response = $this->actingAs($this->adminOf($company))->get("/salary-slips/{$slip->id}?download=1");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="SLIP-202609-EMP-0001.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        Storage::disk('local')->assertExists("salary-slips/{$company->id}/{$payroll->id}/SLIP-202609-EMP-0001.pdf");
    }

    public function test_no_salary_slip_exists_before_the_payroll_is_finalized(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);
        $this->calculatedPayroll();

        $response = $this->actingAs($this->adminOf($company))->get('/salary-slips');

        $response->assertInertia(fn (Assert $page) => $page->has('slips.data', 0));
        $this->assertSame(0, SalarySlip::query()->count());
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);

        return $company;
    }

    /**
     * @return array{0: Payroll, 1: PayrollItem}
     */
    private function calculatedPayroll(): array
    {
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->calculate($payrolls->create(2026, 9));

        return [$payroll, PayrollItem::query()->where('payroll_id', $payroll->id)->firstOrFail()];
    }

    /**
     * A user whose custom role holds exactly the given permissions.
     *
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(Company $company, array $permissions): User
    {
        $role = new Role(['name' => 'Payroll Clerk', 'slug' => 'payroll-clerk', 'permissions' => $permissions]);
        $role->save();

        return User::factory()->forCompany($company, $role)->create();
    }
}
