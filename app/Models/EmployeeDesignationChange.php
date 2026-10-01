<?php

namespace App\Models;

use App\Enums\DesignationChangeType;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeDesignationChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per designation an employee has held, from joining onwards. The
 * history is append-only: a wrong entry is corrected by recording another
 * change, never by editing or deleting one.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int|null $from_designation_id
 * @property int $to_designation_id
 * @property DesignationChangeType $type
 * @property CarbonImmutable $effective_date
 * @property string|null $reason
 * @property int|null $changed_by
 * @property CarbonImmutable|null $created_at
 * @property-read Employee $employee
 * @property-read Designation|null $fromDesignation
 * @property-read Designation $toDesignation
 * @property-read User|null $changedBy
 */
#[Fillable(['employee_id', 'from_designation_id', 'to_designation_id', 'type', 'effective_date', 'reason', 'changed_by'])]
class EmployeeDesignationChange extends Model
{
    /** @use HasFactory<EmployeeDesignationChangeFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DesignationChangeType::class,
            'effective_date' => 'date',
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
     * @return BelongsTo<Designation, $this>
     */
    public function fromDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'from_designation_id');
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function toDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'to_designation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * The fields the history screens show for one change.
     * Expects `fromDesignation`, `toDesignation` and `changedBy` to be eager loaded.
     *
     * @return array{id: int, type: string, type_label: string, from: string|null, to: string, effective_date: string, reason: string|null, changed_by: string|null, created_at: string|null}
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'from' => $this->fromDesignation?->name,
            'to' => $this->toDesignation->name,
            'effective_date' => $this->effective_date->toDateString(),
            'reason' => $this->reason,
            'changed_by' => $this->changedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
