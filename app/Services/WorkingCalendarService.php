<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\WeeklyHoliday;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * The company calendar: weekly offs and holidays decide which dates are
 * scheduled working days. Attendance, payroll and leave all read it here.
 */
class WorkingCalendarService
{
    /** @var array<int, list<int>> */
    private array $weeklyOffs = [];

    /** @var array<int, array<string, string>> */
    private array $holidays = [];

    /** @var array<int, array{0: string, 1: string}> */
    private array $holidayRange = [];

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Days of the week (0 = Sunday … 6 = Saturday) that are weekly offs.
     *
     * @return list<int>
     */
    public function weeklyOffDays(): array
    {
        $companyId = $this->tenant->require()->id;

        return $this->weeklyOffs[$companyId] ??= array_values(WeeklyHoliday::query()
            ->orderBy('day_of_week')
            ->pluck('day_of_week')
            ->map(fn (mixed $day): int => (int) $day)
            ->all());
    }

    public function isWeeklyOff(CarbonImmutable $date): bool
    {
        return in_array($date->dayOfWeek, $this->weeklyOffDays(), true);
    }

    /**
     * Holiday names keyed by date (Y-m-d) for the given range.
     *
     * @return array<string, string>
     */
    public function holidaysBetween(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $companyId = $this->tenant->require()->id;
        $from = $start->toDateString();
        $to = $end->toDateString();

        $loaded = $this->holidayRange[$companyId] ?? null;

        if ($loaded === null || $from < $loaded[0] || $to > $loaded[1]) {
            // Load whole years so repeated month lookups reuse one query.
            $rangeStart = min($from, $loaded[0] ?? $from);
            $rangeEnd = max($to, $loaded[1] ?? $to);
            $rangeStart = CarbonImmutable::parse($rangeStart)->startOfYear()->toDateString();
            $rangeEnd = CarbonImmutable::parse($rangeEnd)->endOfYear()->toDateString();

            $this->holidays[$companyId] = Holiday::query()
                ->whereBetween('date', [$rangeStart, $rangeEnd])
                ->orderBy('date')
                ->get(['date', 'name'])
                ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => $holiday->name])
                ->all();
            $this->holidayRange[$companyId] = [$rangeStart, $rangeEnd];
        }

        return array_filter(
            $this->holidays[$companyId],
            fn (string $date): bool => $date >= $from && $date <= $to,
            ARRAY_FILTER_USE_KEY,
        );
    }

    public function holidayName(CarbonImmutable $date): ?string
    {
        return $this->holidaysBetween($date, $date)[$date->toDateString()] ?? null;
    }

    public function isWorkingDay(CarbonImmutable $date): bool
    {
        return ! $this->isWeeklyOff($date) && $this->holidayName($date) === null;
    }

    /**
     * Number of scheduled working days in the range (inclusive).
     */
    public function workingDaysBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $holidays = $this->holidaysBetween($start, $end);
        $weeklyOffs = $this->weeklyOffDays();
        $count = 0;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (in_array($date->dayOfWeek, $weeklyOffs, true)) {
                continue;
            }

            if (isset($holidays[$date->toDateString()])) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Forget cached calendar data after weekly offs or holidays change.
     */
    public function flush(): void
    {
        $this->weeklyOffs = [];
        $this->holidays = [];
        $this->holidayRange = [];
    }
}
