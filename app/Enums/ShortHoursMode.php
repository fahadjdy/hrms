<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ShortHoursMode: string
{
    use HasOptions;

    /** Short hours are converted to an amount and deducted from salary automatically. */
    case Deduct = 'deduct';
    /** Short hours are recorded but never deducted automatically. */
    case RecordOnly = 'record_only';
    /** The admin decides the deduction for each employee and period. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Deduct => 'Deduct from salary',
            self::RecordOnly => 'Record only',
            self::Manual => 'Manual adjustment',
        };
    }
}
