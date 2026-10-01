<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BorrowTransactionType: string
{
    use HasOptions;

    /** Outstanding balance carried in when the employee joined. */
    case Opening = 'opening';
    /** Money handed to the employee. */
    case Disbursement = 'disbursement';
    /** Money recovered from the employee (payroll deduction or manual repayment). */
    case Recovery = 'recovery';
    /** Reversal of a recovery or disbursement, e.g. when a payroll is reopened. */
    case Reversal = 'reversal';
    /** Recovery taken from the final settlement. */
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening Balance',
            self::Disbursement => 'Borrow Given',
            self::Recovery => 'Recovery',
            self::Reversal => 'Reversal',
            self::Settlement => 'Final Settlement Recovery',
        };
    }
}
