<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\BorrowStatus;
use App\Enums\BorrowTransactionType;
use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Enums\OvertimeStatus;
use App\Enums\PayrollStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\Overtime;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only aggregates for the BI dashboard.
 *
 * Everything is computed in the database with grouped queries over indexed
 * columns; no attendance or payroll history is ever loaded into memory or
 * sent to the browser. The 12-month trend series are cached briefly.
 */
class DashboardService
{
    private const int TREND_MONTHS = 12;

    private const int TREND_CACHE_SECONDS = 300;

    private const int RANKING_SIZE = 5;

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    private ?int $departmentId = null;

    private ?int $employeeId = null;

    private ?string $employeeStatus = null;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly WorkingCalendarService $calendar,
    ) {
        $today = $this->tenant->check() ? $this->tenant->today() : CarbonImmutable::today();
        $this->from = $today->startOfMonth();
        $this->to = $today->endOfMonth()->startOfDay();
    }

    /**
     * @param  array{from?: string|null, to?: string|null, department_id?: int|string|null, employee_id?: int|string|null, employee_status?: string|null}  $filters
     */
    public function filter(array $filters): static
    {
        if (! empty($filters['from']) && ! empty($filters['to'])) {
            $this->from = CarbonImmutable::parse($filters['from'])->startOfDay();
            $this->to = CarbonImmutable::parse($filters['to'])->startOfDay();
        }

        $this->departmentId = ! empty($filters['department_id']) ? (int) $filters['department_id'] : null;
        $this->employeeId = ! empty($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $this->employeeStatus = ! empty($filters['employee_status']) ? (string) $filters['employee_status'] : null;

        return $this;
    }

    /**
     * The top KPI row.
     *
     * @return list<array<string, mixed>>
     */
    public function kpis(): array
    {
        $today = $this->tenant->today();
        $monthStart = $today->startOfMonth();

        $employees = $this->employees()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(case when status <> ? then 1 else 0 end), 0) as current_count', [EmployeeStatus::Past->value])
            ->selectRaw('coalesce(sum(case when status = ? then 1 else 0 end), 0) as past_count', [EmployeeStatus::Past->value])
            ->selectRaw('coalesce(sum(case when joining_date >= ? then 1 else 0 end), 0) as joined_this_month', [$monthStart->toDateString()])
            ->first();

        $todayCounts = $this->statusCounts($today, $today);
        $payroll = $this->currentPayroll();
        $previousPayroll = $payroll !== null ? $this->payrollBefore($payroll) : null;

        $overtime = $this->overtimeAmount($this->from, $this->to);
        $previousRange = $this->previousRange();
        $previousOvertime = $this->overtimeAmount($previousRange[0], $previousRange[1]);

        $borrowed = (float) $this->borrows()->whereIn('status', [BorrowStatus::Active->value, BorrowStatus::Recovered->value])->sum('opening_balance');
        $outstanding = (float) $this->borrows()->outstanding()->sum('outstanding_amount');

        $deductions = $payroll !== null ? $this->payrollItemSum($payroll, 'attendance_deduction + unpaid_leave_deduction + short_hours_deduction + borrow_recovery + other_deductions') : 0.0;
        $netPayable = $payroll !== null ? $this->payrollItemSum($payroll, 'net_payable') : 0.0;
        $previousNet = $previousPayroll !== null ? $this->payrollItemSum($previousPayroll, 'net_payable') : null;

        $active = (int) $employees->current_count;
        $ofActive = fn (int $count, string $tone): ?array => $this->progress($count, $active, "of {$active} active", $tone);
        $payrollTrend = $this->payrollTrend();
        $present = $this->presentCount($todayCounts);
        $absent = $todayCounts[AttendanceStatus::Absent->value] ?? 0;
        $onLeave = ($todayCounts[AttendanceStatus::PaidLeave->value] ?? 0) + ($todayCounts[AttendanceStatus::UnpaidLeave->value] ?? 0);
        $late = $todayCounts[AttendanceStatus::Late->value] ?? 0;

        return [
            $this->kpi('total_employees', 'Total Employees', (int) $employees->total, 'number', 'Everyone on record, current and past', route: 'employees.index'),
            $this->kpi('active_employees', 'Active Employees', (int) $employees->current_count, 'number',
                $employees->joined_this_month > 0 ? "{$employees->joined_this_month} joined this month" : 'Currently working at the company',
                route: 'employees.index'),
            $this->kpi('past_employees', 'Past Employees', (int) $employees->past_count, 'number', 'Left the company; history is kept', route: 'employees.past'),
            $this->kpi('present_today', 'Present Today', $present, 'number', 'Includes late, short hours, WFH and half days', tone: 'positive', route: 'attendance.index',
                progress: $ofActive($present, 'positive')),
            $this->kpi('absent_today', 'Absent Today', $absent, 'number', 'Marked absent today', tone: 'negative', route: 'attendance.index', query: ['status' => 'absent'],
                progress: $ofActive($absent, 'negative')),
            $this->kpi('on_leave_today', 'On Leave', $onLeave, 'number', 'Paid and unpaid leave today', tone: 'info', route: 'leaves.index',
                progress: $ofActive($onLeave, 'info')),
            $this->kpi('late_today', 'Late Today', $late, 'number', 'Arrived after the grace period', tone: 'warning', route: 'attendance.index', query: ['status' => 'late'],
                progress: $ofActive($late, 'warning')),
            $this->kpi('current_payroll', $payroll !== null ? "Payroll - {$payroll->label()}" : 'Current Month Payroll', $netPayable, 'money',
                $payroll !== null ? "Net payable - {$payroll->status->label()}" : 'No payroll has been run yet',
                delta: $this->delta($netPayable, $previousNet, 'vs previous payroll'),
                route: 'payroll.index',
                trend: $this->sparkline(array_column($payrollTrend, 'net'))),
            $this->kpi('total_overtime', 'Total Overtime', $overtime, 'money', 'Approved and paid overtime in the selected period',
                delta: $this->delta($overtime, $previousOvertime, 'vs previous period'),
                route: 'overtime.index',
                trend: $this->sparkline(array_column($payrollTrend, 'overtime'))),
            $this->kpi('total_borrowed', 'Total Borrowed', $borrowed, 'money', 'All borrow and advance ever issued', route: 'borrows.index',
                trend: $this->sparkline(array_column($this->borrowTrend(), 'given'))),
            $this->kpi('borrow_outstanding', 'Borrow Outstanding', $outstanding, 'money', 'Still owed by employees', tone: $outstanding > 0 ? 'warning' : 'neutral', route: 'borrows.index', query: ['status' => 'active'],
                progress: $this->progress($borrowed - $outstanding, $borrowed, 'recovered so far', 'positive')),
            $this->kpi('total_deductions', 'Total Deductions', $deductions, 'money', $payroll !== null ? "All deductions in {$payroll->label()}" : 'No payroll has been run yet', route: 'payroll.reports',
                trend: $this->sparkline(array_column($payrollTrend, 'deductions'))),
        ];
    }

    /**
     * Attendance for the selected period: status breakdown, company attendance
     * rate, working hours and the rankings.
     *
     * @return array<string, mixed>
     */
    public function attendance(): array
    {
        $counts = $this->statusCounts($this->from, $this->to);
        $present = $this->presentCount($counts);
        $halfDays = $counts[AttendanceStatus::HalfDay->value] ?? 0;
        $paidLeave = $counts[AttendanceStatus::PaidLeave->value] ?? 0;
        $unpaidLeave = $counts[AttendanceStatus::UnpaidLeave->value] ?? 0;
        $absent = $counts[AttendanceStatus::Absent->value] ?? 0;
        $fullPresent = $present - $halfDays;

        // Every record on a scheduled working day, i.e. not a weekly off or holiday.
        $workingRecords = $present + $absent + $paidLeave + $unpaidLeave + ($counts[AttendanceStatus::Other->value] ?? 0);

        $minutes = $this->attendanceQuery($this->from, $this->to)->toBase()
            ->selectRaw('coalesce(sum(required_minutes), 0) as required')
            ->selectRaw('coalesce(sum(worked_minutes), 0) as worked')
            ->selectRaw('coalesce(sum(short_minutes), 0) as short')
            ->selectRaw('coalesce(sum(overtime_minutes), 0) as overtime')
            ->first();

        return [
            'overview' => [
                ['key' => 'present', 'label' => 'Present', 'value' => $fullPresent - ($counts[AttendanceStatus::WorkFromHome->value] ?? 0)],
                ['key' => 'absent', 'label' => 'Absent', 'value' => $absent],
                ['key' => 'leave', 'label' => 'Leave', 'value' => $paidLeave + $unpaidLeave],
                ['key' => 'wfh', 'label' => 'WFH', 'value' => $counts[AttendanceStatus::WorkFromHome->value] ?? 0],
                ['key' => 'half_day', 'label' => 'Half Day', 'value' => $halfDays],
                ['key' => 'weekly_off', 'label' => 'Weekly Off', 'value' => $counts[AttendanceStatus::WeeklyOff->value] ?? 0],
            ],
            'late_days' => $counts[AttendanceStatus::Late->value] ?? 0,
            'rate' => $workingRecords > 0
                ? round(min(100, ($fullPresent + $paidLeave + ($halfDays * 0.5)) / $workingRecords * 100), 1)
                : null,
            'rate_explanation' => 'Attended days (present, WFH, late, short hours and paid leave; a half day counts as half) divided by all recorded working days in the period.',
            'hours' => [
                'required_minutes' => (int) $minutes->required,
                'worked_minutes' => (int) $minutes->worked,
                'short_minutes' => (int) $minutes->short,
                'overtime_minutes' => (int) $minutes->overtime,
            ],
            'hours_by_department' => $this->hoursByDepartment(),
            'rankings' => $this->rankings(),
        ];
    }

    /**
     * 12-month overtime and short-hours trend from attendance.
     *
     * @return list<array{month: string, label: string, overtime_minutes: int, short_minutes: int}>
     */
    public function hoursTrend(): array
    {
        return $this->cached('hours-trend', function (): array {
            $months = $this->trendMonths();

            $rows = $this->attendanceQuery($months[0]['start'], $months[count($months) - 1]['end'])->toBase()
                ->selectRaw("date_format(date, '%Y-%m') as month")
                ->selectRaw('coalesce(sum(overtime_minutes), 0) as overtime')
                ->selectRaw('coalesce(sum(short_minutes), 0) as short')
                ->groupBy('month')
                ->get()
                ->keyBy('month');

            return array_map(fn (array $month): array => [
                'month' => $month['key'],
                'label' => $month['label'],
                'overtime_minutes' => (int) ($rows[$month['key']]->overtime ?? 0),
                'short_minutes' => (int) ($rows[$month['key']]->short ?? 0),
            ], $months);
        });
    }

    /**
     * Borrow totals, the employees who owe the most and the monthly trend.
     *
     * @return array<string, mixed>
     */
    public function borrow(): array
    {
        $issued = $this->borrows()->whereIn('status', [BorrowStatus::Active->value, BorrowStatus::Recovered->value]);
        $monthStart = $this->tenant->today()->startOfMonth();

        $topRows = $this->borrows()->outstanding()->toBase()
            ->selectRaw('employee_id, sum(outstanding_amount) as outstanding, count(*) as borrows')
            ->groupBy('employee_id')
            ->orderByDesc('outstanding')
            ->limit(self::RANKING_SIZE)
            ->get();
        $names = $this->employeeNames($topRows->pluck('employee_id'));

        return [
            'total_borrowed' => round((float) (clone $issued)->sum('opening_balance'), 2),
            'total_recovered' => round((float) (clone $issued)->sum('recovered_amount'), 2),
            'outstanding' => round((float) $this->borrows()->outstanding()->sum('outstanding_amount'), 2),
            'employees_with_borrow' => $this->borrows()->outstanding()->distinct()->count('employee_id'),
            'recovered_this_month' => round((float) $this->borrowTransactions()
                ->whereIn('type', [BorrowTransactionType::Recovery->value, BorrowTransactionType::Settlement->value])
                ->whereBetween('transaction_date', [$monthStart->toDateString(), $monthStart->endOfMonth()->toDateString()])
                ->sum('amount'), 2),
            'highest_outstanding' => $topRows
                ->filter(fn (object $row): bool => isset($names[$row->employee_id]))
                ->map(fn (object $row): array => [
                    'employee_id' => (int) $row->employee_id,
                    'name' => $names[$row->employee_id]['name'],
                    'code' => $names[$row->employee_id]['code'],
                    'value' => (float) $row->outstanding,
                    'detail' => $row->borrows.' active borrow'.($row->borrows > 1 ? 's' : ''),
                ])
                ->values()
                ->all(),
            'trend' => $this->borrowTrend(),
        ];
    }

    /**
     * Payroll for the latest period plus the 12-month expense trend.
     *
     * @return array<string, mixed>
     */
    public function payroll(): array
    {
        $payroll = $this->currentPayroll();

        if ($payroll === null) {
            return [
                'current' => null,
                'by_department' => [],
                'deductions' => [],
                'trend' => $this->payrollTrend(),
            ];
        }

        $totals = $this->payrollItems($payroll)->toBase()
            ->selectRaw('count(*) as employees')
            ->selectRaw('coalesce(sum(gross_salary), 0) as gross')
            ->selectRaw('coalesce(sum(overtime_amount), 0) as overtime')
            ->selectRaw('coalesce(sum(attendance_deduction + unpaid_leave_deduction), 0) as attendance')
            ->selectRaw('coalesce(sum(short_hours_deduction), 0) as short_hours')
            ->selectRaw('coalesce(sum(borrow_recovery), 0) as borrow_recovery')
            ->selectRaw('coalesce(sum(other_deductions), 0) as other')
            ->selectRaw('coalesce(sum(borrow_given), 0) as borrow_given')
            ->selectRaw('coalesce(sum(net_payable), 0) as net_payable')
            ->first();

        return [
            'current' => [
                'id' => $payroll->id,
                'label' => $payroll->label(),
                'status' => $payroll->status->value,
                'status_label' => $payroll->status->label(),
                'employees' => (int) $totals->employees,
                'gross' => (float) $totals->gross,
                'overtime' => (float) $totals->overtime,
                'borrow_given' => (float) $totals->borrow_given,
                'net_payable' => (float) $totals->net_payable,
            ],
            'by_department' => $this->payrollItems($payroll)->toBase()
                ->selectRaw("coalesce(department_name, 'No department') as department, sum(net_payable - borrow_given) as amount, count(*) as employees")
                ->groupBy('department')
                ->orderByDesc('amount')
                ->get()
                ->map(fn (object $row): array => [
                    'label' => $row->department,
                    'value' => (float) $row->amount,
                    'employees' => (int) $row->employees,
                ])
                ->all(),
            'deductions' => [
                ['key' => 'attendance', 'label' => 'Attendance deductions', 'value' => (float) $totals->attendance],
                ['key' => 'borrow_recovery', 'label' => 'Borrow recovery', 'value' => (float) $totals->borrow_recovery],
                ['key' => 'short_hours', 'label' => 'Short-hours deductions', 'value' => (float) $totals->short_hours],
                ['key' => 'other', 'label' => 'Other deductions', 'value' => (float) $totals->other],
            ],
            'trend' => $this->payrollTrend(),
        ];
    }

    /**
     * Headcount, distributions, joiners and leavers.
     *
     * @return array<string, mixed>
     */
    public function people(): array
    {
        $today = $this->tenant->today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();

        $byDepartment = $this->employees()->current()->toBase()
            ->selectRaw('department_id, count(*) as total')
            ->groupBy('department_id')
            ->get();
        $departments = Department::query()->whereKey($byDepartment->pluck('department_id')->filter())->pluck('name', 'id');

        $byGender = $this->employees()->current()->toBase()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $brief = fn (Employee $employee, ?CarbonImmutable $date): array => [
            'id' => $employee->id,
            'name' => $employee->full_name,
            'code' => $employee->employee_code,
            'department' => $employee->department?->name,
            'date' => $date?->toDateString(),
        ];

        return [
            'headcount' => [
                'active' => $this->employees()->current()->count(),
                'past' => $this->employees()->past()->count(),
                'new_this_month' => $this->employees()->whereBetween('joining_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count(),
                'exited_this_month' => $this->employees()->past()->whereBetween('last_working_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count(),
                'on_probation' => $this->employees()->where('status', EmployeeStatus::Probation->value)->count(),
                'on_notice' => $this->employees()->where('status', EmployeeStatus::NoticePeriod->value)->count(),
            ],
            'by_department' => $byDepartment
                ->map(fn (object $row): array => [
                    'label' => $departments[$row->department_id] ?? 'No department',
                    'value' => (int) $row->total,
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'by_gender' => [
                ['label' => 'Male', 'value' => (int) ($byGender[Gender::Male->value] ?? 0)],
                ['label' => 'Female', 'value' => (int) ($byGender[Gender::Female->value] ?? 0)],
                ['label' => 'Other / Not specified', 'value' => (int) ($byGender[Gender::Other->value] ?? 0) + (int) ($byGender[''] ?? 0)],
            ],
            'new_joiners' => $this->employees()
                ->whereBetween('joining_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->with('department:id,name')
                ->orderByDesc('joining_date')
                ->limit(self::RANKING_SIZE)
                ->get()
                ->map(fn (Employee $employee): array => $brief($employee, $employee->joining_date))
                ->all(),
            'upcoming_probation' => $this->employees()
                ->where('status', EmployeeStatus::Probation->value)
                ->whereBetween('probation_end_date', [$today->toDateString(), $today->addDays(45)->toDateString()])
                ->with('department:id,name')
                ->orderBy('probation_end_date')
                ->limit(self::RANKING_SIZE)
                ->get()
                ->map(fn (Employee $employee): array => $brief($employee, $employee->probation_end_date))
                ->all(),
            'recent_exits' => $this->employees()
                ->past()
                ->with('department:id,name')
                ->orderByDesc('last_working_date')
                ->limit(self::RANKING_SIZE)
                ->get()
                ->map(fn (Employee $employee): array => $brief($employee, $employee->last_working_date))
                ->all(),
        ];
    }

    /**
     * Upcoming holidays and the working-day count of the current month.
     *
     * @return array<string, mixed>
     */
    public function calendar(): array
    {
        $today = $this->tenant->today();
        $monthEnd = $today->endOfMonth()->startOfDay();
        $weeklyOffs = [];

        for ($date = $today; count($weeklyOffs) < 4 && $date->lte($today->addDays(28)); $date = $date->addDay()) {
            if ($this->calendar->isWeeklyOff($date)) {
                $weeklyOffs[] = ['date' => $date->toDateString(), 'weekday' => $date->format('l')];
            }
        }

        return [
            'upcoming_holidays' => collect($this->calendar->holidaysBetween($today, $today->addDays(120)))
                ->map(fn (string $name, string $date): array => [
                    'name' => $name,
                    'date' => $date,
                    'weekday' => CarbonImmutable::parse($date)->format('l'),
                    'in_days' => (int) $today->diffInDays(CarbonImmutable::parse($date)),
                ])
                ->values()
                ->take(5)
                ->all(),
            'upcoming_weekly_offs' => $weeklyOffs,
            'month_label' => $today->format('F Y'),
            'working_days' => $this->calendar->workingDaysBetween($today->startOfMonth(), $monthEnd),
            'remaining_working_days' => $this->calendar->workingDaysBetween($today, $monthEnd),
        ];
    }

    /**
     * @return list<array{id: int, action: string, description: string|null, user: string|null, employee: string|null, created_at: string|null}>
     */
    public function activity(): array
    {
        return array_values(AuditLog::query()
            ->whereNotNull('employee_id')
            ->when($this->employeeId, fn (Builder $query) => $query->where('employee_id', $this->employeeId))
            ->with(['user:id,name', 'employee:id,first_name,last_name'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->name,
                'employee' => $log->employee?->full_name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * Measurable rankings. Each one says exactly what it measures; none of
     * them claims to identify a "best" employee.
     *
     * @return list<array{key: string, title: string, metric: string, format: string, rows: list<array<string, mixed>>}>
     */
    private function rankings(): array
    {
        $workingStatuses = [
            AttendanceStatus::Present->value, AttendanceStatus::Late->value, AttendanceStatus::ShortHours->value,
            AttendanceStatus::WorkFromHome->value, AttendanceStatus::HalfDay->value, AttendanceStatus::Absent->value,
            AttendanceStatus::PaidLeave->value, AttendanceStatus::UnpaidLeave->value, AttendanceStatus::Other->value,
        ];
        $attended = "sum(case when status in ('present', 'late', 'short_hours', 'wfh', 'paid_leave', 'other') then 1 when status = 'half_day' then 0.5 else 0 end)";
        $working = 'sum(case when status in ('.implode(', ', array_map(fn (string $s): string => "'{$s}'", $workingStatuses)).') then 1 else 0 end)';

        $definitions = [
            ['highest_attendance', 'Highest Attendance', 'Attended days as a share of recorded working days in the period', 'percent', "{$attended} / nullif({$working}, 0) * 100", "{$working} >= 5"],
            ['highest_overtime', 'Highest Overtime', 'Total overtime hours recorded in attendance in the period', 'minutes', 'sum(overtime_minutes)', 'sum(overtime_minutes) > 0'],
            ['most_hours', 'Most Working Hours', 'Total hours worked in the period', 'minutes', 'sum(worked_minutes)', 'sum(worked_minutes) > 0'],
            ['most_leave', 'Most Leave Taken', 'Paid and unpaid leave days in the period', 'days', "sum(case when status in ('paid_leave', 'unpaid_leave') then 1 else 0 end)", "sum(case when status in ('paid_leave', 'unpaid_leave') then 1 else 0 end) > 0"],
            ['most_absent', 'Most Absent Days', 'Days marked absent in the period', 'days', "sum(case when status = 'absent' then 1 else 0 end)", "sum(case when status = 'absent' then 1 else 0 end) > 0"],
        ];

        $rankings = [];

        foreach ($definitions as [$key, $title, $metric, $format, $expression, $having]) {
            $rows = $this->attendanceQuery($this->from, $this->to)->toBase()
                ->selectRaw("employee_id, {$expression} as value")
                ->groupBy('employee_id')
                ->havingRaw($having)
                ->orderByDesc('value')
                ->orderBy('employee_id')
                ->limit(self::RANKING_SIZE)
                ->get();

            $names = $this->employeeNames($rows->pluck('employee_id'));

            $rankings[] = [
                'key' => $key,
                'title' => $title,
                'metric' => $metric,
                'format' => $format,
                'rows' => array_values($rows
                    ->filter(fn (object $row): bool => isset($names[$row->employee_id]))
                    ->map(fn (object $row): array => [
                        'employee_id' => (int) $row->employee_id,
                        'name' => $names[$row->employee_id]['name'],
                        'code' => $names[$row->employee_id]['code'],
                        'value' => round((float) $row->value, 1),
                    ])
                    ->all()),
            ];
        }

        return $rankings;
    }

    /**
     * @return list<array{label: string, worked: int, short: int, overtime: int}>
     */
    private function hoursByDepartment(): array
    {
        $rows = $this->attendanceQuery($this->from, $this->to)->toBase()
            ->join('employees', 'employees.id', '=', 'attendances.employee_id')
            ->selectRaw('employees.department_id as department_id')
            ->selectRaw('coalesce(sum(attendances.worked_minutes), 0) as worked')
            ->selectRaw('coalesce(sum(attendances.short_minutes), 0) as short')
            ->selectRaw('coalesce(sum(attendances.overtime_minutes), 0) as overtime')
            ->groupBy('employees.department_id')
            ->get();

        $departments = Department::query()->whereKey($rows->pluck('department_id')->filter())->pluck('name', 'id');

        return array_values($rows
            ->map(fn (object $row): array => [
                'label' => (string) ($departments[$row->department_id] ?? 'No department'),
                'worked' => (int) $row->worked,
                'short' => (int) $row->short,
                'overtime' => (int) $row->overtime,
            ])
            ->sortByDesc('worked')
            ->all());
    }

    /**
     * @return list<array{month: string, label: string, gross: float, net: float, overtime: float, deductions: float}>
     */
    private function payrollTrend(): array
    {
        return $this->cached('payroll-trend', function (): array {
            $months = $this->trendMonths();

            $rows = PayrollItem::query()
                ->join('payrolls', 'payrolls.id', '=', 'payroll_items.payroll_id')
                ->when($this->hasEmployeeFilter(), fn (Builder $query) => $query->whereIn('payroll_items.employee_id', $this->employees()->select('employees.id')))
                ->where('payrolls.status', '!=', PayrollStatus::Draft->value)
                ->where('payrolls.period_start', '>=', $months[0]['start']->toDateString())
                ->toBase()
                ->selectRaw('payrolls.period_year as year, payrolls.period_month as month')
                ->selectRaw('sum(payroll_items.gross_salary) as gross')
                ->selectRaw('sum(payroll_items.net_payable - payroll_items.borrow_given) as net')
                ->selectRaw('sum(payroll_items.overtime_amount) as overtime')
                ->selectRaw('sum(payroll_items.attendance_deduction + payroll_items.unpaid_leave_deduction + payroll_items.short_hours_deduction + payroll_items.borrow_recovery + payroll_items.other_deductions) as deductions')
                ->groupBy('payrolls.period_year', 'payrolls.period_month')
                ->get()
                ->keyBy(fn (object $row): string => sprintf('%04d-%02d', $row->year, $row->month));

            return array_map(fn (array $month): array => [
                'month' => $month['key'],
                'label' => $month['label'],
                'gross' => (float) ($rows[$month['key']]->gross ?? 0),
                'net' => (float) ($rows[$month['key']]->net ?? 0),
                'overtime' => (float) ($rows[$month['key']]->overtime ?? 0),
                'deductions' => (float) ($rows[$month['key']]->deductions ?? 0),
            ], $months);
        });
    }

    /**
     * @return list<array{month: string, label: string, given: float, recovered: float}>
     */
    private function borrowTrend(): array
    {
        return $this->cached('borrow-trend', function (): array {
            $months = $this->trendMonths();

            $rows = $this->borrowTransactions()
                ->whereBetween('transaction_date', [$months[0]['start']->toDateString(), $months[count($months) - 1]['end']->toDateString()])
                ->toBase()
                ->selectRaw("date_format(transaction_date, '%Y-%m') as month, type, sum(amount) as total")
                ->groupBy('month', 'type')
                ->get()
                ->groupBy('month');

            $sum = fn (?Collection $group, array $types): float => (float) ($group?->whereIn('type', $types)->sum('total') ?? 0);

            return array_map(fn (array $month): array => [
                'month' => $month['key'],
                'label' => $month['label'],
                'given' => $sum($rows->get($month['key']), [BorrowTransactionType::Opening->value, BorrowTransactionType::Disbursement->value]),
                'recovered' => $sum($rows->get($month['key']), [BorrowTransactionType::Recovery->value, BorrowTransactionType::Settlement->value]),
            ], $months);
        });
    }

    /**
     * The last 12 calendar months, oldest first.
     *
     * @return list<array{key: string, label: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function trendMonths(): array
    {
        $current = $this->tenant->today()->startOfMonth();
        $months = [];

        for ($offset = self::TREND_MONTHS - 1; $offset >= 0; $offset--) {
            $month = $current->subMonths($offset);

            $months[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M y'),
                'start' => $month,
                'end' => $month->endOfMonth()->startOfDay(),
            ];
        }

        return $months;
    }

    /**
     * Employees matching the dashboard's department, employee and status filters.
     *
     * @return Builder<Employee>
     */
    private function employees(): Builder
    {
        return Employee::query()
            ->when($this->departmentId, fn (Builder $query) => $query->where('department_id', $this->departmentId))
            ->when($this->employeeId, fn (Builder $query) => $query->whereKey($this->employeeId))
            ->when($this->employeeStatus === 'current', fn (Builder $query) => $query->whereIn('status', EmployeeStatus::currentValues()))
            ->when($this->employeeStatus !== null && $this->employeeStatus !== 'current', fn (Builder $query) => $query->where('status', $this->employeeStatus));
    }

    private function hasEmployeeFilter(): bool
    {
        return $this->departmentId !== null || $this->employeeId !== null || $this->employeeStatus !== null;
    }

    /**
     * @return Builder<Attendance>
     */
    private function attendanceQuery(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Attendance::query()
            ->whereBetween('attendances.date', [$from->toDateString(), $to->toDateString()])
            ->when($this->hasEmployeeFilter(), fn (Builder $query) => $query->whereIn('attendances.employee_id', $this->employees()->select('employees.id')));
    }

    /**
     * @return Builder<EmployeeBorrow>
     */
    private function borrows(): Builder
    {
        return EmployeeBorrow::query()
            ->when($this->hasEmployeeFilter(), fn (Builder $query) => $query->whereIn('employee_id', $this->employees()->select('employees.id')));
    }

    /**
     * @return Builder<BorrowTransaction>
     */
    private function borrowTransactions(): Builder
    {
        return BorrowTransaction::query()
            ->when($this->hasEmployeeFilter(), fn (Builder $query) => $query->whereIn('employee_id', $this->employees()->select('employees.id')));
    }

    /**
     * @return Builder<PayrollItem>
     */
    private function payrollItems(Payroll $payroll): Builder
    {
        return PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->when($this->hasEmployeeFilter(), fn (Builder $query) => $query->whereIn('employee_id', $this->employees()->select('employees.id')));
    }

    /**
     * @param  literal-string  $expression  a SQL expression over payroll item columns
     */
    private function payrollItemSum(Payroll $payroll, string $expression): float
    {
        return round((float) $this->payrollItems($payroll)->toBase()->selectRaw("coalesce(sum({$expression}), 0) as total")->value('total'), 2);
    }

    /**
     * The payroll for the selected period, or the latest calculated payroll.
     */
    private function currentPayroll(): ?Payroll
    {
        $calculated = fn (): Builder => Payroll::query()->where('status', '!=', PayrollStatus::Draft->value);

        return $calculated()
            ->where('period_start', '<=', $this->to->toDateString())
            ->where('period_end', '>=', $this->from->toDateString())
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first()
            ?? $calculated()->orderByDesc('period_year')->orderByDesc('period_month')->first();
    }

    private function payrollBefore(Payroll $payroll): ?Payroll
    {
        return Payroll::query()
            ->where('status', '!=', PayrollStatus::Draft->value)
            ->where('period_start', '<', $payroll->period_start->toDateString())
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();
    }

    private function overtimeAmount(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return round((float) Overtime::query()
            ->whereIn('status', [OvertimeStatus::Approved->value, OvertimeStatus::Paid->value])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($this->hasEmployeeFilter(), fn (Builder $query) => $query->whereIn('employee_id', $this->employees()->select('employees.id')))
            ->sum('amount'), 2);
    }

    /**
     * The period of the same length immediately before the selected one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousRange(): array
    {
        if ($this->from->day === 1 && $this->to->toDateString() === $this->from->endOfMonth()->toDateString()) {
            $previous = $this->from->subMonthNoOverflow();

            return [$previous, $previous->endOfMonth()->startOfDay()];
        }

        $days = (int) $this->from->diffInDays($this->to) + 1;

        return [$this->from->subDays($days), $this->from->subDay()];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->attendanceQuery($from, $to)->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function presentCount(array $counts): int
    {
        return ($counts[AttendanceStatus::Present->value] ?? 0)
            + ($counts[AttendanceStatus::Late->value] ?? 0)
            + ($counts[AttendanceStatus::ShortHours->value] ?? 0)
            + ($counts[AttendanceStatus::WorkFromHome->value] ?? 0)
            + ($counts[AttendanceStatus::HalfDay->value] ?? 0);
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return array<int, array{name: string, code: string}>
     */
    private function employeeNames(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return Employee::query()
            ->whereKey($ids->all())
            ->get(['id', 'first_name', 'last_name', 'employee_code'])
            ->mapWithKeys(fn (Employee $employee): array => [
                $employee->id => ['name' => $employee->full_name, 'code' => $employee->employee_code],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array{value: float, direction: string, label: string}|null  $delta
     * @param  list<float>|null  $trend  monthly values, oldest first, for a sparkline
     * @param  array{value: float, max: float, label: string, tone: string}|null  $progress
     * @return array<string, mixed>
     */
    private function kpi(
        string $key,
        string $label,
        int|float $value,
        string $format,
        string $hint,
        ?array $delta = null,
        string $tone = 'neutral',
        ?string $route = null,
        array $query = [],
        ?array $trend = null,
        ?array $progress = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'hint' => $hint,
            'delta' => $delta,
            'tone' => $tone,
            'href' => $route !== null ? route($route, $query, false) : null,
            'trend' => $trend,
            'progress' => $progress,
        ];
    }

    /**
     * Monthly values for a sparkline, from the first month that has any. Fewer
     * than two such months make no line, so there is no sparkline then.
     *
     * @param  list<float>  $values
     * @return list<float>|null
     */
    private function sparkline(array $values): ?array
    {
        while ($values !== [] && $values[0] == 0.0) {
            array_shift($values);
        }

        return count($values) >= 2 ? $values : null;
    }

    /**
     * A part of a whole for a progress bar; nothing when the whole is empty.
     *
     * @return array{value: float, max: float, label: string, tone: string}|null
     */
    private function progress(int|float $value, int|float $max, string $label, string $tone): ?array
    {
        if ($max <= 0) {
            return null;
        }

        return [
            'value' => (float) max(0, min($value, $max)),
            'max' => (float) $max,
            'label' => $label,
            'tone' => $tone,
        ];
    }

    /**
     * Percentage change against a comparison value, when there is one.
     *
     * @return array{value: float, direction: string, label: string}|null
     */
    private function delta(float $current, ?float $previous, string $label): ?array
    {
        if ($previous === null || $previous == 0.0) {
            return null;
        }

        $change = round(($current - $previous) / abs($previous) * 100, 1);

        return [
            'value' => abs($change),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'label' => $label,
        ];
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    private function cached(string $name, Closure $callback): mixed
    {
        $key = sprintf(
            'company:%d:dashboard:v2:%s:%s',
            $this->tenant->require()->id,
            $name,
            md5(implode('|', [(string) $this->departmentId, (string) $this->employeeId, (string) $this->employeeStatus, $this->tenant->today()->format('Y-m')])),
        );

        return Cache::remember($key, self::TREND_CACHE_SECONDS, $callback);
    }
}
