<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_salary_revision_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property float $amount
 * @property int $sort_order
 */
#[Fillable(['employee_salary_revision_id', 'code', 'name', 'type', 'amount', 'sort_order'])]
class EmployeeSalaryComponent extends Model
{
    use BelongsToCompany;

    public const string TYPE_EARNING = 'earning';

    public const string TYPE_DEDUCTION = 'deduction';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EmployeeSalaryRevision, $this>
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalaryRevision::class, 'employee_salary_revision_id');
    }
}
