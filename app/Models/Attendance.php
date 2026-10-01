<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property CarbonImmutable $date
 * @property AttendanceStatus $status
 * @property string|null $check_in
 * @property string|null $check_out
 * @property int $required_minutes
 * @property int $worked_minutes
 * @property int $short_minutes
 * @property int $overtime_minutes
 * @property int $late_minutes
 * @property int|null $work_shift_id
 * @property int|null $employee_leave_id
 * @property string|null $notes
 * @property string $source
 * @property int|null $modified_by
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id', 'date', 'status', 'check_in', 'check_out', 'required_minutes', 'worked_minutes',
    'short_minutes', 'overtime_minutes', 'late_minutes', 'work_shift_id', 'employee_leave_id',
    'notes', 'source', 'modified_by',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use BelongsToCompany, HasFactory;

    public const string SOURCE_AUTOMATIC = 'automatic';

    public const string SOURCE_MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'required_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'short_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'late_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<WorkShift, $this>
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * @return HasMany<AttendanceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
