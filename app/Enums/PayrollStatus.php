<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PayrollStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Calculated = 'calculated';
    case UnderReview = 'under_review';
    case Adjusted = 'adjusted';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Calculated => 'Calculated',
            self::UnderReview => 'Admin Review',
            self::Adjusted => 'Adjusted',
            self::Finalized => 'Finalized',
        };
    }

    public function isLocked(): bool
    {
        return $this === self::Finalized;
    }
}
