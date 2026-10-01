<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property CarbonImmutable $date
 * @property string $type
 * @property string|null $description
 */
#[Fillable(['name', 'date', 'type', 'description'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use BelongsToCompany, HasFactory;

    public const array TYPES = [
        'public' => 'Public Holiday',
        'company' => 'Company Holiday',
        'optional' => 'Optional Holiday',
        'other' => 'Other',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
