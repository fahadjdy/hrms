<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendanceMode: string
{
    use HasOptions;

    /** Working days are marked present automatically; the admin only records exceptions. */
    case Automatic = 'automatic';
    /** The admin marks every working day. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Manual => 'Manual',
        };
    }
}
