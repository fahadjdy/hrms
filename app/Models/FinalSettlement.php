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
 * @property int $employee_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property float $last_salary
 * @property float $overtime_amount
 * @property float $bonus_amount
 * @property float $other_earnings_amount
 * @property float $unpaid_leave_deduction
 * @property float $short_hours_deduction
 * @property float $outstanding_borrow
 * @property float $other_deductions
 * @property float $adjustment_amount
 * @property string|null $adjustment_reason
 * @property float $net_amount
 * @property array<string, mixed>|null $breakdown
 * @property string $status
 * @property string|null $notes
 * @property CarbonImmutable|null $finalized_at
 * @property int|null $finalized_by
 * @property CarbonImmutable|null $paid_at
 * @property int|null $created_by
 * @property-read Employee $employee
 */
#[Fillable([
    'employee_id', 'period_start', 'period_end', 'last_salary', 'overtime_amount', 'bonus_amount',
    'other_earnings_amount', 'unpaid_leave_deduction', 'short_hours_deduction', 'outstanding_borrow',
    'other_deductions', 'adjustment_amount', 'adjustment_reason', 'net_amount', 'breakdown', 'notes',
    'created_by',
])]
class FinalSettlement extends Model
{
    use BelongsToCompany;

    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_FINALIZED = 'finalized';

    public const string STATUS_PAID = 'paid';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'last_salary' => 'float',
            'overtime_amount' => 'float',
            'bonus_amount' => 'float',
            'other_earnings_amount' => 'float',
            'unpaid_leave_deduction' => 'float',
            'short_hours_deduction' => 'float',
            'outstanding_borrow' => 'float',
            'other_deductions' => 'float',
            'adjustment_amount' => 'float',
            'net_amount' => 'float',
            'breakdown' => 'array',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->status !== self::STATUS_DRAFT;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
