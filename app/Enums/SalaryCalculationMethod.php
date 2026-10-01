<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SalaryCalculationMethod: string
{
    use HasOptions;

    /** Per-day rate = monthly salary / calendar days in the payroll period. */
    case CalendarDays = 'calendar_days';
    /** Per-day rate = monthly salary / scheduled working days in the payroll period. */
    case WorkingDays = 'working_days';
    /** Per-day rate = monthly salary / 30, regardless of the month length. */
    case Fixed30 = 'fixed_30';

    public function label(): string
    {
        return match ($this) {
            self::CalendarDays => 'Calendar days in the month',
            self::WorkingDays => 'Working days in the month',
            self::Fixed30 => 'Fixed 30 days',
        };
    }
}
