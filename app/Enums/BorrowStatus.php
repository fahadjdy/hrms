<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BorrowStatus: string
{
    use HasOptions;

    /** Created to be paid out with a payroll that has not been finalized yet. */
    case PendingDisbursement = 'pending_disbursement';
    case Active = 'active';
    case Recovered = 'recovered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingDisbursement => 'Pending Disbursement',
            self::Active => 'Active',
            self::Recovered => 'Fully Recovered',
            self::Cancelled => 'Cancelled',
        };
    }
}
