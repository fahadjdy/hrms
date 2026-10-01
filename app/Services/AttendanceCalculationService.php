<?php

namespace App\Services;

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Attendance for an employee over a date range.
 *
 * Stored records are the source of truth. Days without a record are derived
 * from the company calendar (weekly off, holiday) and the attendance mode, so
 * the calendar, the summary and payroll always agree on every single day.
 */
class AttendanceCalculationService
{
    /** A stored attendance record. */
    public const string STATE_RECORDED = 'recorded';

    /** No record; the status follows from the calendar or automatic mode. */
    public const string STATE_DERIVED = 'derived';

    /** A past working day nobody has marked yet (manual mode). */
    public const string STATE_UNMARKED = 'unmarked';

    /** A day in the future. */
    public const string STATE_UPCOMING = 'upcoming';

    /** Before joining or after the last working date. */
    public const string STATE_NOT_EMPLOYED = 'not_employed';

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly WorkingCalendarService $calendar,
        private readonly WorkingHoursCalculationService $hours,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Resolve every day in the range to a status and working-hours figures.
     *
     * @return list<array<string, mixed>>
     */
    public function resolveDays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (Attendance $attendance): string => $attendance->date->toDateString());

        $days = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $record = $records->get($date->toDateString());

            $days[] = $record !== null
                ? $this->recordedDay($record)
                : $this->derivedDay($employee, $date);
        }

        return $days;
    }

    /**
     * Resolve one date for many employees with a single attendance query.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, mixed>> keyed by employee id
     */
    public function resolveDate(Collection $employees, CarbonImmutable $date): array
    {
        $records = Attendance::query()
            ->whereIn('employee_id', $employees->modelKeys())
            ->where('date', $date->toDateString())
            ->get()
            ->keyBy('employee_id');

        $days = [];

        foreach ($employees as $employee) {
            $record = $records->get($employee->id);

            $days[$employee->id] = $record !== null
                ? $this->recordedDay($record)
                : $this->derivedDay($employee, $date);
        }

        return $days;
    }

    /**
     * Totals for a list of resolved days.
     *
     * @param  list<array<string, mixed>>  $days
     * @return array<string, int|float>
     */
    public function summarize(array $days): array
    {
        $summary = [
            'calendar_days' => count($days),
            'employed_days' => 0,
            'working_days' => 0,
            'elapsed_working_days' => 0,
            'present' => 0,
            'absent' => 0,
            'half_day' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
            'weekly_off' => 0,
            'holidays' => 0,
            'wfh' => 0,
            'late' => 0,
            'short_hours_days' => 0,
            'other' => 0,
            'unmarked' => 0,
            'upcoming' => 0,
            'not_employed_days' => 0,
            'not_employed_working_days' => 0,
            'required_minutes' => 0,
            'worked_minutes' => 0,
            'short_minutes' => 0,
            'overtime_minutes' => 0,
            'attendance_rate' => 0.0,
        ];

        foreach ($days as $day) {
            if ($day['state'] === self::STATE_NOT_EMPLOYED) {
                $summary['not_employed_days']++;

                if ($day['is_working_day']) {
                    $summary['not_employed_working_days']++;
                }

                continue;
            }

            $summary['employed_days']++;

            if ($day['is_working_day']) {
                $summary['working_days']++;

                if ($day['state'] !== self::STATE_UPCOMING) {
                    $summary['elapsed_working_days']++;
                }
            }

            $summary['required_minutes'] += $day['required_minutes'];
            $summary['worked_minutes'] += $day['worked_minutes'];
            $summary['short_minutes'] += $day['short_minutes'];
            $summary['overtime_minutes'] += $day['overtime_minutes'];

            if ($day['state'] === self::STATE_UNMARKED) {
                $summary['unmarked']++;

                continue;
            }

            if ($day['state'] === self::STATE_UPCOMING && $day['status'] === null) {
                $summary['upcoming']++;

                continue;
            }

            $status = AttendanceStatus::from($day['status']);

            if ($status->isWorkedDay()) {
                $summary['present']++;
            }

            match ($status) {
                AttendanceStatus::Absent => $summary['absent']++,
                AttendanceStatus::HalfDay => $summary['half_day']++,
                AttendanceStatus::PaidLeave => $summary['paid_leave']++,
                AttendanceStatus::UnpaidLeave => $summary['unpaid_leave']++,
                AttendanceStatus::WeeklyOff => $summary['weekly_off']++,
                AttendanceStatus::Holiday => $summary['holidays']++,
                AttendanceStatus::WorkFromHome => $summary['wfh']++,
                AttendanceStatus::Late => $summary['late']++,
                AttendanceStatus::ShortHours => $summary['short_hours_days']++,
                AttendanceStatus::Other => $summary['other']++,
                AttendanceStatus::Present => null,
            };
        }

        // Attended days over the working days that have already passed.
        // Paid leave counts as attended; half days count as half.
        $attended = $summary['present'] + $summary['paid_leave'] + $summary['other'] + ($summary['half_day'] * 0.5);

        $summary['attendance_rate'] = $summary['elapsed_working_days'] > 0
            ? round(min(100, $attended / $summary['elapsed_working_days'] * 100), 1)
            : 0.0;

        return $summary;
    }

    /**
     * Create or update the attendance record for one day.
     *
     * @param  array{status: string, check_in?: string|null, check_out?: string|null, worked_minutes?: int|null, notes?: string|null, reason?: string|null}  $input
     */
    public function record(Employee $employee, CarbonImmutable $date, array $input, ?User $user = null): Attendance
    {
        $values = $this->valuesFor($employee, $date, $input);

        return DB::transaction(function () use ($employee, $date, $input, $values, $user): Attendance {
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->where('date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            $old = $attendance !== null ? $this->loggable($attendance) : null;

            $attendance ??= new Attendance(['employee_id' => $employee->id, 'date' => $date->toDateString()]);
            $attendance->fill([
                ...$values,
                'notes' => $input['notes'] ?? null,
                'source' => Attendance::SOURCE_MANUAL,
                'modified_by' => $user?->id,
            ]);
            $attendance->save();

            $new = $this->loggable($attendance);

            if ($old !== $new) {
                AttendanceLog::query()->create([
                    'attendance_id' => $attendance->id,
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'user_id' => $user?->id,
                    'old_values' => $old,
                    'new_values' => $new,
                    'reason' => $input['reason'] ?? null,
                ]);

                $this->audit->log(
                    $old === null ? 'attendance.marked' : 'attendance.modified',
                    $attendance,
                    $old,
                    $new,
                    "Attendance for {$employee->full_name} on {$date->toDateString()}",
                    $employee->id,
                );
            }

            return $attendance;
        });
    }

    /**
     * Remove a stored record so the day falls back to its derived status.
     */
    public function clear(Employee $employee, CarbonImmutable $date, ?User $user = null): void
    {
        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('date', $date->toDateString())
            ->first();

        if ($attendance === null) {
            return;
        }

        DB::transaction(function () use ($attendance, $employee, $date, $user): void {
            $old = $this->loggable($attendance);

            AttendanceLog::query()->create([
                'attendance_id' => null,
                'employee_id' => $employee->id,
                'date' => $date->toDateString(),
                'user_id' => $user?->id,
                'old_values' => $old,
                'new_values' => null,
                'reason' => 'Record cleared',
            ]);

            $this->audit->log(
                'attendance.cleared',
                $attendance,
                $old,
                null,
                "Attendance cleared for {$employee->full_name} on {$date->toDateString()}",
                $employee->id,
            );

            $attendance->delete();
        });
    }

    /**
     * Store the days that can be decided without the admin: weekly offs,
     * holidays and, in automatic mode, present days. Existing records are
     * never overwritten.
     *
     * @return int number of records created
     */
    public function generateForDate(CarbonImmutable $date): int
    {
        if ($date->gt($this->tenant->today())) {
            return 0;
        }

        $automatic = $this->tenant->settings()->attendance_mode === AttendanceMode::Automatic;

        if (! $automatic && $this->calendar->isWorkingDay($date)) {
            return 0;
        }

        $companyId = $this->tenant->require()->id;
        $created = 0;
        $now = now();

        Employee::query()
            ->current()
            ->where('joining_date', '<=', $date->toDateString())
            ->whereDoesntHave('attendances', fn ($query) => $query->where('date', $date->toDateString()))
            ->with('shiftAssignments')
            ->chunkById(500, function (Collection $employees) use ($date, $companyId, $now, &$created): void {
                $rows = [];

                foreach ($employees as $employee) {
                    $day = $this->derivedDay($employee, $date);

                    if ($day['status'] === null) {
                        continue;
                    }

                    $rows[] = [
                        'company_id' => $companyId,
                        'employee_id' => $employee->id,
                        'date' => $date->toDateString(),
                        'status' => $day['status'],
                        'check_in' => $day['check_in'],
                        'check_out' => $day['check_out'],
                        'required_minutes' => $day['required_minutes'],
                        'worked_minutes' => $day['worked_minutes'],
                        'short_minutes' => 0,
                        'overtime_minutes' => 0,
                        'late_minutes' => 0,
                        'work_shift_id' => $day['work_shift_id'],
                        'source' => Attendance::SOURCE_AUTOMATIC,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    Attendance::query()->insertOrIgnore($rows);
                    $created += count($rows);
                }
            });

        return $created;
    }

    /**
     * @return int number of records created
     */
    public function generateForRange(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $created = 0;
        $end = $end->min($this->tenant->today());

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $created += $this->generateForDate($date);
        }

        return $created;
    }

    /**
     * Write an approved leave into attendance for each working day it covers.
     */
    public function syncLeave(EmployeeLeave $leave): void
    {
        $leave->loadMissing(['employee', 'leaveType']);
        $employee = $leave->employee;
        $paid = $leave->leaveType->is_paid;

        for ($date = $leave->start_date; $date->lte($leave->end_date); $date = $date->addDay()) {
            if (! $this->calendar->isWorkingDay($date) || ! $employee->isEmployedOn($date)) {
                continue;
            }

            $shift = $this->hours->shiftFor($employee, $date);
            $required = $shift->required_minutes ?? WorkingHoursCalculationService::DEFAULT_REQUIRED_MINUTES;

            // Half a day of unpaid leave is a half day in attendance; the worked
            // half counts as worked, the other half is deducted by payroll.
            $halfUnpaid = $leave->is_half_day && ! $paid;

            $attendance = Attendance::query()->firstOrNew([
                'employee_id' => $employee->id,
                'date' => $date->toDateString(),
            ]);

            $attendance->fill([
                'status' => match (true) {
                    $halfUnpaid => AttendanceStatus::HalfDay,
                    $paid => AttendanceStatus::PaidLeave,
                    default => AttendanceStatus::UnpaidLeave,
                },
                'check_in' => null,
                'check_out' => null,
                'required_minutes' => $halfUnpaid ? intdiv($required, 2) : $required,
                'worked_minutes' => $halfUnpaid ? intdiv($required, 2) : 0,
                'short_minutes' => 0,
                'overtime_minutes' => 0,
                'late_minutes' => 0,
                'work_shift_id' => $shift?->id,
                'employee_leave_id' => $leave->id,
                'notes' => $leave->leaveType->name.($leave->is_half_day ? ' (half day)' : ''),
                'source' => Attendance::SOURCE_AUTOMATIC,
            ]);
            $attendance->save();
        }
    }

    /**
     * Remove the attendance written for a leave that is no longer approved.
     */
    public function removeLeave(EmployeeLeave $leave): void
    {
        Attendance::query()->where('employee_leave_id', $leave->id)->delete();
    }

    /**
     * Status and working-hours values for a manually marked day.
     *
     * @param  array{status: string, check_in?: string|null, check_out?: string|null, worked_minutes?: int|null}  $input
     * @return array<string, mixed>
     */
    private function valuesFor(Employee $employee, CarbonImmutable $date, array $input): array
    {
        $status = AttendanceStatus::from($input['status']);
        $settings = $this->tenant->settings();
        $shift = $this->hours->shiftFor($employee, $date);
        $shiftMinutes = $shift->required_minutes ?? WorkingHoursCalculationService::DEFAULT_REQUIRED_MINUTES;
        $checkIn = $input['check_in'] ?? null;
        $checkOut = $input['check_out'] ?? null;
        $hasTimes = $checkIn !== null && $checkOut !== null;

        $values = [
            'status' => $status,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'required_minutes' => 0,
            'worked_minutes' => 0,
            'short_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'work_shift_id' => $shift?->id,
        ];

        if ($status->isNonWorkingDay() || $status === AttendanceStatus::Other) {
            // Any time worked on a day off is overtime in full.
            if ($hasTimes) {
                $worked = $this->hours->calculate($shift, $checkIn, $checkOut, 0)['worked_minutes'];
                $values['worked_minutes'] = $worked;
                $values['overtime_minutes'] = $worked;
            }

            return $values;
        }

        if (! $status->tracksHours()) {
            // Absent and full-day leave: the scheduled hours were simply not worked.
            return [...$values, 'required_minutes' => $shiftMinutes, 'check_in' => null, 'check_out' => null];
        }

        $required = $status === AttendanceStatus::HalfDay ? intdiv($shiftMinutes, 2) : $shiftMinutes;

        if (! $hasTimes) {
            $worked = isset($input['worked_minutes']) ? max(0, (int) $input['worked_minutes']) : $required;
            $short = max(0, $required - $worked);

            return [
                ...$values,
                'required_minutes' => $required,
                'worked_minutes' => $worked,
                'short_minutes' => $short <= $settings->short_hours_tolerance_minutes ? 0 : $short,
                'overtime_minutes' => max(0, $worked - $required),
            ];
        }

        $calculated = $this->hours->calculate(
            $shift,
            $checkIn,
            $checkOut,
            $required,
            $settings->grace_minutes,
            $settings->short_hours_tolerance_minutes,
        );

        // Present, late and short hours describe the same kind of day, so the
        // exact one follows from the recorded times.
        if (in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::ShortHours], true)) {
            $status = match (true) {
                $calculated['late_minutes'] > 0 => AttendanceStatus::Late,
                $calculated['short_minutes'] > 0 => AttendanceStatus::ShortHours,
                default => AttendanceStatus::Present,
            };
        }

        return [...$values, ...$calculated, 'status' => $status];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordedDay(Attendance $attendance): array
    {
        $date = $attendance->date;

        return [
            ...$this->baseDay($date),
            'state' => self::STATE_RECORDED,
            'status' => $attendance->status->value,
            'status_label' => $attendance->status->label(),
            'code' => $attendance->status->shortCode(),
            'check_in' => $this->shortTime($attendance->check_in),
            'check_out' => $this->shortTime($attendance->check_out),
            'required_minutes' => $attendance->required_minutes,
            'worked_minutes' => $attendance->worked_minutes,
            'short_minutes' => $attendance->short_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'late_minutes' => $attendance->late_minutes,
            'work_shift_id' => $attendance->work_shift_id,
            'notes' => $attendance->notes,
            'source' => $attendance->source,
            'attendance_id' => $attendance->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function derivedDay(Employee $employee, CarbonImmutable $date): array
    {
        $day = [
            ...$this->baseDay($date),
            'state' => self::STATE_DERIVED,
            'status' => null,
            'status_label' => null,
            'code' => null,
            'check_in' => null,
            'check_out' => null,
            'required_minutes' => 0,
            'worked_minutes' => 0,
            'short_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'work_shift_id' => null,
            'notes' => null,
            'source' => null,
            'attendance_id' => null,
        ];

        if (! $employee->isEmployedOn($date)) {
            return [...$day, 'state' => self::STATE_NOT_EMPLOYED];
        }

        $upcoming = $date->gt($this->tenant->today());

        if (! $day['is_working_day']) {
            $status = $day['holiday_name'] !== null ? AttendanceStatus::Holiday : AttendanceStatus::WeeklyOff;

            return [
                ...$day,
                'state' => $upcoming ? self::STATE_UPCOMING : self::STATE_DERIVED,
                'status' => $status->value,
                'status_label' => $status->label(),
                'code' => $status->shortCode(),
            ];
        }

        if ($upcoming) {
            return [...$day, 'state' => self::STATE_UPCOMING];
        }

        $shift = $this->hours->shiftFor($employee, $date);
        $required = $shift->required_minutes ?? WorkingHoursCalculationService::DEFAULT_REQUIRED_MINUTES;

        if ($this->tenant->settings()->attendance_mode === AttendanceMode::Manual) {
            return [...$day, 'state' => self::STATE_UNMARKED, 'required_minutes' => $required, 'work_shift_id' => $shift?->id];
        }

        return [
            ...$day,
            'status' => AttendanceStatus::Present->value,
            'status_label' => AttendanceStatus::Present->label(),
            'code' => AttendanceStatus::Present->shortCode(),
            'check_in' => $this->shortTime($shift?->start_time),
            'check_out' => $this->shortTime($shift?->end_time),
            'required_minutes' => $required,
            'worked_minutes' => $required,
            'work_shift_id' => $shift?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDay(CarbonImmutable $date): array
    {
        $holiday = $this->calendar->holidayName($date);

        return [
            'date' => $date->toDateString(),
            'day' => $date->day,
            'weekday' => $date->format('D'),
            'holiday_name' => $holiday,
            'is_working_day' => $holiday === null && ! $this->calendar->isWeeklyOff($date),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loggable(Attendance $attendance): array
    {
        return [
            'status' => $attendance->status->value,
            'check_in' => $this->shortTime($attendance->check_in),
            'check_out' => $this->shortTime($attendance->check_out),
            'required_minutes' => (int) $attendance->required_minutes,
            'worked_minutes' => (int) $attendance->worked_minutes,
            'short_minutes' => (int) $attendance->short_minutes,
            'overtime_minutes' => (int) $attendance->overtime_minutes,
            'late_minutes' => (int) $attendance->late_minutes,
            'notes' => $attendance->notes,
        ];
    }

    private function shortTime(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
