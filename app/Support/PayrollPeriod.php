<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * A monthly payroll period.
 *
 * With a start day of 1 the period is the calendar month. With any other
 * start day (2-28) the period named "September" runs from that day in
 * September to the day before it in October.
 */
final readonly class PayrollPeriod
{
    public function __construct(
        public int $year,
        public int $month,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function forMonth(int $year, int $month, int $startDay = 1): self
    {
        $startDay = max(1, min(28, $startDay));
        $start = CarbonImmutable::create($year, $month, $startDay)->startOfDay();
        $end = $startDay === 1
            ? $start->endOfMonth()->startOfDay()
            : $start->addMonthNoOverflow()->subDay();

        return new self($year, $month, $start, $end);
    }

    /**
     * The period that contains the given date.
     */
    public static function containing(CarbonInterface $date, int $startDay = 1): self
    {
        $startDay = max(1, min(28, $startDay));
        $anchor = CarbonImmutable::parse($date->toDateString());

        if ($anchor->day < $startDay) {
            $anchor = $anchor->subMonthNoOverflow();
        }

        return self::forMonth($anchor->year, $anchor->month, $startDay);
    }

    public function label(): string
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->format('F Y');
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->toDateString() >= $this->start->toDateString()
            && $date->toDateString() <= $this->end->toDateString();
    }

    public function previous(int $startDay = 1): self
    {
        $anchor = CarbonImmutable::create($this->year, $this->month, 1)->subMonthNoOverflow();

        return self::forMonth($anchor->year, $anchor->month, $startDay);
    }

    /**
     * @return CarbonPeriod<CarbonImmutable>
     */
    public function eachDay(): CarbonPeriod
    {
        return CarbonPeriod::create($this->start, $this->end);
    }

    /**
     * @return array{year: int, month: int, start: string, end: string, label: string, days: int}
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'label' => $this->label(),
            'days' => $this->days(),
        ];
    }
}
