<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One employee's pay for one payroll, with the full breakdown stored as a
 * snapshot so the result stays explainable after the source data changes.
 *
 * @property int $id
 * @property int $company_id
 * @property int $payroll_id
 * @property int $employee_id
 * @property string $employee_name
 * @property string $employee_code
 * @property int|null $department_id
 * @property string|null $department_name
 * @property string|null $designation_name
 * @property float $gross_salary
 * @property float $overtime_amount
 * @property float $bonus_amount
 * @property float $other_earnings_amount
 * @property float $attendance_deduction
 * @property float $unpaid_leave_deduction
 * @property float $short_hours_deduction
 * @property float $borrow_recovery
 * @property float $other_deductions
 * @property float $borrow_given
 * @property float $net_salary
 * @property float $net_payable
 * @property float $present_days
 * @property float $absent_days
 * @property float $leave_days
 * @property int $short_minutes
 * @property int $overtime_minutes
 * @property array<string, mixed>|null $attendance_summary
 * @property array<string, mixed>|null $breakdown
 * @property bool $is_adjusted
 * @property-read Payroll $payroll
 * @property-read Employee $employee
 * @property-read Collection<int, PayrollAdjustment> $adjustments
 */
#[Fillable([
    'payroll_id', 'employee_id', 'employee_name', 'employee_code', 'department_id', 'department_name',
    'designation_name', 'gross_salary', 'overtime_amount', 'bonus_amount', 'other_earnings_amount',
    'attendance_deduction', 'unpaid_leave_deduction', 'short_hours_deduction', 'borrow_recovery',
    'other_deductions', 'borrow_given', 'net_salary', 'net_payable', 'present_days', 'absent_days',
    'leave_days', 'short_minutes', 'overtime_minutes', 'attendance_summary', 'breakdown', 'is_adjusted',
])]
class PayrollItem extends Model
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_salary' => 'float',
            'overtime_amount' => 'float',
            'bonus_amount' => 'float',
            'other_earnings_amount' => 'float',
            'attendance_deduction' => 'float',
            'unpaid_leave_deduction' => 'float',
            'short_hours_deduction' => 'float',
            'borrow_recovery' => 'float',
            'other_deductions' => 'float',
            'borrow_given' => 'float',
            'net_salary' => 'float',
            'net_payable' => 'float',
            'present_days' => 'float',
            'absent_days' => 'float',
            'leave_days' => 'float',
            'short_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'attendance_summary' => 'array',
            'breakdown' => 'array',
            'is_adjusted' => 'boolean',
        ];
    }

    /**
     * Everything deducted from the employee's pay, including borrow recovery.
     */
    public function totalDeductions(): float
    {
        return round(
            $this->attendance_deduction + $this->unpaid_leave_deduction + $this->short_hours_deduction
            + $this->borrow_recovery + $this->other_deductions,
            2,
        );
    }

    /**
     * @return BelongsTo<Payroll, $this>
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayrollAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /**
     * @return HasOne<SalarySlip, $this>
     */
    public function salarySlip(): HasOne
    {
        return $this->hasOne(SalarySlip::class);
    }
}
