<?php

namespace Database\Seeders\Concerns;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkShift;
use App\Services\WorkingCalendarService;
use App\Services\WorkingHoursCalculationService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Carbon;

/**
 * Helpers shared by the demo company seeders: relative dates, time travel and
 * bulk attendance whose values match what AttendanceCalculationService stores.
 */
trait SeedsTenantData
{
    /**
     * Share of working days, in percent, that are not a plain on-time day.
     * Whatever is left over is a normal present day.
     *
     * @var array<string, array<string, int>>
     */
    protected array $attendanceProfiles = [
        'regular' => ['late' => 5, 'short' => 3, 'overtime' => 3, 'wfh' => 3, 'half' => 1, 'absent' => 2],
        'steady' => ['late' => 1, 'short' => 1, 'overtime' => 4, 'wfh' => 4, 'half' => 0, 'absent' => 0],
        'overtime' => ['late' => 3, 'short' => 1, 'overtime' => 13, 'wfh' => 2, 'half' => 0, 'absent' => 1],
        'irregular' => ['late' => 14, 'short' => 8, 'overtime' => 1, 'wfh' => 1, 'half' => 2, 'absent' => 6],
        'remote' => ['late' => 3, 'short' => 2, 'overtime' => 2, 'wfh' => 14, 'half' => 1, 'absent' => 1],
    ];

    /**
     * The company's actual "today", remembered once so relative dates stay
     * anchored to it even while a callback runs with "now" moved into the past.
     */
    private ?CarbonImmutable $anchorDate = null;

    protected function realToday(): CarbonImmutable
    {
        return $this->anchorDate ??= app(TenantContext::class)->today();
    }

    /**
     * Run a callback with "now" set to a moment on the given date in the
     * company's timezone, so timestamps and "today" match the story being told.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function at(CarbonImmutable $date, Closure $callback, string $time = '11:00:00'): mixed
    {
        $real = CarbonImmutable::now();
        $moment = CarbonImmutable::parse($date->toDateString().' '.$time, app(TenantContext::class)->timezone());

        // Never travel into the future: anything dated today or later happens "now".
        if ($moment->gte($real)) {
            Carbon::setTestNow();

            return $callback();
        }

        Carbon::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * A date `$monthsBack` months before the current month, on the given day
     * (clamped to the length of that month).
     */
    protected function month(int $monthsBack, int $day = 1): CarbonImmutable
    {
        $start = $this->realToday()->startOfMonth()->subMonthsNoOverflow($monthsBack);

        return $start->setDay(min($day, $start->daysInMonth));
    }

    /**
     * The given date, moved forward to the next scheduled working day if needed.
     */
    protected function workday(CarbonImmutable $date): CarbonImmutable
    {
        $calendar = app(WorkingCalendarService::class);

        while (! $calendar->isWorkingDay($date)) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * Build attendance rows for an employee over a date range.
     *
     * Weekly offs and holidays are stored as such; working days are drawn
     * from the employee's attendance profile.
     *
     * @return list<array<string, mixed>>
     */
    protected function attendanceRows(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, string $profile, ?int $userId): array
    {
        $tenant = app(TenantContext::class);
        $calendar = app(WorkingCalendarService::class);
        $hours = app(WorkingHoursCalculationService::class);
        $rows = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            if (! $employee->isEmployedOn($date)) {
                continue;
            }

            if (! $calendar->isWorkingDay($date)) {
                $status = $calendar->holidayName($date) !== null ? AttendanceStatus::Holiday : AttendanceStatus::WeeklyOff;
                $rows[] = $this->attendanceRow($tenant->require()->id, $employee->id, $date, ['status' => $status->value], Attendance::SOURCE_AUTOMATIC, null);

                continue;
            }

            $values = $this->workingDayValues($hours->shiftFor($employee, $date), $this->drawDayKind($profile));
            $rows[] = $this->attendanceRow($tenant->require()->id, $employee->id, $date, $values, Attendance::SOURCE_MANUAL, $userId);
        }

        return $rows;
    }

