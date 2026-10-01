<?php

namespace App\Models;

use App\Enums\AttendanceMode;
use App\Enums\SalaryCalculationMethod;
use App\Enums\ShortHoursMode;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property AttendanceMode $attendance_mode
 * @property int|null $default_work_shift_id
 * @property int|null $male_work_shift_id
 * @property int|null $female_work_shift_id
 * @property int|null $other_work_shift_id
 * @property int $grace_minutes
 * @property int $lates_per_half_day
 * @property int $short_hours_tolerance_minutes
 * @property SalaryCalculationMethod $salary_calculation_method
 * @property string $payroll_cycle
 * @property int $payroll_period_start_day
 * @property int $salary_payment_day
 * @property string $overtime_rate_type
 * @property float $overtime_multiplier
 * @property float|null $overtime_fixed_rate
 * @property bool $overtime_from_attendance
 * @property ShortHoursMode $short_hours_mode
 * @property string $short_hours_rate_type
 * @property float|null $short_hours_fixed_rate
 * @property bool $borrow_auto_deduct
 * @property float|null $borrow_max_deduction_percent
 */
#[Fillable([
    'attendance_mode', 'default_work_shift_id', 'male_work_shift_id', 'female_work_shift_id',
    'other_work_shift_id', 'grace_minutes', 'lates_per_half_day', 'short_hours_tolerance_minutes',
    'salary_calculation_method', 'payroll_cycle', 'payroll_period_start_day', 'salary_payment_day',
    'overtime_rate_type', 'overtime_multiplier', 'overtime_fixed_rate', 'overtime_from_attendance',
    'short_hours_mode', 'short_hours_rate_type', 'short_hours_fixed_rate', 'borrow_auto_deduct',
    'borrow_max_deduction_percent',
])]
class CompanySetting extends Model
{
    use BelongsToCompany;

    public const string OVERTIME_RATE_MULTIPLIER = 'multiplier';

    public const string OVERTIME_RATE_FIXED = 'fixed';

    public const string SHORT_HOURS_RATE_SALARY = 'salary_hourly';

    public const string SHORT_HOURS_RATE_FIXED = 'fixed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_mode' => AttendanceMode::class,
            'salary_calculation_method' => SalaryCalculationMethod::class,
            'short_hours_mode' => ShortHoursMode::class,
            'grace_minutes' => 'integer',
            'lates_per_half_day' => 'integer',
            'short_hours_tolerance_minutes' => 'integer',
            'payroll_period_start_day' => 'integer',
            'salary_payment_day' => 'integer',
            'overtime_multiplier' => 'float',
            'overtime_fixed_rate' => 'float',
            'overtime_from_attendance' => 'boolean',
            'short_hours_fixed_rate' => 'float',
            'borrow_auto_deduct' => 'boolean',
            'borrow_max_deduction_percent' => 'float',
        ];
    }

    /**
     * @return BelongsTo<WorkShift, $this>
     */
    public function defaultWorkShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'default_work_shift_id');
    }
}
