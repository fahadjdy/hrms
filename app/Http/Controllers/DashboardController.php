<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Services\AttendanceCalculationService;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The BI dashboard. KPIs render with the page; the heavier widget groups
     * are deferred and load right after, each group in its own request.
     */
    public function __invoke(Request $request, DashboardService $dashboard, AttendanceCalculationService $attendance): Response
    {
        $validated = $request->validate([
            'preset' => ['nullable', Rule::in(['current_month', 'previous_month', 'custom'])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer'],
            'employee_status' => ['nullable', Rule::in(['current', ...EmployeeStatus::values()])],
        ]);

        $today = $this->tenant()->today();
        $preset = $validated['preset'] ?? 'current_month';

        [$from, $to] = match ($preset) {
            'previous_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'custom' => [CarbonImmutable::parse($validated['from']), CarbonImmutable::parse($validated['to'])],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };

        $filters = [
            'preset' => $preset,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'department_id' => isset($validated['department_id']) ? (int) $validated['department_id'] : null,
            'employee_id' => isset($validated['employee_id']) ? (int) $validated['employee_id'] : null,
            'employee_status' => $validated['employee_status'] ?? null,
        ];

        // Today's weekly off / holiday (and present, in automatic mode) records.
        $attendance->generateForDate($today);

        $dashboard->filter($filters);

        return Inertia::render('Dashboard', [
            'filters' => $filters,
            'today' => $today->toDateString(),
            'options' => [
                'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
                'employees' => Employee::query()
                    ->orderBy('first_name')
                    ->limit(1000)
                    ->get(['id', 'first_name', 'last_name', 'employee_code'])
                    ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => "{$employee->full_name} ({$employee->employee_code})"]),
                'statuses' => [['value' => 'current', 'label' => 'All current employees'], ...EmployeeStatus::options()],
            ],
            'kpis' => $dashboard->kpis(),
            'attendance' => Inertia::defer(fn () => $dashboard->attendance(), 'attendance'),
            'hoursTrend' => Inertia::defer(fn () => $dashboard->hoursTrend(), 'attendance'),
            'payroll' => Inertia::defer(fn () => $dashboard->payroll(), 'finance'),
            'borrow' => Inertia::defer(fn () => $dashboard->borrow(), 'finance'),
            'people' => Inertia::defer(fn () => $dashboard->people(), 'people'),
            'calendar' => Inertia::defer(fn () => $dashboard->calendar(), 'people'),
            'activity' => Inertia::defer(fn () => $dashboard->activity(), 'people'),
        ]);
    }
}
