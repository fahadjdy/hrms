<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmployeeStatus: string
{
    use HasOptions;

    case Probation = 'probation';
    case Active = 'active';
    case NoticePeriod = 'notice';
    case Past = 'past';

    public function label(): string
    {
        return match ($this) {
            self::Probation => 'On Probation',
            self::Active => 'Active',
            self::NoticePeriod => 'Notice Period',
            self::Past => 'Past Employee',
        };
    }

    /**
     * Statuses of employees who currently work at the company.
     *
     * @return list<string>
     */
    public static function currentValues(): array
    {
        return [self::Probation->value, self::Active->value, self::NoticePeriod->value];
    }

    /**
     * Options selectable while the employee still works at the company.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function currentOptions(): array
    {
        return array_values(array_filter(
            self::options(),
            fn (array $option): bool => $option['value'] !== self::Past->value,
        ));
    }
}
