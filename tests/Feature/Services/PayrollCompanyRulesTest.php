<?php

namespace Tests\Feature\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Overtime;
use App\Services\BorrowCalculationService;
use App\Services\LeaveService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollService;
use App\Services\ShortHoursCalculationService;
use App\Support\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * How each company setting changes the pay of the same employee: 26,000 a
 * month, which in September 2026 (26 working days of 8 hours) is 1,000 per
 * day and 125 per hour.
 */
class PayrollCompanyRulesTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_every_third_late_arrival_deducts_half_a_day_when_the_late_rule_is_on(): void
    {
        $employee = $this->employee();
        $this->configure(['lates_per_half_day' => 3]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10'] as $date) {
            $this->markAttendance($employee, $date, 'present', '09:45', '18:45');
        }

        $result = $this->calculate($employee);

        // Four late days: one full set of three, so one half-day deduction.
        $this->assertSame(4, $result['attendance']['late']);
        $this->assertSame(500.0, $this->line($result, 'attendance.late')['amount']);
        $this->assertSame(500.0, $result['buckets']['attendance_deduction']['final']);
    }

    public function test_late_arrivals_are_not_deducted_when_the_late_rule_is_off(): void
    {
        $employee = $this->employee();
        foreach (['2026-09-07', '2026-09-08', '2026-09-09'] as $date) {
            $this->markAttendance($employee, $date, 'present', '09:45', '18:45');
        }

        $result = $this->calculate($employee);

        $this->assertSame(3, $result['attendance']['late']);
        $this->assertSame(0.0, $result['buckets']['attendance_deduction']['final']);
    }

    public function test_overtime_in_attendance_is_paid_at_the_multiplier_when_the_company_enables_it(): void
    {
        $employee = $this->employee();
        $this->configure(['overtime_from_attendance' => true, 'overtime_multiplier' => 1.5]);
        // 09:00 to 20:00 less the 1h break is 10h worked: 2h of overtime.
        $this->markAttendance($employee, '2026-09-10', 'present', '09:00', '20:00');

        $result = $this->calculate($employee);

        // 2 hours x (125 x 1.5).
        $this->assertSame(375.0, $result['buckets']['overtime_amount']['final']);
        $this->assertSame(120, $result['overtime']['attendance_minutes']);
        $this->assertSame(187.5, $result['overtime']['attendance_rate']);
    }

    public function test_overtime_in_attendance_is_only_recorded_when_the_company_does_not_pay_it(): void
    {
        $employee = $this->employee();
        $this->markAttendance($employee, '2026-09-10', 'present', '09:00', '20:00');

        $result = $this->calculate($employee);

        $this->assertSame(120, $result['attendance']['overtime_minutes']);
        $this->assertSame(0.0, $result['buckets']['overtime_amount']['final']);
    }

    public function test_a_day_with_a_manual_overtime_entry_is_not_paid_again_from_attendance(): void
    {
        $employee = $this->employee();
        $this->configure(['overtime_from_attendance' => true, 'overtime_rate_type' => 'fixed', 'overtime_fixed_rate' => 200]);
        $this->markAttendance($employee, '2026-09-10', 'present', '09:00', '20:00');
        $this->markAttendance($employee, '2026-09-11', 'present', '09:00', '19:00');
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed', 'amount' => 1000, 'status' => 'approved',
        ]);

        $result = $this->calculate($employee);

        // 1,000 manual for the 10th, plus 1 hour x 200 from attendance on the 11th.
        $this->assertSame(1200.0, $result['buckets']['overtime_amount']['final']);
        $this->assertSame(60, $result['overtime']['attendance_minutes']);
    }

    public function test_borrow_recovery_is_capped_at_the_companys_maximum_percentage_of_pay(): void
    {
        $employee = $this->employee();
        $this->configure(['borrow_max_deduction_percent' => 10]);
        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 20000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 5000, 'deduction_start_month' => '2026-09-01',
        ]);

        $result = $this->calculate($employee);

        // 10% of the 26,000 left after other lines.
        $this->assertSame(2600.0, $result['buckets']['borrow_recovery']['final']);
        $this->assertStringContainsString('Limited from 5,000.00', collect($result['lines'])->firstWhere('bucket', 'borrow_recovery')['note']);
    }

    public function test_borrow_is_not_recovered_automatically_when_auto_deduction_is_off(): void
    {
        $employee = $this->employee();
        $this->configure(['borrow_auto_deduct' => false]);
        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 20000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 5000, 'deduction_start_month' => '2026-09-01',
        ]);

        $result = $this->calculate($employee);

        $this->assertSame(0.0, $result['buckets']['borrow_recovery']['final']);
        $this->assertSame([], $result['borrows']['recoveries']);
    }

    public function test_fixed_30_method_divides_the_salary_by_thirty_in_a_31_day_month(): void
    {
        $this->prepareCompany();
        $this->configure(['salary_calculation_method' => 'fixed_30']);
        $employee = $this->createEmployee([], 30000);
        $this->markAttendance($employee, '2026-08-04', 'absent');

        $result = app(PayrollCalculationService::class)->calculate($employee, PayrollPeriod::forMonth(2026, 8));

        $this->assertSame(30, $result['salary']['divisor']);
        $this->assertSame(1000.0, $result['salary']['per_day_rate']);
        $this->assertSame(1000.0, $result['buckets']['attendance_deduction']['final']);
    }

    public function test_payroll_period_follows_the_companys_period_start_day(): void
    {
        $employee = $this->employee();
        $this->configure(['payroll_period_start_day' => 26]);
        // Inside the "September" period that runs 26 September to 25 October.
        $this->travelTo('2026-10-26 10:00:00');
        $this->markAttendance($employee, '2026-10-20', 'absent');
        // Before it: belongs to the previous period.
        $this->markAttendance($employee, '2026-09-25', 'absent');
        $payrolls = app(PayrollService::class);

        $payroll = $payrolls->calculate($payrolls->create(2026, 9));

        $this->assertSame('2026-09-26', $payroll->period_start->toDateString());
        $this->assertSame('2026-10-25', $payroll->period_end->toDateString());
        $this->assertSame('September 2026', $payroll->label());
        $this->assertSame(1.0, $payroll->items()->sole()->absent_days);
    }

    public function test_short_time_within_the_tolerance_is_not_recorded_as_short_hours(): void
    {
        $employee = $this->employee();
        $this->configure(['short_hours_mode' => 'deduct', 'short_hours_tolerance_minutes' => 15]);
        // 10 minutes short of 8 hours.
        $this->markAttendance($employee, '2026-09-10', 'present', '09:00', '17:50');

        $result = $this->calculate($employee);

        $this->assertSame('present', Attendance::query()->sole()->status->value);
        $this->assertSame(0, $result['attendance']['short_minutes']);
        $this->assertSame(0.0, $result['buckets']['short_hours_deduction']['final']);
    }

    public function test_manual_short_hours_mode_deducts_only_what_the_admin_enters(): void
    {
        $employee = $this->employee();
        $this->configure(['short_hours_mode' => 'manual']);
        $this->markAttendance($employee, '2026-09-10', 'present', '09:00', '17:00');

        $before = $this->calculate($employee);
        app(ShortHoursCalculationService::class)->adjust($employee, PayrollPeriod::forMonth(2026, 9), 100, 'Agreed with the employee');
        $after = $this->calculate($employee);

        $this->assertSame(125.0, $before['short_hours']['calculated_amount']);
        $this->assertSame(0.0, $before['buckets']['short_hours_deduction']['final']);
        $this->assertSame(100.0, $after['buckets']['short_hours_deduction']['final']);
        $this->assertStringContainsString('Admin set the deduction to 100.00: Agreed with the employee', $this->line($after, 'short_hours')['note']);
    }

    public function test_half_day_of_unpaid_leave_deducts_half_a_day(): void
    {
        $employee = $this->employee();
        app(LeaveService::class)->create($employee, [
            'leave_type_id' => LeaveType::query()->where('code', 'UL')->sole()->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'is_half_day' => true,
            'status' => 'approved',
        ]);

        $result = $this->calculate($employee);

        $this->assertSame(1, $result['attendance']['half_day']);
        $this->assertSame(500.0, $result['buckets']['attendance_deduction']['final']);
        $this->assertSame(0.0, $result['buckets']['unpaid_leave_deduction']['final']);
    }

    public function test_half_day_of_paid_leave_deducts_nothing(): void
    {
        $employee = $this->employee();
        app(LeaveService::class)->create($employee, [
            'leave_type_id' => LeaveType::query()->where('code', 'CL')->sole()->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'is_half_day' => true,
            'status' => 'approved',
        ]);

        $result = $this->calculate($employee);

        $this->assertSame(26000.0, PayrollCalculationService::totals($result['buckets'])['net_payable']);
    }

    public function test_work_on_a_weekly_off_counts_entirely_as_overtime(): void
    {
        $employee = $this->employee();
        // 13 September 2026 is a Sunday.
        $this->markAttendance($employee, '2026-09-13', 'weekly_off', '10:00', '14:00');

        $result = $this->calculate($employee);

        $attendance = Attendance::query()->sole();
        $this->assertSame(0, $attendance->required_minutes);
        $this->assertSame(240, $attendance->worked_minutes);
        $this->assertSame(240, $attendance->overtime_minutes);
        $this->assertSame(0.0, $result['buckets']['attendance_deduction']['final']);
    }

    public function test_days_still_to_come_in_the_running_month_are_not_deducted(): void
    {
        $this->travelTo('2026-10-15 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'manual']);
        $employee = $this->createEmployee([], 27000);
        // October 2026 has 27 working days: 1,000 per day. Only the 13 that have passed are unmarked.
        $result = app(PayrollCalculationService::class)->calculate($employee, PayrollPeriod::forMonth(2026, 10));

        $this->assertSame(13, $result['attendance']['unmarked']);
        $this->assertSame(14, $result['attendance']['upcoming']);
        $this->assertSame(13000.0, $result['buckets']['attendance_deduction']['final']);
    }

    public function test_employee_without_a_salary_is_paid_nothing_with_a_warning(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();

        $result = $this->calculate($employee);

        $this->assertSame(0.0, PayrollCalculationService::totals($result['buckets'])['net_payable']);
        $this->assertSame('No salary is set for this period. Add a salary structure for this employee.', $result['warnings'][0]);
    }

    public function test_salary_that_starts_after_joining_is_not_paid_for_the_earlier_days(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-09-01']);
        // Salary only starts on 21 September: 9 of the 26 working days.
        $this->setSalary($employee, 26000, '2026-09-21');

        $result = $this->calculate($employee);

        $this->assertSame(26000.0, $result['buckets']['gross_salary']['final']);
        $this->assertSame(17000.0, $result['buckets']['attendance_deduction']['final']);
        $this->assertSame(9000.0, PayrollCalculationService::totals($result['buckets'])['net_payable']);
    }

    private function prepareCompany(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
    }

    private function employee(): Employee
    {
        $this->prepareCompany();

        return $this->createEmployee([], 26000);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculate(Employee $employee): array
    {
        return app(PayrollCalculationService::class)->calculate($employee, PayrollPeriod::forMonth(2026, 9));
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
