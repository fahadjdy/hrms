<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceCalculationService;
use App\Services\WorkingHoursCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeAttendanceController extends Controller
{
    /**
     * The employee's attendance calendar for one month, with its summary.
     */
    public function show(
        Request $request,
        Employee $employee,
        AttendanceCalculationService $attendance,
        WorkingHoursCalculationService $hours,
    ): Response {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $today = $this->tenant()->today();
        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : $today->startOfMonth();
        $start = $month->startOfMonth();
        $end = $month->endOfMonth()->startOfDay();

        $employee->load(['department:id,name', 'designation:id,name', 'shiftAssignments']);
        $days = $attendance->resolveDays($employee, $start, $end);
        $shift = $hours->shiftFor($employee, $end->min($today)->max($start));

        $logs = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('user:id,name')
            ->latest('id')
            ->limit(200)
            ->get()
            ->groupBy(fn (AttendanceLog $log): string => $log->date->toDateString())
            ->map(fn ($group) => $group->map(fn (AttendanceLog $log): array => [
                'id' => $log->id,
                'user' => $log->user?->name,
                'old' => $log->old_values,
                'new' => $log->new_values,
                'reason' => $log->reason,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values());

        return Inertia::render('attendance/EmployeeCalendar', [
            'employee' => [
                ...$employee->toBrief(),
                'joining_date' => $employee->joining_date->toDateString(),
                'last_working_date' => $employee->last_working_date?->toDateString(),
            ],
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->format('F Y'),
            'previousMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
            'today' => $today->toDateString(),
            'days' => $days,
            'summary' => $attendance->summarize($days),
            'logs' => $logs,
            'shift' => $shift === null ? null : [
                'name' => $shift->name,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'required_minutes' => $shift->required_minutes,
                'source' => $hours->shiftSourceFor($employee, $end->min($today)->max($start)),
            ],
            'statuses' => AttendanceController::statusOptions(),
        ]);
    }

    /**
     * Mark or correct one day. Every change is logged and audited.
     */
    public function update(Request $request, Employee $employee, string $date, AttendanceCalculationService $attendance): RedirectResponse
    {
        $day = $this->editableDate($employee, $date);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in' => ['nullable', 'date_format:H:i', 'required_with:check_out'],
            'check_out' => ['nullable', 'date_format:H:i', 'required_with:check_in'],
            'worked_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $attendance->record($employee, $day, $validated, $request->user());

        $this->toast('Attendance saved.');

        return back();
    }

    /**
     * Clear a stored record so the day goes back to its derived status.
     */
    public function destroy(Request $request, Employee $employee, string $date, AttendanceCalculationService $attendance): RedirectResponse
    {
        $attendance->clear($employee, $this->editableDate($employee, $date), $request->user());

        $this->toast('Attendance record cleared.');

        return back();
    }

    private function editableDate(Employee $employee, string $date): CarbonImmutable
    {
        $day = CarbonImmutable::parse($date);

        if ($day->gt($this->tenant()->today())) {
            throw ValidationException::withMessages(['status' => 'Attendance cannot be marked for a future date.']);
        }

        if (! $employee->isEmployedOn($day)) {
            throw ValidationException::withMessages([
                'status' => 'This date is outside the employee\'s employment (before joining or after the last working day).',
            ]);
        }

        return $day;
    }
}
