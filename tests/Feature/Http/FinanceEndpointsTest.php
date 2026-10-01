<?php

namespace Tests\Feature\Http;

use App\Enums\OvertimeStatus;
use App\Jobs\GenerateSalarySlips;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\SalaryBonus;
use App\Models\SalaryDeduction;
use App\Models\ShortHoursAdjustment;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class FinanceEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_hourly_overtime_amount_is_hours_times_rate(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post('/overtime', [
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'hourly',
            'hours' => 5, 'rate' => 200, 'amount' => 99999, 'reason' => 'Release night', 'status' => 'approved',
        ]);

        $response->assertSessionHasNoErrors();
        $overtime = Overtime::query()->sole();
        $this->assertSame(1000.0, $overtime->amount);
        $this->assertSame(5.0, $overtime->hours);
        $this->assertSame(OvertimeStatus::Approved, $overtime->status);
        $this->assertSame($admin->id, $overtime->created_by);
        $this->assertTrue(AuditLog::query()->where('action', 'overtime.created')->where('employee_id', $employee->id)->exists());
    }

    public function test_fixed_overtime_uses_the_entered_amount(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/overtime', [
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed',
            'amount' => 1500, 'status' => 'pending',
        ]);

        $response->assertSessionHasNoErrors();
        $overtime = Overtime::query()->sole();
        $this->assertSame(1500.0, $overtime->amount);
        $this->assertNull($overtime->rate);
        $this->assertSame(OvertimeStatus::Pending, $overtime->status);
    }

    public function test_hourly_overtime_requires_hours_and_a_rate(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/overtime', [
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'hourly', 'status' => 'approved',
        ]);

        $response->assertSessionHasErrors([
            'hours' => 'Enter the overtime hours.',
            'rate' => 'Enter the rate per hour.',
        ]);
        $this->assertSame(0, Overtime::query()->count());
    }

    public function test_overtime_cannot_be_stored_as_paid(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/overtime', [
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed', 'amount' => 1500, 'status' => 'paid',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame(0, Overtime::query()->count());
    }

    public function test_updating_overtime_recalculates_its_amount(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $overtime = $this->overtime($employee, 1500);

        $response = $this->actingAs($this->adminOf($company))->put("/overtime/{$overtime->id}", [
            'employee_id' => $employee->id, 'date' => '2026-09-12', 'calculation_type' => 'hourly',
            'hours' => 2.5, 'rate' => 300, 'status' => 'approved',
        ]);

        $response->assertSessionHasNoErrors();
        $overtime->refresh();
        $this->assertSame(750.0, $overtime->amount);
        $this->assertSame('2026-09-12', $overtime->date->toDateString());
    }

    public function test_entries_paid_in_a_finalized_payroll_cannot_be_changed_or_deleted(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $company = $this->prepareCompany();
        $this->configure(['attendance_mode' => 'automatic']);
        $employee = $this->createEmployee([], 26000);
        $overtime = $this->overtime($employee, 1500);
        $bonus = SalaryBonus::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-15', 'type' => 'bonus', 'title' => 'Festival Bonus', 'amount' => 1000]);
        $deduction = SalaryDeduction::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-20', 'title' => 'Uniform', 'amount' => 500]);
        $payrolls = app(PayrollService::class);
        $payrolls->finalize($payrolls->calculate($payrolls->create(2026, 9)));
        $this->actingAs($this->adminOf($company));

        $this->put("/overtime/{$overtime->id}", [
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed', 'amount' => 1, 'status' => 'approved',
        ])->assertSessionHasErrors(['overtime' => 'This overtime was paid in a finalized payroll and can no longer be changed.']);
        $this->delete("/overtime/{$overtime->id}")->assertSessionHasErrors('overtime');
        $this->put("/bonuses/{$bonus->id}", [
            'employee_id' => $employee->id, 'date' => '2026-09-15', 'type' => 'bonus', 'title' => 'Changed', 'amount' => 1,
        ])->assertSessionHasErrors(['bonus' => 'This entry was paid in a finalized payroll and can no longer be changed.']);
        $this->delete("/bonuses/{$bonus->id}")->assertSessionHasErrors('bonus');
        $this->put("/deductions/{$deduction->id}", [
            'employee_id' => $employee->id, 'date' => '2026-09-20', 'title' => 'Changed', 'amount' => 1,
        ])->assertSessionHasErrors(['deduction' => 'This deduction was applied in a finalized payroll and can no longer be changed.']);
        $this->delete("/deductions/{$deduction->id}")->assertSessionHasErrors('deduction');

        $this->assertSame(1500.0, $overtime->refresh()->amount);
        $this->assertSame(OvertimeStatus::Paid, $overtime->status);
        $this->assertSame('Festival Bonus', $bonus->refresh()->title);
        $this->assertSame(500.0, $deduction->refresh()->amount);
    }

    public function test_unpaid_overtime_can_be_deleted(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $overtime = $this->overtime($employee, 1500);

        $response = $this->actingAs($this->adminOf($company))->delete("/overtime/{$overtime->id}");

        $response->assertSessionHasNoErrors();
        $this->assertModelMissing($overtime);
        $this->assertTrue(AuditLog::query()->where('action', 'overtime.deleted')->exists());
    }

    public function test_overtime_page_totals_only_count_approved_and_paid_entries(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->overtime($employee, 1500);
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-11', 'calculation_type' => 'hourly', 'hours' => 2, 'rate' => 250, 'amount' => 500, 'status' => 'approved',
        ]);
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-12', 'calculation_type' => 'fixed', 'amount' => 900, 'status' => 'pending',
        ]);
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-08-30', 'calculation_type' => 'fixed', 'amount' => 700, 'status' => 'approved',
        ]);

        $response = $this->actingAs($this->adminOf($company))->get('/overtime?month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-09')
            ->has('entries.data', 3)
            ->where('totals.amount', 2000)
            ->where('totals.hours', 2)
            ->where('totals.pending', 1));
    }

    public function test_bonus_and_deduction_are_stored_and_audited(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->actingAs($this->adminOf($company));

        $this->post('/bonuses', [
            'employee_id' => $employee->id, 'date' => '2026-09-15', 'type' => 'other_earning', 'title' => 'Travel reimbursement', 'amount' => 750.5,
        ])->assertSessionHasNoErrors();
        $this->post('/deductions', [
            'employee_id' => $employee->id, 'date' => '2026-09-20', 'title' => 'Uniform', 'amount' => 500, 'reason' => 'Two shirts',
        ])->assertSessionHasNoErrors();

        $bonus = SalaryBonus::query()->sole();
        $this->assertSame(SalaryBonus::TYPE_OTHER_EARNING, $bonus->type);
        $this->assertSame(750.5, $bonus->amount);
        $deduction = SalaryDeduction::query()->sole();
        $this->assertSame(500.0, $deduction->amount);
        $this->assertSame('Two shirts', $deduction->reason);
        $this->assertTrue(AuditLog::query()->where('action', 'bonus.created')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'deduction.created')->exists());
    }

    public function test_bonus_and_deduction_amounts_must_be_positive(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->actingAs($this->adminOf($company));

        $this->post('/bonuses', [
            'employee_id' => $employee->id, 'date' => '2026-09-15', 'type' => 'bonus', 'title' => 'Zero', 'amount' => 0,
        ])->assertSessionHasErrors('amount');
        $this->post('/deductions', [
            'employee_id' => $employee->id, 'date' => '2026-09-20', 'title' => 'Negative', 'amount' => -50,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, SalaryBonus::query()->count());
        $this->assertSame(0, SalaryDeduction::query()->count());
    }

    public function test_short_hours_page_separates_required_actual_calculated_adjusted_and_final(): void
    {
        $company = $this->prepareCompany();
        $this->configure(['attendance_mode' => 'automatic', 'short_hours_mode' => 'deduct']);
        // 26,000 over 26 working days of 8 hours is 125 per hour.
        $employee = $this->createEmployee(['first_name' => 'Rahul', 'last_name' => 'Sharma'], 26000);
        $this->createEmployee([], 26000);
        $this->markAttendance($employee, '2026-09-10', 'present', '09:00', '17:00');
        $this->actingAs($this->adminOf($company));

        $this->put("/short-hours/{$employee->id}", ['month' => '2026-09', 'amount' => 80, 'reason' => 'Agreed with the employee'])
            ->assertSessionHasNoErrors();

        $this->get('/short-hours?month=2026-09')->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-09')
            ->where('rule.mode', 'deduct')
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Rahul Sharma')
            ->where('employees.data.0.short_hours.required_minutes', 26 * 480)
            ->where('employees.data.0.short_hours.worked_minutes', 26 * 480 - 60)
            ->where('employees.data.0.short_hours.short_minutes', 60)
            ->where('employees.data.0.short_hours.hourly_rate', 125)
            ->where('employees.data.0.short_hours.calculated_amount', 125)
            ->where('employees.data.0.short_hours.adjusted_amount', 80)
            ->where('employees.data.0.short_hours.final_amount', 80)
            ->where('employees.data.0.short_hours.adjustment_reason', 'Agreed with the employee'));
        $this->assertTrue(AuditLog::query()->where('action', 'short_hours.adjusted')->exists());
    }

    public function test_short_hours_adjustment_requires_a_reason(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))
            ->put("/short-hours/{$employee->id}", ['month' => '2026-09', 'amount' => 80]);

        $response->assertSessionHasErrors(['reason' => 'The reason field is required.']);
        $this->assertSame(0, ShortHoursAdjustment::query()->count());
    }

    public function test_removing_the_short_hours_adjustment_restores_the_company_rule(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->actingAs($this->adminOf($company));
        $this->put("/short-hours/{$employee->id}", ['month' => '2026-09', 'amount' => 80, 'reason' => 'Agreed']);

        $response = $this->delete("/short-hours/{$employee->id}", ['month' => '2026-09']);

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, ShortHoursAdjustment::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'short_hours.adjustment_removed')->exists());
    }

    public function test_viewer_role_can_see_finance_pages_but_not_change_them(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $overtime = $this->overtime($employee, 1500);
        $this->actingAs($this->userWithRole($company, 'viewer'));

        $this->get('/overtime')->assertOk();
        $this->get('/deductions')->assertOk();
        $this->post('/overtime', [])->assertForbidden();
        $this->delete("/overtime/{$overtime->id}")->assertForbidden();
        $this->post('/bonuses', [])->assertForbidden();
        $this->post('/deductions', [])->assertForbidden();
        $this->put("/short-hours/{$employee->id}", ['month' => '2026-09', 'amount' => 1, 'reason' => 'x'])->assertForbidden();

        $this->assertModelExists($overtime);
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');

        return $this->useCompany($this->createCompany());
    }

    private function overtime(Employee $employee, float $amount): Overtime
    {
        return Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed', 'amount' => $amount, 'status' => 'approved',
        ]);
    }
}
