<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $period_year
 * @property int $period_month
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property PayrollStatus $status
 * @property int $employee_count
 * @property float $total_gross
 * @property float $total_earnings
 * @property float $total_deductions
 * @property float $total_borrow_given
 * @property float $total_borrow_recovery
 * @property float $total_net_payable
 * @property string|null $notes
 * @property CarbonImmutable|null $calculated_at
 * @property CarbonImmutable|null $finalized_at
 * @property int|null $finalized_by
 * @property CarbonImmutable|null $reopened_at
 * @property int|null $reopened_by
 * @property string|null $reopen_reason
 * @property int|null $created_by
 */
#[Fillable(['period_year', 'period_month', 'period_start', 'period_end', 'notes', 'created_by'])]
class Payroll extends Model
{
    use BelongsToCompany;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => PayrollStatus::class,
            'employee_count' => 'integer',
            'total_gross' => 'float',
            'total_earnings' => 'float',
            'total_deductions' => 'float',
            'total_borrow_given' => 'float',
            'total_borrow_recovery' => 'float',
            'total_net_payable' => 'float',
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    public function period(): PayrollPeriod
    {
        return new PayrollPeriod($this->period_year, $this->period_month, $this->period_start, $this->period_end);
    }

    public function label(): string
    {
        return $this->period()->label();
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
