<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\WorkShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property int $required_minutes
 * @property int $break_minutes
 * @property bool $is_active
 */
#[Fillable(['name', 'start_time', 'end_time', 'required_minutes', 'break_minutes', 'is_active'])]
class WorkShift extends Model
{
    /** @use HasFactory<WorkShiftFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required_minutes' => 'integer',
            'break_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmployeeShiftAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    /**
     * Minutes between the shift start and end, allowing for overnight shifts.
     */
    public function spanMinutes(): int
    {
        $start = self::timeToMinutes($this->start_time);
        $end = self::timeToMinutes($this->end_time);

        return $end > $start ? $end - $start : $end + 1440 - $start;
    }

    public static function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
