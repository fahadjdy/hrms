<?php

namespace App\Services;

use App\Enums\Gender;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\WorkShift;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves which work shift applies to an employee on a date, and turns a
 * check-in / check-out pair into worked, short, overtime and late minutes.
 */
class WorkingHoursCalculationService
{
    public const int DEFAULT_REQUIRED_MINUTES = 480;

    /** @var array<int, Collection<int, WorkShift>> */
    private array $shifts = [];

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * The shift an employee works on a date.
     *
     * Priority: employee-specific assignment, then the company's gender-based
     * default, then the company default shift.
     */
    public function shiftFor(Employee $employee, CarbonImmutable $date): ?WorkShift
    {
        $assignment = $this->assignmentFor($employee, $date);

        if ($assignment !== null) {
            return $this->shift($assignment->work_shift_id);
        }

        $settings = $this->tenant->settings();

        $genderShiftId = match ($employee->gender) {
            Gender::Male => $settings->male_work_shift_id,
            Gender::Female => $settings->female_work_shift_id,
            Gender::Other => $settings->other_work_shift_id,
            null => null,
        };

        return $this->shift($genderShiftId) ?? $this->shift($settings->default_work_shift_id);
    }

    /**
     * Where the employee's shift on a date comes from: 'employee', 'gender' or 'company'.
     */
    public function shiftSourceFor(Employee $employee, CarbonImmutable $date): ?string
    {
        if ($this->assignmentFor($employee, $date) !== null) {
            return 'employee';
        }

        $settings = $this->tenant->settings();

        $genderShiftId = match ($employee->gender) {
            Gender::Male => $settings->male_work_shift_id,
            Gender::Female => $settings->female_work_shift_id,
            Gender::Other => $settings->other_work_shift_id,
            null => null,
        };

        if ($this->shift($genderShiftId) !== null) {
            return 'gender';
        }

        return $this->shift($settings->default_work_shift_id) !== null ? 'company' : null;
    }

    public function requiredMinutesFor(Employee $employee, CarbonImmutable $date): int
    {
        return $this->shiftFor($employee, $date)->required_minutes ?? self::DEFAULT_REQUIRED_MINUTES;
    }

    /**
     * Calculate working time from a check-in / check-out pair.
     *
     * @return array{required_minutes: int, worked_minutes: int, short_minutes: int, overtime_minutes: int, late_minutes: int}
     */
    public function calculate(
        ?WorkShift $shift,
        string $checkIn,
        string $checkOut,
        ?int $requiredMinutes = null,
        int $graceMinutes = 0,
        int $toleranceMinutes = 0,
    ): array {
        $required = $requiredMinutes ?? $shift->required_minutes ?? self::DEFAULT_REQUIRED_MINUTES;
        $in = WorkShift::timeToMinutes($checkIn);
        $out = WorkShift::timeToMinutes($checkOut);
        $span = $out >= $in ? $out - $in : $out + 1440 - $in;

        if ($shift?->hasBreakTiming()) {
            // A fixed break is deducted only for the part of it the employee
            // was actually at work for.
            $worked = max(0, $span - $shift->breakOverlapMinutes($checkIn, $checkOut));
        } else {
            // The unpaid break only applies once more than half of the shift was
            // spent at work, so a short half-day visit is not reduced by it.
            $break = $shift->break_minutes ?? 0;
            $shiftSpan = $shift?->spanMinutes() ?? $required;
            $worked = $span > intdiv($shiftSpan, 2) ? max(0, $span - $break) : $span;
        }

        $short = max(0, $required - $worked);

        if ($short <= $toleranceMinutes) {
            $short = 0;
        }

        $late = 0;

        if ($shift !== null) {
            $lateBy = $in - WorkShift::timeToMinutes($shift->start_time);
            $late = $lateBy > $graceMinutes ? $lateBy : 0;
        }

        return [
            'required_minutes' => $required,
            'worked_minutes' => $worked,
            'short_minutes' => $short,
            'overtime_minutes' => max(0, $worked - $required),
            'late_minutes' => $late,
        ];
    }

    /**
     * Forget cached shifts after a shift changes.
     */
    public function flush(): void
    {
        $this->shifts = [];
    }

    private function assignmentFor(Employee $employee, CarbonImmutable $date): ?EmployeeShiftAssignment
    {
        if (! $employee->relationLoaded('shiftAssignments')) {
            $employee->load('shiftAssignments');
        }

        $day = $date->toDateString();

        return $employee->shiftAssignments
            ->filter(fn (EmployeeShiftAssignment $assignment): bool => $assignment->effective_from->toDateString() <= $day
                && ($assignment->effective_to === null || $assignment->effective_to->toDateString() >= $day))
            ->sortByDesc(fn (EmployeeShiftAssignment $assignment): string => $assignment->effective_from->toDateString().'-'.str_pad((string) $assignment->id, 12, '0', STR_PAD_LEFT))
            ->first();
    }

    private function shift(?int $id): ?WorkShift
    {
        if ($id === null) {
            return null;
        }

        $companyId = $this->tenant->require()->id;
        $this->shifts[$companyId] ??= WorkShift::query()->get()->keyBy('id');

        return $this->shifts[$companyId]->get($id);
    }
}
