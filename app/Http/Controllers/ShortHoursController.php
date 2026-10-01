<?php

namespace App\Http\Controllers;

use App\Enums\ShortHoursMode;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceCalculationService;
use App\Services\SalaryCalculationService;
use App\Services\ShortHoursCalculationService;
use App\Services\WorkingCalendarService;
use App\Services\WorkingHoursCalculationService;
use App\Support\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShortHoursController extends Controller
{
    /**
     * Short working hours per employee for a payroll period: required and
     * actual hours, the calculated amount, the admin-adjusted amount and the
     * final deduction.
     */
    public function index(
        Request $request,
        AttendanceCalculationService $attendance,
        SalaryCalculationService $salaries,
        ShortHoursCalculationService $shortHours,
        WorkingCalendarService $calendar,
        WorkingHoursCalculationService $hours,
    ): Response {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $period = $this->period($validated['month'] ?? null);
        $workingDays = $calendar->workingDaysBetween($period->start, $period->end);

        // Only employees with at least one short-hours record in the period are listed.
        $employees = Employee::query()
            ->search($validated['search'] ?? null)
            ->whereIn('id', Attendance::query()
                ->whereBetween('date', [$period->start->toDateString(), $period->end->toDateString()])
                ->where('short_minutes', '>', 0)
                ->select('employee_id'))
            ->with(['department:id,name', 'designation:id,name', 'shiftAssignments', 'salaryRevisions.components'])
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Employee $employee) use ($attendance, $salaries, $shortHours, $hours, $period, $workingDays): array {
                $summary = $attendance->summarize($attendance->resolveDays($employee, $period->start, $period->end));
                $salary = $salaries->forPeriod($employee, $period, $workingDays, $hours->requiredMinutesFor($employee, $period->end));

                return [
                    ...$employee->toBrief(),
                    'short_hours' => $shortHours->forPeriod($employee, $period, $summary, $salary['hourly_rate']),
                ];
            });

        $settings = $this->tenant()->settings();

        return Inertia::render('finance/ShortHours', [
            'employees' => $employees,
            'period' => $period->toArray(),
            'month' => sprintf('%04d-%02d', $period->year, $period->month),
            'filters' => ['search' => $validated['search'] ?? ''],
            'rule' => [
                'mode' => $settings->short_hours_mode->value,
                'mode_label' => $settings->short_hours_mode->label(),
                'rate_type' => $settings->short_hours_rate_type,
                'fixed_rate' => $settings->short_hours_fixed_rate,
                'tolerance_minutes' => $settings->short_hours_tolerance_minutes,
                'modes' => ShortHoursMode::options(),
            ],
        ]);
    }

    /**
     * Set the admin-adjusted short-hours deduction for an employee and period.
     */
    public function update(Request $request, Employee $employee, ShortHoursCalculationService $shortHours): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $shortHours->adjust($employee, $this->period($validated['month']), (float) $validated['amount'], $validated['reason'], $request->user());

        $this->toast('Short-hours adjustment saved.');

        return back();
    }

    /**
     * Remove the admin adjustment so the company rule applies again.
     */
    public function destroy(Request $request, Employee $employee, ShortHoursCalculationService $shortHours): RedirectResponse
    {
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $shortHours->resetAdjustment($employee, $this->period($validated['month']));

        $this->toast('Short-hours adjustment removed.');

        return back();
    }

    private function period(?string $month): PayrollPeriod
    {
        $startDay = $this->tenant()->settings()->payroll_period_start_day;

        if ($month === null) {
            return PayrollPeriod::containing($this->tenant()->today(), $startDay);
        }

        [$year, $monthNumber] = array_map(intval(...), explode('-', $month));

        return PayrollPeriod::forMonth($year, $monthNumber, $startDay);
    }
}
