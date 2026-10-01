<?php

namespace App\Models;

use App\Enums\BorrowStatus;
use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One borrow / salary advance. An employee may have several, and each keeps
 * its own schedule and transaction history; they are never merged.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property string $reference_no
 * @property string $kind
 * @property float $amount
 * @property float $opening_balance
 * @property float $recovered_amount
 * @property float $outstanding_amount
 * @property CarbonImmutable $borrow_date
 * @property string|null $reason
 * @property float $monthly_deduction
 * @property int|null $installments_count
 * @property CarbonImmutable|null $deduction_start_month
 * @property string $disbursement_method
 * @property CarbonImmutable|null $disburse_period
 * @property CarbonImmutable|null $disbursed_at
 * @property BorrowStatus $status
 * @property string|null $source_reference
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id', 'reference_no', 'kind', 'amount', 'opening_balance', 'recovered_amount',
    'outstanding_amount', 'borrow_date', 'reason', 'monthly_deduction', 'installments_count',
    'deduction_start_month', 'disbursement_method', 'disburse_period', 'disbursed_at', 'status',
    'source_reference', 'notes', 'created_by',
])]
class EmployeeBorrow extends Model
{
    use BelongsToCompany;

    /** A borrow the employee already had when joining the company. */
    public const string KIND_EXISTING = 'existing';

    /** A borrow issued by the company after joining. */
    public const string KIND_NEW = 'new';

    /** Money was handed over outside of payroll. */
    public const string DISBURSE_DIRECT = 'direct';

    /** Money is paid out together with a payroll run. */
    public const string DISBURSE_WITH_SALARY = 'with_salary';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'opening_balance' => 'float',
            'recovered_amount' => 'float',
            'outstanding_amount' => 'float',
            'borrow_date' => 'date',
            'monthly_deduction' => 'float',
            'installments_count' => 'integer',
            'deduction_start_month' => 'date',
            'disburse_period' => 'date',
            'disbursed_at' => 'datetime',
            'status' => BorrowStatus::class,
        ];
    }

    /**
     * Borrows with money still owed by the employee.
     *
     * @param  Builder<EmployeeBorrow>  $query
     */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->where('status', BorrowStatus::Active->value)->where('outstanding_amount', '>', 0);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<BorrowInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(BorrowInstallment::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<BorrowTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BorrowTransaction::class)->orderBy('id');
    }
}
