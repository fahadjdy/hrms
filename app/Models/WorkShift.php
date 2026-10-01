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
 * @property string|null $break_start
 * @property string|null $break_end
 * @property int $required_minutes
 * @property int $break_minutes
 * @property bool $is_active
 */
#[Fillable(['name', 'start_time', 'end_time', 'break_start', 'break_end', 'required_minutes', 'break_minutes', 'is_active'])]
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

    public function hasBreakTiming(): bool
    {
        return $this->break_start !== null && $this->break_end !== null;
    }

    /**
     * Minutes of the fixed break that fall between a check-in and a check-out.
     * Both pairs may run past midnight.
     */
    public function breakOverlapMinutes(string $checkIn, string $checkOut): int
    {
        if (! $this->hasBreakTiming()) {
            return 0;
        }

        $in = self::timeToMinutes($checkIn);
        $span = self::minutesBetween($in, self::timeToMinutes($checkOut));
        $breakStart = self::minutesBetween($in, self::timeToMinutes($this->break_start));
        $breakLength = self::minutesBetween(self::timeToMinutes($this->break_start), self::timeToMinutes($this->break_end));

        // Checking in during the break means the break began "yesterday" on
        // this timeline, so the occurrence a day earlier is counted as well.
        $overlap = 0;

        foreach ([$breakStart - 1440, $breakStart] as $start) {
            $overlap += max(0, min($span, $start + $breakLength) - max(0, $start));
        }

        return $overlap;
    }

    /**
     * Minutes from one time of day to the next, running past midnight when needed.
     */
    public static function minutesBetween(int $from, int $to): int
    {
        return $to >= $from ? $to - $from : $to + 1440 - $from;
    }

    public static function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
