<?php

namespace Tests\Feature\Services;

use App\Models\Employee;
use App\Models\Overtime;
use App\Models\SalaryBonus;
use App\Models\SalaryDeduction;
use App\Services\BorrowCalculationService;
use App\Services\PayrollCalculationService;
use App\Support\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * September 2026 has 30 days and four Sundays, so a company with Sunday as
 * its weekly off has 26 working days. A salary of 26,000 is therefore 1,000
 * per working day and 125 per hour on an 8-hour shift.
 */
class PayrollCalculationServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_full_month_of_attendance_pays_the_full_salary(): void
    {
        $employee = $this->employeeWithSalary(26000);

        $result = $this->calculate($employee);

        $this->assertSame(26000.0, $result['buckets']['gross_salary']['final']);
        $this->assertSame(0.0, $result['totals']['total_deductions']);
        $this->assertSame(26000.0, $result['totals']['net_payable']);
        $this->assertSame(1000.0, $result['salary']['per_day_rate']);
        $this->assertSame(125.0, $result['salary']['hourly_rate']);
    }

    public function test_salary_components_are_listed_and_add_up_to_the_gross(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->setSalary($employee, 35000, '2025-01-01', [
            ['name' => 'HRA', 'type' => 'earning', 'amount' => 5000],
            ['name' => 'Other Allowance', 'type' => 'earning', 'amount' => 2000],
        ]);

        $result = $this->calculate($employee);

        $this->assertSame(
            ['salary.basic' => 35000.0, 'salary.hra' => 5000.0, 'salary.other_allowance' => 2000.0],
            collect($result['lines'])->where('bucket', 'gross_salary')->pluck('amount', 'code')->all(),
        );
        $this->assertSame(42000.0, $result['buckets']['gross_salary']['final']);
    }

    public function test_salary_before_a_revision_uses_the_old_amount_and_after_it_the_new_amount(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->setSalary($employee, 30000, '2026-01-01');
        $this->setSalary($employee, 35000, '2026-07-01', reason: 'Annual Increment');

        $june = app(PayrollCalculationService::class)->calculate($employee, PayrollPeriod::forMonth(2026, 6));
        $july = app(PayrollCalculationService::class)->calculate($employee, PayrollPeriod::forMonth(2026, 7));

        $this->assertSame(30000.0, $june['buckets']['gross_salary']['system']);
        $this->assertSame(35000.0, $july['buckets']['gross_salary']['system']);
    }

    public function test_revision_in_the_middle_of_a_period_weights_both_salaries_by_days(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->setSalary($employee, 30000, '2026-01-01');
        $this->setSalary($employee, 36000, '2026-09-16', reason: 'Promotion');

        $result = $this->calculate($employee);

        // 15 days at 30,000 and 15 days at 36,000.
        $this->assertSame(33000.0, $result['buckets']['gross_salary']['system']);
        $this->assertCount(2, $result['salary']['segments']);
    }

    public function test_absent_days_are_deducted_at_the_per_day_rate(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        $this->markAttendance($employee, '2026-09-04', 'absent');

        $result = $this->calculate($employee);

        $this->assertSame(2000.0, $result['buckets']['attendance_deduction']['final']);
        $this->assertSame(24000.0, $result['totals']['net_payable']);
        $this->assertStringContainsString('2 absent day(s) x 1,000.00', $this->line($result, 'attendance.absent')['note']);
    }

    public function test_half_day_deducts_half_of_the_per_day_rate(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $this->markAttendance($employee, '2026-09-03', 'half_day');

        $result = $this->calculate($employee);

        $this->assertSame(500.0, $result['buckets']['attendance_deduction']['final']);
    }

    public function test_unpaid_leave_is_deducted_and_paid_leave_is_not(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $this->markAttendance($employee, '2026-09-03', 'unpaid_leave');
        $this->markAttendance($employee, '2026-09-04', 'paid_leave');

        $result = $this->calculate($employee);

        $this->assertSame(1000.0, $result['buckets']['unpaid_leave_deduction']['final']);
        $this->assertSame(0.0, $result['buckets']['attendance_deduction']['final']);
        $this->assertSame(25000.0, $result['totals']['net_payable']);
    }

    public function test_holiday_and_weekly_off_are_not_deducted(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $this->markAttendance($employee, '2026-09-03', 'holiday');

        $result = $this->calculate($employee);

        $this->assertSame(0.0, $result['totals']['total_deductions']);
    }

    public function test_employee_who_joined_mid_month_is_paid_only_for_the_days_employed(): void
    {
        $this->prepareCompany();
        // Joining on 21 September leaves 9 of the 26 working days.
        $employee = $this->createEmployee(['joining_date' => '2026-09-21'], 26000);

        $result = $this->calculate($employee);

        $this->assertSame(17000.0, $result['buckets']['attendance_deduction']['final']);
        $this->assertSame(9000.0, $result['totals']['net_payable']);
    }

    public function test_short_hours_are_deducted_when_the_company_rule_is_deduct(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $this->configure(['short_hours_mode' => 'deduct']);
        // 7 of 8 required hours worked: 1 short hour at 125 per hour.
        $this->markAttendance($employee, '2026-09-03', 'present', '09:00', '17:00');

        $result = $this->calculate($employee);

        $this->assertSame(60, $result['short_hours']['short_minutes']);
        $this->assertSame(125.0, $result['buckets']['short_hours_deduction']['final']);
        $this->assertSame(25875.0, $result['totals']['net_payable']);
    }

    public function test_short_hours_are_shown_but_not_deducted_when_the_company_rule_is_record_only(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $this->markAttendance($employee, '2026-09-03', 'present', '09:00', '17:00');

        $result = $this->calculate($employee);

        $this->assertSame(125.0, $result['short_hours']['calculated_amount']);
        $this->assertSame(0.0, $result['buckets']['short_hours_deduction']['final']);
        $this->assertStringContainsString('Recorded only', $this->line($result, 'short_hours')['note']);
    }

    public function test_approved_overtime_is_added_and_pending_overtime_is_not(): void
    {
        $employee = $this->employeeWithSalary(26000);
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'hourly',
            'hours' => 5, 'rate' => 200, 'amount' => 1000, 'status' => 'approved',
        ]);
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-11', 'calculation_type' => 'fixed',
            'amount' => 1500, 'status' => 'approved',
        ]);
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-12', 'calculation_type' => 'fixed',
            'amount' => 900, 'status' => 'pending',
        ]);

        $result = $this->calculate($employee);

        $this->assertSame(2500.0, $result['buckets']['overtime_amount']['final']);
        $this->assertSame(28500.0, $result['totals']['net_payable']);
    }

    public function test_bonuses_and_one_off_deductions_are_included(): void
    {
        $employee = $this->employeeWithSalary(26000);
        SalaryBonus::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-15', 'type' => 'bonus', 'title' => 'Festival Bonus', 'amount' => 1000]);
        SalaryBonus::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-15', 'type' => 'other_earning', 'title' => 'Travel Reimbursement', 'amount' => 300]);
        SalaryDeduction::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-20', 'title' => 'Uniform', 'amount' => 500]);

        $result = $this->calculate($employee);

        $this->assertSame(1000.0, $result['buckets']['bonus_amount']['final']);
        $this->assertSame(300.0, $result['buckets']['other_earnings_amount']['final']);
        $this->assertSame(500.0, $result['buckets']['other_deductions']['final']);
        $this->assertSame(26800.0, $result['totals']['net_payable']);
    }

    public function test_recurring_deduction_in_the_salary_structure_is_deducted_every_month(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->setSalary($employee, 26000, '2025-01-01', [
            ['name' => 'Professional Tax', 'type' => 'deduction', 'amount' => 200],
        ]);

        $result = $this->calculate($employee);

        $this->assertSame(26000.0, $result['buckets']['gross_salary']['final']);
        $this->assertSame(200.0, $result['buckets']['other_deductions']['final']);
        $this->assertSame(25800.0, $result['totals']['net_payable']);
    }

    public function test_scheduled_borrow_recovery_is_deducted_per_borrow(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $borrows = app(BorrowCalculationService::class);
        $borrows->create($employee, ['amount' => 20000, 'borrow_date' => '2026-06-01', 'monthly_deduction' => 3000, 'deduction_start_month' => '2026-07-01']);
        $borrows->create($employee, ['amount' => 5000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-09-01']);

        $result = $this->calculate($employee);

        $this->assertSame(4000.0, $result['buckets']['borrow_recovery']['final']);
        $this->assertSame([3000.0, 1000.0], array_column($result['borrows']['recoveries'], 'amount'));
        $this->assertSame(22000.0, $result['totals']['net_payable']);
    }

    public function test_new_borrow_given_with_salary_is_added_to_net_payable_but_is_not_an_earning(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee([], 40000);
        $borrows = app(BorrowCalculationService::class);
        $borrows->create($employee, ['amount' => 8000, 'borrow_date' => '2026-05-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-06-01']);
        $borrows->create($employee, [
            'amount' => 10000, 'borrow_date' => '2026-09-25', 'monthly_deduction' => 2500,
            'disbursement_method' => 'with_salary', 'disburse_period' => '2026-09-01',
        ]);
        SalaryDeduction::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-20', 'title' => 'Other', 'amount' => 500]);

        $result = $this->calculate($employee);

        // Salary 40,000 + new borrow 10,000 - repayment 2,000 - other deductions 500.
        $this->assertSame(40000.0, $result['totals']['total_earnings']);
        $this->assertSame(10000.0, $result['buckets']['borrow_given']['final']);
        $this->assertSame(2000.0, $result['buckets']['borrow_recovery']['final']);
        $this->assertSame(37500.0, $result['totals']['net_salary']);
        $this->assertSame(47500.0, $result['totals']['net_payable']);
    }

    public function test_borrow_recovery_never_takes_more_than_the_pay_that_is_left(): void
    {
        $this->prepareCompany();
        // Joined on 29 September: 2 working days, so only 2,000 is earned.
        $employee = $this->createEmployee(['joining_date' => '2026-09-29'], 26000);
        app(BorrowCalculationService::class)->create($employee, [
            'kind' => 'existing', 'amount' => 20000, 'borrow_date' => '2026-09-29',
            'monthly_deduction' => 5000, 'deduction_start_month' => '2026-09-01',
        ]);

        $result = $this->calculate($employee);

        $this->assertSame(2000.0, $result['buckets']['borrow_recovery']['final']);
        $this->assertSame(0.0, $result['totals']['net_payable']);
    }

    public function test_unmarked_days_are_treated_as_absent_in_manual_mode_with_a_warning(): void
    {
        $employee = $this->employeeWithSalary(26000);
        $this->configure(['attendance_mode' => 'manual']);

        $result = $this->calculate($employee);

        $this->assertSame(26000.0, $result['buckets']['attendance_deduction']['final']);
        $this->assertSame(0.0, $result['totals']['net_payable']);
        $this->assertStringContainsString('26 working day(s) have no attendance', $result['warnings'][0]);
    }

    public function test_calendar_day_method_divides_the_salary_by_the_days_in_the_month(): void
    {
        $this->prepareCompany();
        $this->configure(['salary_calculation_method' => 'calendar_days']);
        $employee = $this->createEmployee([], 30000);
        $this->markAttendance($employee, '2026-09-03', 'absent');

        $result = $this->calculate($employee);

        $this->assertSame(1000.0, $result['salary']['per_day_rate']);
        $this->assertSame(1000.0, $result['buckets']['attendance_deduction']['final']);
    }

    private function prepareCompany(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
    }

    private function employeeWithSalary(float $monthly): Employee
    {
        $this->prepareCompany();

        return $this->createEmployee([], $monthly);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculate(Employee $employee): array
    {
        $result = app(PayrollCalculationService::class)->calculate($employee, PayrollPeriod::forMonth(2026, 9));
        $result['totals'] = PayrollCalculationService::totals($result['buckets']);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function line(array $result, string $code): array
    {
        return collect($result['lines'])->firstWhere('code', $code);
    }
}
