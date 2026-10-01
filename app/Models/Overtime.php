<?php

namespace App\Models;

use App\Enums\OvertimeStatus;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property CarbonImmutable $date
 * @property string $calculation_type
 * @property float|null $hours
 * @property float|null $rate
 * @property float $amount
 * @property string|null $reason
 * @property string|null $notes
 * @property OvertimeStatus $status
 * @property int|null $payroll_item_id
 * @property int|null $created_by
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id', 'date', 'calculation_type', 'hours', 'rate', 'amount', 'reason', 'notes',
    'status', 'created_by',
])]
class Overtime extends Model
{
    use BelongsToCompany;

    /** Amount = hours x rate. */
    public const string TYPE_HOURLY = 'hourly';

    /** A fixed overtime amount, independent of hours. */
    public const string TYPE_FIXED = 'fixed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'float',
            'rate' => 'float',
            'amount' => 'float',
            'status' => OvertimeStatus::class,
        ];
    }

    public function isLocked(): bool
    {
        return $this->payroll_item_id !== null;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
