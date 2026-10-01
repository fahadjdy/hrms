<?php

namespace Tests\Feature\Http;

use App\Jobs\GenerateSalarySlips;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Overtime;
use App\Models\User;
use App\Services\BorrowCalculationService;
use App\Services\EmployeeService;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_company_user_without_a_role_can_open_the_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_dashboard_renders_the_twelve_kpis_and_defers_the_widget_groups(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee([], 26000);

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('kpis', 12)
            ->where('kpis', fn ($kpis) => collect($kpis)->pluck('key')->all() === [
                'total_employees', 'active_employees', 'past_employees', 'present_today', 'absent_today',
                'on_leave_today', 'late_today', 'current_payroll', 'total_overtime', 'total_borrowed',
                'borrow_outstanding', 'total_deductions',
            ])
            ->has('kpis.0', fn (Assert $kpi) => $kpi
                ->where('key', 'total_employees')
                ->where('label', 'Total Employees')
                ->where('value', 1)
                ->where('format', 'number')
                ->where('href', '/employees')
                ->hasAll(['hint', 'delta', 'tone']))
            ->where('today', '2026-10-01')
            ->where('filters.preset', 'current_month')
            ->where('filters.from', '2026-10-01')
            ->where('filters.to', '2026-10-31')
            ->has('options.departments')
            ->has('options.employees', 1)
            ->missing('attendance')
            ->missing('payroll')
            ->missing('people')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('attendance.overview', 6)
                ->has('attendance.rankings', 5)
                ->has('attendance.hours', fn (Assert $hours) => $hours
                    ->hasAll(['required_minutes', 'worked_minutes', 'short_minutes', 'overtime_minutes']))
                ->has('attendance.rate_explanation')
                ->has('hoursTrend', 12)
                ->where('hoursTrend.11.month', '2026-10')
                ->has('payroll.trend', 12)
                ->has('borrow.trend', 12)
                ->has('borrow', fn (Assert $borrow) => $borrow
                    ->hasAll(['total_borrowed', 'total_recovered', 'outstanding', 'employees_with_borrow', 'recovered_this_month', 'highest_outstanding', 'trend']))
                ->has('people.headcount', fn (Assert $headcount) => $headcount
                    ->hasAll(['active', 'past', 'new_this_month', 'exited_this_month', 'on_probation', 'on_notice']))
                ->has('people.by_gender', 3)
                ->has('calendar', fn (Assert $calendar) => $calendar
                    ->hasAll(['upcoming_holidays', 'upcoming_weekly_offs', 'month_label', 'working_days', 'remaining_working_days']))
                ->has('activity')));
    }

    public function test_kpis_report_headcount_todays_attendance_and_borrow_totals(): void
    {
        $company = $this->prepareCompany();
        $present = $this->createEmployee();
        $late = $this->createEmployee();
        $absent = $this->createEmployee();
        Employee::factory()->past('2026-08-31')->create();
        // 1 October 2026 is a Thursday.
        $this->markAttendance($present, '2026-10-01', 'present');
        $this->markAttendance($late, '2026-10-01', 'present', '10:00', '19:00');
        $this->markAttendance($absent, '2026-10-01', 'absent');
        $borrow = app(BorrowCalculationService::class)->create($present, [
            'amount' => 20000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 5000,
        ]);
        app(BorrowCalculationService::class)->recover($borrow, 5000, CarbonImmutable::parse('2026-09-30'));
        Overtime::query()->create([
            'employee_id' => $present->id, 'date' => '2026-10-01', 'calculation_type' => 'fixed', 'amount' => 1500, 'status' => 'approved',
        ]);

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('kpis', fn ($kpis) => collect($kpis)->pluck('value', 'key')->map(fn ($value) => (float) $value)->all() === [
                'total_employees' => 4.0,
                'active_employees' => 3.0,
                'past_employees' => 1.0,
                'present_today' => 2.0,
                'absent_today' => 1.0,
                'on_leave_today' => 0.0,
                'late_today' => 1.0,
                'current_payroll' => 0.0,
                'total_overtime' => 1500.0,
                'total_borrowed' => 20000.0,
                'borrow_outstanding' => 15000.0,
                'total_deductions' => 0.0,
            ]));
    }

    public function test_payroll_widgets_show_the_latest_calculated_payroll(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $company = $this->prepareCompany();
        $this->configure(['attendance_mode' => 'automatic']);
        $sales = Department::factory()->create(['name' => 'Sales']);
        $employee = $this->createEmployee(['department_id' => $sales->id], 26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01',
        ]);
        $payrolls = app(PayrollService::class);
        $payrolls->finalize($payrolls->calculate($payrolls->create(2026, 9)));

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard?preset=previous_month');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', 'previous_month')
            ->where('filters.from', '2026-09-01')
            ->where('filters.to', '2026-09-30')
            ->where('kpis.7.key', 'current_payroll')
            ->where('kpis.7.label', 'Payroll - September 2026')
            ->where('kpis.7.value', 23000)
            ->where('kpis.11.value', 3000)
            ->loadDeferredProps('finance', fn (Assert $reload) => $reload
                ->where('payroll.current.label', 'September 2026')
                ->where('payroll.current.status', 'finalized')
                ->where('payroll.current.gross', 26000)
                ->where('payroll.current.net_payable', 23000)
                ->where('payroll.by_department.0.label', 'Sales')
                ->where('payroll.by_department.0.value', 23000)
                ->where('payroll.deductions', [
                    ['key' => 'attendance', 'label' => 'Attendance deductions', 'value' => 1000],
                    ['key' => 'borrow_recovery', 'label' => 'Borrow recovery', 'value' => 2000],
                    ['key' => 'short_hours', 'label' => 'Short-hours deductions', 'value' => 0],
                    ['key' => 'other', 'label' => 'Other deductions', 'value' => 0],
                ])
                ->where('payroll.trend.10.month', '2026-09')
                ->where('payroll.trend.10.gross', 26000)
                ->where('payroll.trend.10.net', 23000)
                ->where('borrow.total_borrowed', 6000)
                ->where('borrow.total_recovered', 2000)
                ->where('borrow.outstanding', 4000)
                ->where('borrow.employees_with_borrow', 1)
                ->where('borrow.highest_outstanding.0.value', 4000)));
    }

    public function test_attendance_widgets_summarize_the_selected_period(): void
    {
        $company = $this->prepareCompany();
        $first = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao']);
        $second = $this->createEmployee(['first_name' => 'Bina', 'last_name' => 'Shah']);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'] as $date) {
            $this->markAttendance($first, $date, 'present');
        }
        $this->markAttendance($second, '2026-09-07', 'present', '09:00', '20:00');
        $this->markAttendance($second, '2026-09-08', 'absent');
        $this->markAttendance($second, '2026-09-09', 'wfh');
        $this->markAttendance($second, '2026-09-10', 'paid_leave');
        $this->markAttendance($second, '2026-09-11', 'half_day');

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard?preset=previous_month');

        $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps('attendance', fn (Assert $reload) => $reload
            ->where('attendance.overview', [
                ['key' => 'present', 'label' => 'Present', 'value' => 6],
                ['key' => 'absent', 'label' => 'Absent', 'value' => 1],
                ['key' => 'leave', 'label' => 'Leave', 'value' => 1],
                ['key' => 'wfh', 'label' => 'WFH', 'value' => 1],
                ['key' => 'half_day', 'label' => 'Half Day', 'value' => 1],
                ['key' => 'weekly_off', 'label' => 'Weekly Off', 'value' => 0],
            ])
            // Attended 6 present + 1 WFH + 1 paid leave + half of 1 half day = 8.5 of 10 working-day records.
            ->where('attendance.rate', 85)
            // 08:00 required x 8 full days + 4h half day + 8h for the absent and leave days.
            ->where('attendance.hours.overtime_minutes', 120)
            ->where('attendance.rankings.0.key', 'highest_attendance')
            ->where('attendance.rankings.0.rows.0.name', 'Asha Rao')
            ->where('attendance.rankings.0.rows.0.value', 100)
            ->where('attendance.rankings.1.key', 'highest_overtime')
            ->where('attendance.rankings.1.rows.0.name', 'Bina Shah')
            ->where('attendance.rankings.1.rows.0.value', 120)
            ->where('attendance.rankings.4.key', 'most_absent')
            ->where('attendance.rankings.4.rows.0.name', 'Bina Shah')
            ->where('hoursTrend.10.month', '2026-09')
            ->where('hoursTrend.10.overtime_minutes', 120)));
    }

    public function test_department_filter_limits_the_widgets_to_that_department(): void
    {
        $company = $this->prepareCompany();
        $sales = Department::factory()->create(['name' => 'Sales']);
        $finance = Department::factory()->create(['name' => 'Finance']);
        $this->createEmployee(['department_id' => $sales->id]);
        $this->createEmployee(['department_id' => $sales->id]);
        $inFinance = $this->createEmployee(['department_id' => $finance->id]);
        app(BorrowCalculationService::class)->create($inFinance, [
            'amount' => 8000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000,
        ]);

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard?department_id='.$sales->id);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.department_id', $sales->id)
            ->where('kpis.0.value', 2)
            ->where('kpis.9.key', 'total_borrowed')
            ->where('kpis.9.value', 0)
            ->loadDeferredProps('people', fn (Assert $reload) => $reload
                ->where('people.headcount.active', 2)
                ->where('people.by_department', [['label' => 'Sales', 'value' => 2]])));
    }

    public function test_filtering_by_another_companys_employee_shows_no_data(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $outsider = $this->createEmployee();
        app(BorrowCalculationService::class)->create($outsider, ['amount' => 9000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000]);
        $company = $this->useCompany($this->createCompany());
        $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard?employee_id='.$outsider->id);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('kpis.0.value', 0)
            ->where('kpis.9.value', 0)
            ->where('kpis.10.value', 0)
            ->has('options.employees', 1));
    }

    public function test_people_and_calendar_widgets_list_joiners_exits_and_upcoming_holidays(): void
    {
        $company = $this->prepareCompany();
        $this->createEmployee(['first_name' => 'Old', 'last_name' => 'Hand', 'gender' => 'male', 'joining_date' => '2025-01-01']);
        $this->createEmployee(['first_name' => 'New', 'last_name' => 'Joiner', 'gender' => 'female', 'joining_date' => '2026-10-01']);
        $this->createEmployee([
            'first_name' => 'Pro', 'last_name' => 'Bation', 'gender' => null, 'status' => 'probation',
            'joining_date' => '2026-08-01', 'probation_end_date' => '2026-10-20',
        ]);
        $leaving = $this->createEmployee(['first_name' => 'Gone', 'last_name' => 'Away', 'gender' => 'male']);
        app(EmployeeService::class)->exit($leaving, [
            'exit_date' => '2026-10-01', 'last_working_date' => '2026-10-01', 'exit_type' => 'resignation',
        ]);
        Holiday::factory()->create(['name' => 'Diwali', 'date' => '2026-11-08']);

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps('people', fn (Assert $reload) => $reload
            ->where('people.headcount', [
                'active' => 3, 'past' => 1, 'new_this_month' => 1, 'exited_this_month' => 1, 'on_probation' => 1, 'on_notice' => 0,
            ])
            ->where('people.by_gender', [
                ['label' => 'Male', 'value' => 1],
                ['label' => 'Female', 'value' => 1],
                ['label' => 'Other / Not specified', 'value' => 1],
            ])
            ->where('people.new_joiners.0.name', 'New Joiner')
            ->where('people.upcoming_probation.0.name', 'Pro Bation')
            ->where('people.upcoming_probation.0.date', '2026-10-20')
            ->where('people.recent_exits.0.name', 'Gone Away')
            ->where('calendar.upcoming_holidays.0.name', 'Diwali')
            ->where('calendar.upcoming_holidays.0.in_days', 38)
            ->where('calendar.month_label', 'October 2026')
            // October 2026 has 31 days and four Sundays.
            ->where('calendar.working_days', 27)
            ->where('calendar.remaining_working_days', 27)
            ->where('calendar.upcoming_weekly_offs.0.date', '2026-10-04')
            ->where('activity.0.action', 'employee.exited')
            ->where('activity.0.employee', 'Gone Away')));
    }

    public function test_custom_range_requires_both_dates(): void
    {
        $company = $this->prepareCompany();

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard?preset=custom&from=2026-09-10');

        $response->assertSessionHasErrors('to');
    }

    public function test_custom_range_is_reflected_in_the_filters(): void
    {
        $company = $this->prepareCompany();

        $response = $this->actingAs($this->adminOf($company))->get('/dashboard?preset=custom&from=2026-09-10&to=2026-09-20');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.preset', 'custom')
            ->where('filters.from', '2026-09-10')
            ->where('filters.to', '2026-09-20'));
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');

        return $this->useCompany($this->createCompany());
    }
}
