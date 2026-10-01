<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DesignationChangeType: string
{
    use HasOptions;

    case Initial = 'initial';
    case Promotion = 'promotion';
    case Demotion = 'demotion';
    case Change = 'change';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Joined as',
            self::Promotion => 'Promotion',
            self::Demotion => 'Demotion',
            self::Change => 'Role change',
        };
    }

    /**
     * The kinds an admin can record; the initial designation comes from joining.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function recordableOptions(): array
    {
        return array_values(array_filter(
            self::options(),
            fn (array $option): bool => $option['value'] !== self::Initial->value,
        ));
    }

    /**
     * @return list<string>
     */
    public static function recordableValues(): array
    {
        return array_column(self::recordableOptions(), 'value');
    }
}
