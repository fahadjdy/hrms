<?php

namespace App\Http\Controllers;

use App\Models\EmployeeShiftAssignment;
use App\Models\WorkShift;
use App\Services\AuditLogger;
use App\Services\WorkingHoursCalculationService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkShiftController extends Controller
{
    public function index(): Response
    {
        $settings = $this->tenant()->settings();
        $today = $this->tenant()->today()->toDateString();

        $assigned = EmployeeShiftAssignment::query()
            ->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->selectRaw('work_shift_id, count(distinct employee_id) as total')
            ->groupBy('work_shift_id')
            ->toBase()
            ->pluck('total', 'work_shift_id');

        return Inertia::render('attendance/WorkShifts', [
            'shifts' => WorkShift::query()->orderBy('name')->get()->map(fn (WorkShift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'break_start' => $shift->break_start === null ? null : substr($shift->break_start, 0, 5),
                'break_end' => $shift->break_end === null ? null : substr($shift->break_end, 0, 5),
                'required_minutes' => $shift->required_minutes,
                'break_minutes' => $shift->break_minutes,
                'is_active' => $shift->is_active,
                'assigned_employees' => (int) ($assigned[$shift->id] ?? 0),
                'default_for' => array_values(array_filter([
                    $settings->default_work_shift_id === $shift->id ? 'Company default' : null,
                    $settings->male_work_shift_id === $shift->id ? 'Male staff' : null,
                    $settings->female_work_shift_id === $shift->id ? 'Female staff' : null,
                    $settings->other_work_shift_id === $shift->id ? 'Other staff' : null,
                ])),
            ]),
        ]);
    }

    public function store(Request $request, WorkingHoursCalculationService $hours, AuditLogger $audit): RedirectResponse
    {
        $shift = WorkShift::query()->create($this->validated($request));
        $hours->flush();

        $audit->log('work_shift.created', $shift, null, $shift->only(['name', 'start_time', 'end_time', 'break_start', 'break_end', 'required_minutes']), "Work shift {$shift->name} created");
        $this->toast('Work shift added.');

        return back();
    }

    public function update(Request $request, WorkShift $workShift, WorkingHoursCalculationService $hours, AuditLogger $audit): RedirectResponse
    {
        $original = $workShift->getAttributes();
        $workShift->update($this->validated($request, $workShift));
        $hours->flush();

        [$old, $new] = $audit->diff($workShift, $original);

        if ($new !== []) {
            $audit->log('work_shift.updated', $workShift, $old, $new, "Work shift {$workShift->name} updated");
        }

        $this->toast('Work shift updated.');

        return back();
    }

    public function destroy(WorkShift $workShift, WorkingHoursCalculationService $hours, AuditLogger $audit): RedirectResponse
    {
        $settings = $this->tenant()->settings();

        $isDefault = in_array($workShift->id, [
            $settings->default_work_shift_id,
            $settings->male_work_shift_id,
            $settings->female_work_shift_id,
            $settings->other_work_shift_id,
        ], true);

        if ($isDefault || $workShift->assignments()->exists()) {
            throw ValidationException::withMessages([
                'work_shift' => 'This shift is a default shift or is assigned to employees. Reassign it first, or mark it inactive.',
            ]);
        }

        $audit->log('work_shift.deleted', $workShift, $workShift->only(['name', 'start_time', 'end_time', 'break_start', 'break_end', 'required_minutes']), null, "Work shift {$workShift->name} deleted");
        $workShift->delete();
        $hours->flush();

        $this->toast('Work shift deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WorkShift $shift = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', TenantRule::unique('work_shifts', 'name')->ignore($shift?->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'break_start' => ['nullable', 'required_with:break_end', 'date_format:H:i'],
            'break_end' => ['nullable', 'required_with:break_start', 'date_format:H:i', 'different:break_start'],
            'required_minutes' => ['required', 'integer', 'min:30', 'max:1440'],
            'is_active' => ['boolean'],
        ], [
            'required_minutes.min' => 'The required hours must be at least 30 minutes.',
            'break_start.required_with' => 'Enter when the break starts, or clear both break times.',
            'break_end.required_with' => 'Enter when the break ends, or clear both break times.',
            'break_end.different' => 'The break must end after it starts.',
        ]);

        $start = WorkShift::timeToMinutes($validated['start_time']);
        $span = WorkShift::minutesBetween($start, WorkShift::timeToMinutes($validated['end_time']));
        $breakMinutes = null;

        if (isset($validated['break_start'], $validated['break_end'])) {
            $breakStart = WorkShift::timeToMinutes($validated['break_start']);
            $breakMinutes = WorkShift::minutesBetween($breakStart, WorkShift::timeToMinutes($validated['break_end']));

            // Measured from the shift start, the whole break must end before the shift does.
            if (WorkShift::minutesBetween($start, $breakStart) + $breakMinutes >= $span) {
                throw ValidationException::withMessages([
                    'break_start' => 'The break must fall between the start and end of the shift.',
                ]);
            }
        }

        if ($validated['required_minutes'] > $span - ($breakMinutes ?? 0)) {
            throw ValidationException::withMessages([
                'required_minutes' => $breakMinutes === null
                    ? 'The required hours cannot be longer than the time between the start and end of the shift.'
                    : 'The required hours cannot be longer than the shift less its break.',
            ]);
        }

        // Without fixed break times, whatever part of the shift is not
        // required working time is the break.
        $validated['break_minutes'] = $breakMinutes ?? $span - $validated['required_minutes'];

        // Times are stored with seconds; matching that format keeps an unchanged
        // time from being saved, and audited, as a change.
        foreach (['start_time', 'end_time', 'break_start', 'break_end'] as $key) {
            $validated[$key] = isset($validated[$key]) ? $validated[$key].':00' : null;
        }

        return $validated;
    }
}
