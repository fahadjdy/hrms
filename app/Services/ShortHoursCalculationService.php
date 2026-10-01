<?php

namespace App\Services;

use App\Enums\ShortHoursMode;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\ShortHoursAdjustment;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;

/**
 * Turns short working hours into a monetary adjustment, following the
 * company's short-hours rule:
 *
 * - Deduct: the calculated amount is deducted from salary.
 * - Record only: short hours are reported but nothing is deducted.
 * - Manual: nothing is deducted until the admin enters an amount.
 *
 * In every mode an admin-adjusted amount, when present, is the final amount.
 */
class ShortHoursCalculationService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The rate one short hour is worth: the employee's hourly salary rate or
     * the company's fixed short-hours rate.
     */
    public function hourlyRate(float $salaryHourlyRate): float
    {
        $settings = $this->tenant->settings();

        if ($settings->short_hours_rate_type === CompanySetting::SHORT_HOURS_RATE_FIXED && $settings->short_hours_fixed_rate !== null) {
            return Money::round($settings->short_hours_fixed_rate);
        }

        return Money::round($salaryHourlyRate);
    }

    public function calculate(int $shortMinutes, float $hourlyRate): float
    {
        return Money::round(max(0, $shortMinutes) / 60 * $hourlyRate);
    }

    /**
     * @param  array<string, int|float>  $attendanceSummary
     * @return array{
     *     mode: string,
     *     mode_label: string,
     *     required_minutes: int,
     *     worked_minutes: int,
     *     short_minutes: int,
     *     hourly_rate: float,
     *     calculated_amount: float,
     *     adjusted_amount: float|null,
     *     adjustment_reason: string|null,
     *     final_amount: float
     * }
     */
    public function forPeriod(Employee $employee, PayrollPeriod $period, array $attendanceSummary, float $salaryHourlyRate): array
    {
        $mode = $this->tenant->settings()->short_hours_mode;
        $shortMinutes = (int) $attendanceSummary['short_minutes'];
        $rate = $this->hourlyRate($salaryHourlyRate);
        $calculated = $this->calculate($shortMinutes, $rate);

        $adjustment = ShortHoursAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('period_start', $period->start->toDateString())
            ->first();

        $final = $adjustment !== null
            ? $adjustment->adjusted_amount
            : ($mode === ShortHoursMode::Deduct ? $calculated : 0.0);

        return [
            'mode' => $mode->value,
            'mode_label' => $mode->label(),
            'required_minutes' => (int) $attendanceSummary['required_minutes'],
            'worked_minutes' => (int) $attendanceSummary['worked_minutes'],
            'short_minutes' => $shortMinutes,
            'hourly_rate' => $rate,
            'calculated_amount' => $calculated,
            'adjusted_amount' => $adjustment?->adjusted_amount,
            'adjustment_reason' => $adjustment?->reason,
            'final_amount' => Money::round($final),
        ];
    }

    /**
     * Set the admin-decided short-hours deduction for an employee and period.
     */
    public function adjust(Employee $employee, PayrollPeriod $period, float $amount, string $reason, ?User $user = null): ShortHoursAdjustment
    {
        $adjustment = ShortHoursAdjustment::query()->firstOrNew([
            'employee_id' => $employee->id,
            'period_start' => $period->start->toDateString(),
        ]);

        $old = $adjustment->exists ? ['adjusted_amount' => $adjustment->adjusted_amount, 'reason' => $adjustment->reason] : null;

        $adjustment->fill([
            'adjusted_amount' => Money::round(max(0, $amount)),
            'reason' => $reason,
            'user_id' => $user?->id,
        ])->save();

        $this->audit->log(
            'short_hours.adjusted',
            $adjustment,
            $old,
            ['adjusted_amount' => $adjustment->adjusted_amount, 'reason' => $reason, 'period' => $period->label()],
            "Short-hours deduction for {$employee->full_name} ({$period->label()}) set to {$adjustment->adjusted_amount}",
            $employee->id,
        );

        return $adjustment;
    }

    /**
     * Remove the admin adjustment so the company rule applies again.
     */
    public function resetAdjustment(Employee $employee, PayrollPeriod $period): void
    {
        $adjustment = ShortHoursAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('period_start', $period->start->toDateString())
            ->first();

        if ($adjustment === null) {
            return;
        }

        $this->audit->log(
            'short_hours.adjustment_removed',
            $adjustment,
            ['adjusted_amount' => $adjustment->adjusted_amount, 'reason' => $adjustment->reason],
            null,
            "Short-hours adjustment removed for {$employee->full_name} ({$period->label()})",
            $employee->id,
        );

        $adjustment->delete();
    }
}
