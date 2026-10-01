<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The totals a payroll item is made of. Every breakdown line belongs to one
 * bucket, and manual adjustments are recorded against a bucket.
 */
enum PayrollBucket: string
{
    use HasOptions;

    case Salary = 'gross_salary';
    case Overtime = 'overtime_amount';
    case Bonus = 'bonus_amount';
    case OtherEarnings = 'other_earnings_amount';
    case AttendanceDeduction = 'attendance_deduction';
    case UnpaidLeave = 'unpaid_leave_deduction';
    case ShortHours = 'short_hours_deduction';
    case BorrowRecovery = 'borrow_recovery';
    case OtherDeductions = 'other_deductions';
    case BorrowGiven = 'borrow_given';

    public function label(): string
    {
        return match ($this) {
            self::Salary => 'Salary',
            self::Overtime => 'Overtime',
            self::Bonus => 'Bonus',
            self::OtherEarnings => 'Other Earnings',
            self::AttendanceDeduction => 'Attendance Deduction',
            self::UnpaidLeave => 'Unpaid Leave',
            self::ShortHours => 'Short Hours Deduction',
            self::BorrowRecovery => 'Borrow Recovery',
            self::OtherDeductions => 'Other Deductions',
            self::BorrowGiven => 'New Borrow / Advance',
        };
    }

    /**
     * +1 when the bucket increases what the employee is paid, -1 when it reduces it.
     */
    public function sign(): int
    {
        return match ($this) {
            self::Salary, self::Overtime, self::Bonus, self::OtherEarnings, self::BorrowGiven => 1,
            default => -1,
        };
    }

    public function isDeduction(): bool
    {
        return $this->sign() === -1;
    }

    /**
     * A borrow given is an advance, not salary income, so it never counts as an earning.
     */
    public function isEarning(): bool
    {
        return $this->sign() === 1 && $this !== self::BorrowGiven;
    }

    /**
     * Buckets an admin may adjust manually. A new borrow is changed by editing the borrow itself.
     *
     * @return list<self>
     */
    public static function adjustable(): array
    {
        $buckets = [];

        foreach (self::cases() as $bucket) {
            if ($bucket !== self::BorrowGiven) {
                $buckets[] = $bucket;
            }
        }

        return $buckets;
    }
}
