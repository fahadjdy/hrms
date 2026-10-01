<?php

namespace App\Services;

use App\Enums\AttendanceMode;
use App\Enums\PayrollBucket;
use App\Enums\SalaryCalculationMethod;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollAdjustment;
use App\Models\SalaryBonus;
use App\Models\SalaryDeduction;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Calculates one employee's pay for one payroll period.
 *
 * The result is a structured breakdown: every amount is a line with a code, a
 * bucket and a plain-language note that says how it was derived, so the admin
 * can always answer "why did this employee receive this exact salary?".
 *
 * This class only reads data. Saving, adjusting and finalizing is done by
 * PayrollService.
 */
class PayrollCalculationService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly WorkingCalendarService $calendar,
        private readonly WorkingHoursCalculationService $hours,
        private readonly AttendanceCalculationService $attendance,
        private readonly SalaryCalculationService $salary,
        private readonly ShortHoursCalculationService $shortHours,
        private readonly OvertimeCalculationService $overtime,
        private readonly BorrowCalculationService $borrows,
    ) {}

    /**
     * @param  bool  $settleBorrows  recover every outstanding borrow in full (final settlement)
     * @return array<string, mixed>
     */
    public function calculate(Employee $employee, PayrollPeriod $period, bool $settleBorrows = false): array
    {
        $settings = $this->tenant->settings();
        $employee->loadMissing(['department', 'designation']);

        $days = $this->attendance->resolveDays($employee, $period->start, $period->end);
        $summary = $this->attendance->summarize($days);

        $lastDay = $employee->last_working_date !== null && $employee->last_working_date->lt($period->end)
            ? $employee->last_working_date->max($period->start)
            : $period->end;

        $salary = $this->salary->forPeriod(
            $employee,
            $period,
            $this->calendar->workingDaysBetween($period->start, $period->end),
            $this->hours->requiredMinutesFor($employee, $lastDay),
        );

        $perDay = $salary['per_day_rate'];
        $gross = $salary['gross'];
        $lines = [];
        $warnings = [];

        if ($salary['segments'] === []) {
            $warnings[] = 'No salary is set for this period. Add a salary structure for this employee.';
        }

        // Salary: one line per earning component.
        foreach ($salary['components'] as $component) {
            if ($component['type'] !== EmployeeSalaryComponent::TYPE_EARNING) {
                continue;
            }

            $lines[] = $this->line(
                'salary.'.$component['code'],
                $component['name'],
                PayrollBucket::Salary,
                $component['amount'],
                count($salary['segments']) > 1 ? 'Weighted across '.count($salary['segments']).' salary revisions in this period' : 'Monthly salary component',
            );
        }

        // Attendance-based deductions never take more than the gross salary.
        $deductible = $gross;
        $unit = $salary['method'] === SalaryCalculationMethod::WorkingDays->value ? 'working day' : 'day';

        $unpaidStartDays = $this->daysWithoutSalary($days, $salary['salary_starts_on'], $salary['method']);
        $notEmployedDays = ($salary['method'] === SalaryCalculationMethod::WorkingDays->value
            ? $summary['not_employed_working_days']
            : $summary['not_employed_days']) + $unpaidStartDays;

        if ($notEmployedDays > 0) {
            $lines[] = $this->deduction(
                'attendance.not_employed',
                'Days Not Employed',
                PayrollBucket::AttendanceDeduction,
                $notEmployedDays * $perDay,
                $deductible,
                "{$notEmployedDays} {$unit}(s) outside the employment or salary dates x {$this->format($perDay)} per day",
                ['days' => $notEmployedDays],
            );
        }

        if ($summary['absent'] > 0) {
            $lines[] = $this->deduction(
                'attendance.absent',
                'Absent Deduction',
                PayrollBucket::AttendanceDeduction,
                $summary['absent'] * $perDay,
                $deductible,
                "{$summary['absent']} absent day(s) x {$this->format($perDay)} per day",
                ['days' => $summary['absent']],
            );
        }

        if ($summary['unmarked'] > 0 && $settings->attendance_mode === AttendanceMode::Manual) {
            $lines[] = $this->deduction(
                'attendance.unmarked',
                'Unmarked Days',
                PayrollBucket::AttendanceDeduction,
                $summary['unmarked'] * $perDay,
                $deductible,
                "{$summary['unmarked']} working day(s) without attendance, treated as absent x {$this->format($perDay)} per day",
                ['days' => $summary['unmarked']],
            );
            $warnings[] = "{$summary['unmarked']} working day(s) have no attendance and are treated as absent.";
        }

        if ($summary['half_day'] > 0) {
            $lines[] = $this->deduction(
                'attendance.half_day',
                'Half Day Deduction',
                PayrollBucket::AttendanceDeduction,
                $summary['half_day'] * 0.5 * $perDay,
                $deductible,
                "{$summary['half_day']} half day(s) x half of {$this->format($perDay)} per day",
                ['days' => $summary['half_day']],
            );
        }

        if ($settings->lates_per_half_day > 0 && $summary['late'] >= $settings->lates_per_half_day) {
            $penalties = intdiv((int) $summary['late'], $settings->lates_per_half_day);

            $lines[] = $this->deduction(
                'attendance.late',
                'Late Arrival Deduction',
                PayrollBucket::AttendanceDeduction,
                $penalties * 0.5 * $perDay,
                $deductible,
                "{$summary['late']} late day(s): every {$settings->lates_per_half_day} late arrivals deduct half a day x {$this->format($perDay)}",
                ['late_days' => $summary['late'], 'penalties' => $penalties],
            );
        }

        if ($summary['unpaid_leave'] > 0) {
            $lines[] = $this->deduction(
                'leave.unpaid',
                'Unpaid Leave',
                PayrollBucket::UnpaidLeave,
                $summary['unpaid_leave'] * $perDay,
                $deductible,
                "{$summary['unpaid_leave']} unpaid leave day(s) x {$this->format($perDay)} per day",
                ['days' => $summary['unpaid_leave']],
            );
        }

        // Short hours.
        $shortHours = $this->shortHours->forPeriod($employee, $period, $summary, $salary['hourly_rate']);

        if ($shortHours['short_minutes'] > 0 || $shortHours['final_amount'] > 0) {
            $lines[] = $this->line(
                'short_hours',
                'Short Hours Deduction',
                PayrollBucket::ShortHours,
                $shortHours['final_amount'],
                $this->shortHoursNote($shortHours),
                $shortHours,
            );
        }

        // Overtime.
        $overtime = $this->overtime->forPeriod($employee, $period, $days, $salary['hourly_rate']);

        foreach ($overtime['entries'] as $entry) {
            $lines[] = $this->line(
                'overtime.'.$entry['id'],
                'Overtime',
                PayrollBucket::Overtime,
                $entry['amount'],
                $entry['calculation_type'] === 'fixed'
                    ? "Fixed overtime amount on {$entry['date']}"
                    : "{$this->trim($entry['hours'])} hour(s) x {$this->format((float) $entry['rate'])} on {$entry['date']}",
                ['overtime_id' => $entry['id'], 'reason' => $entry['reason']],
            );
        }

        if ($overtime['attendance_amount'] > 0) {
            $lines[] = $this->line(
                'overtime.attendance',
                'Overtime (from attendance)',
                PayrollBucket::Overtime,
                $overtime['attendance_amount'],
                $this->minutes($overtime['attendance_minutes'])." of overtime in attendance x {$this->format($overtime['attendance_rate'])} per hour",
                ['minutes' => $overtime['attendance_minutes']],
            );
        }

        // Bonuses and other one-off earnings.
        foreach ($this->unpaidBonuses($employee, $period) as $bonus) {
            $isBonus = $bonus->type === SalaryBonus::TYPE_BONUS;

            $lines[] = $this->line(
                ($isBonus ? 'bonus.' : 'other_earning.').$bonus->id,
                $bonus->title,
                $isBonus ? PayrollBucket::Bonus : PayrollBucket::OtherEarnings,
                $bonus->amount,
                ($isBonus ? 'Bonus' : 'Other earning')." dated {$bonus->date->toDateString()}".($bonus->reason ? ": {$bonus->reason}" : ''),
                ['bonus_id' => $bonus->id],
            );
        }

        // Recurring deductions from the salary structure, then one-off deductions.
        foreach ($salary['components'] as $component) {
            if ($component['type'] !== EmployeeSalaryComponent::TYPE_DEDUCTION) {
                continue;
            }

            $lines[] = $this->line(
                'deduction.component.'.$component['code'],
                $component['name'],
                PayrollBucket::OtherDeductions,
                $component['amount'],
                'Recurring deduction from the salary structure',
            );
        }

        foreach ($this->unpaidDeductions($employee, $period) as $deduction) {
            $lines[] = $this->line(
                'deduction.'.$deduction->id,
                $deduction->title,
                PayrollBucket::OtherDeductions,
                $deduction->amount,
                "Deduction dated {$deduction->date->toDateString()}".($deduction->reason ? ": {$deduction->reason}" : ''),
                ['deduction_id' => $deduction->id],
            );
        }

        // Borrow recovery is limited to what is left of the pay after all other lines.
        $netBeforeBorrow = $this->net($lines);
        $recoveries = $settleBorrows
            ? $this->settlementRecoveries($employee)
            : $this->limitRecoveries($this->borrows->dueForPeriod($employee, $period), $netBeforeBorrow, $settings->borrow_max_deduction_percent);

        foreach ($recoveries as $recovery) {
            $lines[] = $this->line(
                'borrow_recovery.'.$recovery['borrow_id'],
                "Borrow Recovery ({$recovery['reference_no']})",
                PayrollBucket::BorrowRecovery,
                $recovery['amount'],
                $recovery['note'],
                ['borrow_id' => $recovery['borrow_id'], 'outstanding_before' => $recovery['outstanding']],
            );
        }

        // A new borrow is an advance paid with salary. It is never salary income.
        $given = $settleBorrows ? [] : $this->borrows->givenWithPeriod($employee, $period);

        foreach ($given as $borrow) {
            $lines[] = $this->line(
                'borrow_given.'.$borrow['borrow_id'],
                "New Borrow / Advance ({$borrow['reference_no']})",
                PayrollBucket::BorrowGiven,
                $borrow['amount'],
                'Advance paid out with this salary'.($borrow['reason'] ? ": {$borrow['reason']}" : ''),
                ['borrow_id' => $borrow['borrow_id']],
            );
        }

        return [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'code' => $employee->employee_code,
                'department_id' => $employee->department_id,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
            ],
            'period' => $period->toArray(),
            'salary' => $salary,
            'attendance' => $summary,
            'short_hours' => $shortHours,
            'overtime' => $overtime,
            'lines' => $lines,
            'borrows' => [
                'recoveries' => array_map(
                    fn (array $recovery): array => ['borrow_id' => $recovery['borrow_id'], 'reference_no' => $recovery['reference_no'], 'amount' => $recovery['amount']],
                    $recoveries,
                ),
                'given' => $given,
            ],
            'buckets' => self::buckets($lines, collect()),
            'warnings' => $warnings,
        ];
    }

    /**
     * System, adjustment and final amounts for every bucket.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  Collection<int, PayrollAdjustment>  $adjustments
     * @return array<string, array{label: string, system: float, adjustment: float, final: float}>
     */
    public static function buckets(array $lines, Collection $adjustments): array
    {
        $buckets = [];

        foreach (PayrollBucket::cases() as $bucket) {
            $system = Money::sum(array_column(
                array_filter($lines, fn (array $line): bool => $line['bucket'] === $bucket->value),
                'amount',
            ));
            $adjustment = Money::sum(
                $adjustments->filter(fn ($adjustment): bool => $adjustment->bucket === $bucket)->pluck('amount'),
            );

            $buckets[$bucket->value] = [
                'label' => $bucket->label(),
                'system' => $system,
                'adjustment' => $adjustment,
                'final' => Money::round($system + $adjustment),
            ];
        }

        return $buckets;
    }

    /**
     * The breakdown lines stored on a payroll item or a final settlement.
     *
     * @param  array<string, mixed>|null  $breakdown
     * @return list<array{code: string, label: string, bucket: string, amount: float|int, note: string, meta: array<string, mixed>}>
     */
    public static function linesOf(?array $breakdown): array
    {
        return array_values($breakdown['lines'] ?? []);
    }

    /**
     * The system, adjustment and final amounts per bucket stored on a payroll item.
     *
     * @param  array<string, mixed>|null  $breakdown
     * @return array<string, array{label: string, system: float|int, adjustment: float|int, final: float|int}>
     */
    public static function bucketsOf(?array $breakdown): array
    {
        return $breakdown['buckets'] ?? [];
    }

    /**
     * Totals derived from the final bucket amounts.
     *
     * @param  array<string, array{label: string, system: float, adjustment: float, final: float}>  $buckets
     * @return array{total_earnings: float, total_deductions: float, net_salary: float, borrow_given: float, net_payable: float}
     */
    public static function totals(array $buckets): array
    {
        $earnings = 0.0;
        $deductions = 0.0;

        foreach (PayrollBucket::cases() as $bucket) {
            if ($bucket->isEarning()) {
                $earnings += $buckets[$bucket->value]['final'];
            } elseif ($bucket->isDeduction()) {
                $deductions += $buckets[$bucket->value]['final'];
            }
        }

        $given = $buckets[PayrollBucket::BorrowGiven->value]['final'];
        $netSalary = Money::round($earnings - $deductions);

        return [
            'total_earnings' => Money::round($earnings),
            'total_deductions' => Money::round($deductions),
            'net_salary' => $netSalary,
            'borrow_given' => $given,
            'net_payable' => Money::round($netSalary + $given),
        ];
    }

    /**
     * Employed days that fall before the first salary revision starts.
     *
     * @param  list<array<string, mixed>>  $days
     */
    private function daysWithoutSalary(array $days, ?string $salaryStartsOn, string $method): int
    {
        if ($salaryStartsOn === null) {
            return 0;
        }

        $count = 0;

        foreach ($days as $day) {
            if ($day['state'] === AttendanceCalculationService::STATE_NOT_EMPLOYED || $day['date'] >= $salaryStartsOn) {
                continue;
            }

            if ($method === SalaryCalculationMethod::WorkingDays->value && ! $day['is_working_day']) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Earnings minus deductions over the lines gathered so far.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function net(array $lines): float
    {
        $net = 0.0;

        foreach ($lines as $line) {
            $net += PayrollBucket::from($line['bucket'])->sign() * $line['amount'];
        }

        return Money::round($net);
    }

    /**
     * Cap scheduled recoveries so pay never goes below zero, and apply the
     * company's maximum recovery percentage when one is set.
     *
     * @param  list<array{borrow_id: int, reference_no: string, amount: float, outstanding: float}>  $due
     * @return list<array{borrow_id: int, reference_no: string, amount: float, outstanding: float, note: string}>
     */
    private function limitRecoveries(array $due, float $netBeforeBorrow, ?float $maxPercent): array
    {
        $available = max(0, $netBeforeBorrow);

        if ($maxPercent !== null && $maxPercent > 0) {
            $available = min($available, Money::round($netBeforeBorrow * $maxPercent / 100));
        }

        $recoveries = [];

        foreach ($due as $item) {
            $amount = Money::round(min($item['amount'], max(0, $available)));
            $available = Money::round($available - $amount);

            if ($amount <= 0) {
                continue;
            }

            $note = "Monthly deduction against an outstanding balance of {$this->format($item['outstanding'])}";

            if ($amount < $item['amount']) {
                $note .= ". Limited from {$this->format($item['amount'])} so the recovery stays within the allowed pay";
            }

            $recoveries[] = [...$item, 'amount' => $amount, 'note' => $note];
        }

        return $recoveries;
    }

    /**
     * @return list<array{borrow_id: int, reference_no: string, amount: float, outstanding: float, note: string}>
     */
    private function settlementRecoveries(Employee $employee): array
    {
        return array_values($employee->borrows()
            ->outstanding()
            ->orderBy('borrow_date')
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeBorrow $borrow): array => [
                'borrow_id' => $borrow->id,
                'reference_no' => $borrow->reference_no,
                'amount' => $borrow->outstanding_amount,
                'outstanding' => $borrow->outstanding_amount,
                'note' => 'Full outstanding balance recovered in the final settlement',
            ])
            ->all());
    }

    /**
     * @return Collection<int, SalaryBonus>
     */
    private function unpaidBonuses(Employee $employee, PayrollPeriod $period): Collection
    {
        return SalaryBonus::query()
            ->where('employee_id', $employee->id)
            ->whereNull('payroll_item_id')
            ->where('date', '<=', $period->end->toDateString())
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SalaryDeduction>
     */
    private function unpaidDeductions(Employee $employee, PayrollPeriod $period): Collection
    {
        return SalaryDeduction::query()
            ->where('employee_id', $employee->id)
            ->whereNull('payroll_item_id')
            ->where('date', '<=', $period->end->toDateString())
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{code: string, label: string, bucket: string, amount: float, note: string, meta: array<string, mixed>}
     */
    private function line(string $code, string $label, PayrollBucket $bucket, float $amount, string $note, array $meta = []): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'bucket' => $bucket->value,
            'amount' => Money::round($amount),
            'note' => $note,
            'meta' => $meta,
        ];
    }

    /**
     * A deduction line limited to what is still deductible from the gross salary.
     *
     * @param  array<string, mixed>  $meta
     * @return array{code: string, label: string, bucket: string, amount: float, note: string, meta: array<string, mixed>}
     */
    private function deduction(string $code, string $label, PayrollBucket $bucket, float $amount, float &$deductible, string $note, array $meta = []): array
    {
        $amount = Money::round($amount);
        $limited = Money::round(min($amount, max(0, $deductible)));
        $deductible = Money::round($deductible - $limited);

        if ($limited < $amount) {
            $note .= ' (limited to the remaining gross salary)';
        }

        return $this->line($code, $label, $bucket, $limited, $note, $meta);
    }

    /**
     * @param  array<string, mixed>  $shortHours
     */
    private function shortHoursNote(array $shortHours): string
    {
        $base = $this->minutes($shortHours['short_minutes'])." short x {$this->format($shortHours['hourly_rate'])} per hour = {$this->format($shortHours['calculated_amount'])}";

        if ($shortHours['adjusted_amount'] !== null) {
            return "{$base}. Admin set the deduction to {$this->format($shortHours['adjusted_amount'])}: {$shortHours['adjustment_reason']}";
        }

        return match ($shortHours['mode']) {
            'deduct' => $base,
            'manual' => "{$base}. Not deducted until an admin sets an amount",
            default => "{$base}. Recorded only, not deducted",
        };
    }

    private function format(float $amount): string
    {
        return number_format($amount, 2);
    }

    private function trim(float|int|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }

    private function minutes(int $minutes): string
    {
        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
