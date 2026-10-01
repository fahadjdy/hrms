<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ExitType: string
{
    use HasOptions;

    case Resignation = 'resignation';
    case Termination = 'termination';
    case Retirement = 'retirement';
    case ContractEnd = 'contract_end';
    case Absconding = 'absconding';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Resignation => 'Resignation',
            self::Termination => 'Termination',
            self::Retirement => 'Retirement',
            self::ContractEnd => 'Contract Ended',
            self::Absconding => 'Absconding',
            self::Other => 'Other',
        };
    }
}
