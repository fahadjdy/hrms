<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendanceStatus: string
{
    use HasOptions;

    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case PaidLeave = 'paid_leave';
    case UnpaidLeave = 'unpaid_leave';
    case Holiday = 'holiday';
    case WeeklyOff = 'weekly_off';
    case WorkFromHome = 'wfh';
    case Late = 'late';
    case ShortHours = 'short_hours';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::HalfDay => 'Half Day',
            self::PaidLeave => 'Paid Leave',
            self::UnpaidLeave => 'Unpaid Leave',
            self::Holiday => 'Holiday',
            self::WeeklyOff => 'Weekly Off',
            self::WorkFromHome => 'Work From Home',
            self::Late => 'Late',
            self::ShortHours => 'Short Hours',
            self::Other => 'Other',
        };
    }

    public function shortCode(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Absent => 'A',
            self::HalfDay => 'HD',
            self::PaidLeave => 'PL',
            self::UnpaidLeave => 'UL',
            self::Holiday => 'H',
            self::WeeklyOff => 'OFF',
            self::WorkFromHome => 'WFH',
            self::Late => 'L',
            self::ShortHours => 'SH',
            self::Other => 'O',
        };
    }

    /**
     * Statuses where the employee worked a full scheduled day.
     */
    public function isWorkedDay(): bool
    {
        return in_array($this, [self::Present, self::WorkFromHome, self::Late, self::ShortHours], true);
    }

    /**
     * Statuses that track check-in / check-out and working hours.
     */
    public function tracksHours(): bool
    {
        return $this->isWorkedDay() || $this === self::HalfDay;
    }

    /**
     * Statuses that are not scheduled working days at all.
     */
    public function isNonWorkingDay(): bool
    {
        return in_array($this, [self::Holiday, self::WeeklyOff], true);
    }
}
