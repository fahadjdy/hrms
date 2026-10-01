<?php

namespace Tests\Unit\Support;

use App\Support\PayrollPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class PayrollPeriodTest extends TestCase
{
    public function test_period_starting_on_the_first_is_the_calendar_month(): void
    {
        $period = PayrollPeriod::forMonth(2026, 9);

        $this->assertSame('2026-09-01', $period->start->toDateString());
        $this->assertSame('2026-09-30', $period->end->toDateString());
        $this->assertSame(30, $period->days());
        $this->assertSame('September 2026', $period->label());
    }

    public function test_february_of_a_leap_year_has_29_days(): void
    {
        $period = PayrollPeriod::forMonth(2028, 2);

        $this->assertSame('2028-02-29', $period->end->toDateString());
        $this->assertSame(29, $period->days());
    }

    public function test_period_with_a_later_start_day_runs_into_the_next_month(): void
    {
        $period = PayrollPeriod::forMonth(2026, 9, 26);

        $this->assertSame('2026-09-26', $period->start->toDateString());
        $this->assertSame('2026-10-25', $period->end->toDateString());
        $this->assertSame(30, $period->days());
        $this->assertSame('September 2026', $period->label());
    }

    public function test_period_starting_late_in_january_ends_in_february(): void
    {
        $period = PayrollPeriod::forMonth(2026, 1, 28);

        $this->assertSame('2026-01-28', $period->start->toDateString());
        $this->assertSame('2026-02-27', $period->end->toDateString());
    }

    #[TestWith(['2026-10-10', 26, '2026-09-26', '2026-10-25'])]
    #[TestWith(['2026-10-26', 26, '2026-10-26', '2026-11-25'])]
    #[TestWith(['2026-09-26', 26, '2026-09-26', '2026-10-25'])]
    #[TestWith(['2026-10-10', 1, '2026-10-01', '2026-10-31'])]
    #[TestWith(['2026-01-05', 26, '2025-12-26', '2026-01-25'])]
    public function test_the_period_containing_a_date_depends_on_the_start_day(string $date, int $startDay, string $start, string $end): void
    {
        $period = PayrollPeriod::containing(CarbonImmutable::parse($date), $startDay);

        $this->assertSame($start, $period->start->toDateString());
        $this->assertSame($end, $period->end->toDateString());
        $this->assertTrue($period->contains(CarbonImmutable::parse($date)));
    }

    public function test_contains_includes_both_ends_and_nothing_outside(): void
    {
        $period = PayrollPeriod::forMonth(2026, 9);

        $this->assertTrue($period->contains(CarbonImmutable::parse('2026-09-01')));
        $this->assertTrue($period->contains(CarbonImmutable::parse('2026-09-30 23:59:59')));
        $this->assertFalse($period->contains(CarbonImmutable::parse('2026-08-31')));
        $this->assertFalse($period->contains(CarbonImmutable::parse('2026-10-01')));
    }

    public function test_previous_period_of_january_is_december_of_the_year_before(): void
    {
        $previous = PayrollPeriod::forMonth(2026, 1)->previous();

        $this->assertSame(2025, $previous->year);
        $this->assertSame(12, $previous->month);
        $this->assertSame('2025-12-31', $previous->end->toDateString());
    }

    public function test_start_day_is_kept_between_1_and_28(): void
    {
        $this->assertSame('2026-02-28', PayrollPeriod::forMonth(2026, 2, 31)->start->toDateString());
        $this->assertSame('2026-02-01', PayrollPeriod::forMonth(2026, 2, 0)->start->toDateString());
    }

    public function test_array_form_lists_the_period_boundaries(): void
    {
        $this->assertSame(
            ['year' => 2026, 'month' => 9, 'start' => '2026-09-01', 'end' => '2026-09-30', 'label' => 'September 2026', 'days' => 30],
            PayrollPeriod::forMonth(2026, 9)->toArray(),
        );
    }
}
