<?php

namespace App\Services;

use App\Enums\OvertimeStatus;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Overtime;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;

class OvertimeCalculationService
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * The amount of one overtime entry: hours x rate, or the fixed amount.
     */
    public function entryAmount(string $calculationType, ?float $hours, ?float $rate, ?float $fixedAmount): float
    {
        if ($calculationType === Overtime::TYPE_FIXED) {
            return Money::round($fixedAmount);
        }

        return Money::round((float) $hours * (float) $rate);
    }

    /**
     * The company's overtime rate per hour for an employee with the given hourly salary rate.
     */
    public function defaultRate(float $salaryHourlyRate): float
    {
        $settings = $this->tenant->settings();

        if ($settings->overtime_rate_type === CompanySetting::OVERTIME_RATE_FIXED && $settings->overtime_fixed_rate !== null) {
            return Money::round($settings->overtime_fixed_rate);
        }

        return Money::round($salaryHourlyRate * $settings->overtime_multiplier);
    }

    /**
     * Overtime payable in a period: approved, unpaid overtime entries dated up
     * to the period end, plus (when the company enables it) overtime worked
     * according to attendance on days without an entry.
     *
     * @param  list<array<string, mixed>>  $days  resolved attendance days of the period
     * @return array{
     *     entries: list<array{id: int, date: string, calculation_type: string, hours: float|null, rate: float|null, amount: float, reason: string|null}>,
     *     entries_total: float,
     *     attendance_minutes: int,
     *     attendance_rate: float,
     *     attendance_amount: float,
     *     total: float
     * }
     */
    public function forPeriod(Employee $employee, PayrollPeriod $period, array $days, float $salaryHourlyRate): array
    {
        $entries = Overtime::query()
            ->where('employee_id', $employee->id)
            ->where('status', OvertimeStatus::Approved->value)
            ->whereNull('payroll_item_id')
            ->where('date', '<=', $period->end->toDateString())
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $entryDates = $entries->map(fn (Overtime $entry): string => $entry->date->toDateString())->all();
        $attendanceMinutes = 0;
        $attendanceRate = 0.0;

        if ($this->tenant->settings()->overtime_from_attendance) {
            $attendanceRate = $this->defaultRate($salaryHourlyRate);

            foreach ($days as $day) {
                if (! in_array($day['date'], $entryDates, true)) {
                    $attendanceMinutes += (int) $day['overtime_minutes'];
                }
            }
        }

        $entriesTotal = Money::sum($entries->pluck('amount'));
        $attendanceAmount = Money::round($attendanceMinutes / 60 * $attendanceRate);

        return [
            'entries' => array_values($entries->map(fn (Overtime $entry): array => [
                'id' => $entry->id,
                'date' => $entry->date->toDateString(),
                'calculation_type' => $entry->calculation_type,
                'hours' => $entry->hours,
                'rate' => $entry->rate,
                'amount' => $entry->amount,
                'reason' => $entry->reason,
            ])->all()),
            'entries_total' => $entriesTotal,
            'attendance_minutes' => $attendanceMinutes,
            'attendance_rate' => $attendanceRate,
            'attendance_amount' => $attendanceAmount,
            'total' => Money::round($entriesTotal + $attendanceAmount),
        ];
    }
}
