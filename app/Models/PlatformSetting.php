<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide (not tenant-owned) key/value settings managed by the super admin.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class PlatformSetting extends Model
{
    public const array DEFAULTS = [
        'platform_name' => 'HRMS',
        'support_email' => '',
        'default_currency' => 'INR',
        'default_timezone' => 'Asia/Kolkata',
        'default_date_format' => 'd M Y',
    ];

    /**
     * @return array<string, string>
     */
    public static function allValues(): array
    {
        /** @var array<string, string|null> $stored */
        $stored = self::query()->pluck('value', 'key')->all();

        return array_map(
            fn (?string $value): string => (string) $value,
            array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS)),
        );
    }
}
