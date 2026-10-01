<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_borrow_id
 * @property int $employee_id
 * @property int $sequence
 * @property CarbonImmutable $due_month
 * @property float $amount
 * @property float $paid_amount
 * @property string $status
 * @property int|null $payroll_item_id
 * @property CarbonImmutable|null $paid_at
 * @property-read EmployeeBorrow $borrow
 */
#[Fillable([
    'employee_borrow_id', 'employee_id', 'sequence', 'due_month', 'amount', 'paid_amount',
    'status', 'payroll_item_id', 'paid_at',
])]
class BorrowInstallment extends Model
{
    use BelongsToCompany;

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PAID = 'paid';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_month' => 'date',
            'amount' => 'float',
            'paid_amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EmployeeBorrow, $this>
     */
    public function borrow(): BelongsTo
    {
        return $this->belongsTo(EmployeeBorrow::class, 'employee_borrow_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
