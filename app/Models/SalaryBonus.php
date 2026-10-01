<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off earning for an employee: a bonus or another earning.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property CarbonImmutable $date
 * @property string $type
 * @property string $title
 * @property float $amount
 * @property string|null $reason
 * @property int|null $payroll_item_id
 * @property int|null $created_by
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'date', 'type', 'title', 'amount', 'reason', 'created_by'])]
class SalaryBonus extends Model
{
    use BelongsToCompany;

    public const string TYPE_BONUS = 'bonus';

    public const string TYPE_OTHER_EARNING = 'other_earning';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'float',
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
