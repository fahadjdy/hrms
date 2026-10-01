<?php

namespace App\Models;

use App\Enums\BorrowTransactionType;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger of everything that changed a borrow's balance.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_borrow_id
 * @property int $employee_id
 * @property BorrowTransactionType $type
 * @property float $amount
 * @property float $balance_after
 * @property CarbonImmutable $transaction_date
 * @property int|null $payroll_item_id
 * @property string|null $notes
 * @property int|null $user_id
 * @property-read EmployeeBorrow $borrow
 */
#[Fillable([
    'employee_borrow_id', 'employee_id', 'type', 'amount', 'balance_after', 'transaction_date',
    'payroll_item_id', 'notes', 'user_id',
])]
class BorrowTransaction extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BorrowTransactionType::class,
            'amount' => 'float',
            'balance_after' => 'float',
            'transaction_date' => 'date',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