    /**
     * Status and working-hours values for one working day of the given kind:
     * present, late, short, overtime, wfh, half or absent.
     *
     * @return array<string, mixed>
     */
    protected function workingDayValues(?WorkShift $shift, string $kind): array
    {
        $settings = app(TenantContext::class)->settings();
        $hours = app(WorkingHoursCalculationService::class);

        $required = $shift->required_minutes ?? WorkingHoursCalculationService::DEFAULT_REQUIRED_MINUTES;
        $start = $shift !== null ? WorkShift::timeToMinutes($shift->start_time) : 540;
        $span = $shift?->spanMinutes() ?? $required + 60;

        if ($kind === 'absent') {
            return ['status' => AttendanceStatus::Absent->value, 'required_minutes' => $required, 'work_shift_id' => $shift?->id];
        }

        if ($kind === 'wfh') {
            return [
                'status' => AttendanceStatus::WorkFromHome->value,
                'required_minutes' => $required,
                'worked_minutes' => $required,
                'work_shift_id' => $shift?->id,
            ];
        }

        if ($kind === 'half') {
            $required = intdiv($required, 2);
            [$in, $out] = [$start, $start + $required];
        } else {
            [$in, $out] = match ($kind) {
                'late' => [$late = $start + mt_rand(15, 75), max($start + $span, $late + $span - mt_rand(0, 45))],
                'short' => [$onTime = $start + mt_rand(-5, 8), $onTime + $span - mt_rand(35, 150)],
                'overtime' => [$onTime = $start + mt_rand(-5, 5), $onTime + $span + mt_rand(45, 180)],
                default => [$onTime = $start + mt_rand(-6, 9), $onTime + $span],
            };
        }

        $out = min($out, 1439);
        $checkIn = $this->clock($in);
        $checkOut = $this->clock($out);

        $calculated = $hours->calculate(
            $shift,
            $checkIn,
            $checkOut,
            $required,
            $settings->grace_minutes,
            $settings->short_hours_tolerance_minutes,
        );

        // The same rule the service applies when a day is marked with times.
        $status = match (true) {
            $kind === 'half' => AttendanceStatus::HalfDay,
            $calculated['late_minutes'] > 0 => AttendanceStatus::Late,
            $calculated['short_minutes'] > 0 => AttendanceStatus::ShortHours,
            default => AttendanceStatus::Present,
        };

        return [
            ...$calculated,
            'status' => $status->value,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'work_shift_id' => $shift?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function attendanceRow(int $companyId, int $employeeId, CarbonImmutable $date, array $values, string $source, ?int $userId): array
    {
        $stamp = $date->setTime(19, 0)->toDateTimeString();

        return [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'date' => $date->toDateString(),
            'status' => $values['status'],
            'check_in' => $values['check_in'] ?? null,
            'check_out' => $values['check_out'] ?? null,
            'required_minutes' => $values['required_minutes'] ?? 0,
            'worked_minutes' => $values['worked_minutes'] ?? 0,
            'short_minutes' => $values['short_minutes'] ?? 0,
            'overtime_minutes' => $values['overtime_minutes'] ?? 0,
            'late_minutes' => $values['late_minutes'] ?? 0,
            'work_shift_id' => $values['work_shift_id'] ?? null,
            'employee_leave_id' => null,
            'notes' => null,
            'source' => $source,
            'modified_by' => $userId,
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function insertAttendance(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            Attendance::query()->insertOrIgnore($chunk);
        }
    }

    /**
     * Pick the kind of day from an attendance profile.
     */
    protected function drawDayKind(string $profile): string
    {
        $roll = mt_rand(1, 100);
        $threshold = 0;

        foreach ($this->attendanceProfiles[$profile] ?? $this->attendanceProfiles['regular'] as $kind => $share) {
            $threshold += $share;

            if ($roll <= $threshold) {
                return $kind;
            }
        }

        return 'present';
    }

    /**
     * Minutes since midnight as HH:MM.
     */
    protected function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Round a money amount to the nearest 500, the way salaries are usually set.
     */
    protected function roundSalary(float $amount): float
    {
        return round($amount / 500) * 500;
    }
}
