<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property bool $is_half_day
 * @property float $days
 * @property LeaveStatus $status
 * @property string|null $reason
 * @property string|null $notes
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property int|null $created_by
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 */
#[Fillable(['employee_id', 'leave_type_id', 'start_date', 'end_date', 'is_half_day', 'reason', 'notes'])]
class EmployeeLeave extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_half_day' => 'boolean',
            'days' => 'float',
            'status' => LeaveStatus::class,
            'decided_at' => 'datetime',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
