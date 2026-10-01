<?php

namespace App\Services;

use App\Enums\BorrowTransactionType;
use App\Enums\OvertimeStatus;
use App\Enums\PayrollBucket;
use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\FinalSettlement;
use App\Models\Overtime;
use App\Models\PayrollItem;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Final settlement for an employee who left:
 *
 *   last salary + overtime + bonus + other earnings
 *   - unpaid leave - short hours - outstanding borrow - other deductions
 *   +/- admin adjustment
 */
class FinalSettlementService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PayrollCalculationService $calculator,
        private readonly BorrowCalculationService $borrows,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create or refresh the draft settlement from current data.
     */
    public function prepare(Employee $employee, ?User $user = null): FinalSettlement
    {
        if (! $employee->isPast() || $employee->last_working_date === null) {
            throw ValidationException::withMessages([
                'employee' => 'A final settlement is prepared after the employee has left the company.',
            ]);
        }

        $settlement = FinalSettlement::query()->firstOrNew(['employee_id' => $employee->id]);

        if ($settlement->exists && $settlement->isLocked()) {
            throw ValidationException::withMessages([
                'settlement' => 'This final settlement is finalized and can no longer be recalculated.',
            ]);
        }

        $period = PayrollPeriod::containing($employee->last_working_date, $this->tenant->settings()->payroll_period_start_day);
        $result = $this->calculator->calculate($employee, $period, settleBorrows: true);
        $buckets = $result['buckets'];

        // When the last period was already paid through a finalized payroll, only
        // items that payroll did not cover remain to be settled.
        $alreadyPaid = PayrollItem::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payroll', fn ($query) => $query
                ->where('period_year', $period->year)
                ->where('period_month', $period->month)
                ->where('status', PayrollStatus::Finalized->value))
            ->exists();

        $amount = fn (PayrollBucket $bucket): float => $buckets[$bucket->value]['final'];

        $lastSalary = $alreadyPaid
            ? 0.0
            : Money::round($amount(PayrollBucket::Salary) - $amount(PayrollBucket::AttendanceDeduction));

        // Recurring salary-structure deductions were taken by that payroll too.
        $otherDeductions = $alreadyPaid
            ? Money::sum(collect(PayrollCalculationService::linesOf($result))
                ->filter(fn (array $line): bool => isset($line['meta']['deduction_id']))
                ->pluck('amount'))
            : $amount(PayrollBucket::OtherDeductions);

        $settlement->fill([
            'period_start' => $period->start->toDateString(),
            'period_end' => $employee->last_working_date->min($period->end)->toDateString(),
            'last_salary' => $lastSalary,
            'overtime_amount' => $amount(PayrollBucket::Overtime),
            'bonus_amount' => $amount(PayrollBucket::Bonus),
            'other_earnings_amount' => $amount(PayrollBucket::OtherEarnings),
            'unpaid_leave_deduction' => $alreadyPaid ? 0.0 : $amount(PayrollBucket::UnpaidLeave),
            'short_hours_deduction' => $alreadyPaid ? 0.0 : $amount(PayrollBucket::ShortHours),
            'outstanding_borrow' => $amount(PayrollBucket::BorrowRecovery),
            'other_deductions' => $otherDeductions,
            'breakdown' => [...$result, 'salary_already_paid' => $alreadyPaid],
            'created_by' => $settlement->created_by ?? $user?->id,
        ]);
        $settlement->net_amount = $this->net($settlement);
        $settlement->save();

        return $settlement;
    }

    /**
     * Set the admin's manual adjustment on a draft settlement.
     */
    public function adjust(FinalSettlement $settlement, float $amount, ?string $reason, ?string $notes = null): FinalSettlement
    {
        $this->guardDraft($settlement);

        $old = $settlement->only(['adjustment_amount', 'adjustment_reason', 'net_amount']);

        $settlement->adjustment_amount = Money::round($amount);
        $settlement->adjustment_reason = $reason;
        $settlement->notes = $notes;
        $settlement->net_amount = $this->net($settlement);
        $settlement->save();

        $this->audit->log(
            'settlement.adjusted',
            $settlement,
            $old,
            $settlement->only(['adjustment_amount', 'adjustment_reason', 'net_amount']),
            "Final settlement for {$settlement->employee->full_name} adjusted by {$settlement->adjustment_amount}",
            $settlement->employee_id,
        );

        return $settlement;
    }

    /**
     * Lock the settlement and recover the outstanding borrows through the ledger.
     */
    public function finalize(FinalSettlement $settlement, ?User $user = null): FinalSettlement
    {
        $this->guardDraft($settlement);

        return DB::transaction(function () use ($settlement, $user): FinalSettlement {
            $today = $this->tenant->today();
            $lines = collect(PayrollCalculationService::linesOf($settlement->breakdown));

            foreach ($settlement->breakdown['borrows']['recoveries'] ?? [] as $recovery) {
                $borrow = EmployeeBorrow::query()->findOrFail((int) $recovery['borrow_id']);

                $this->borrows->recover(
                    $borrow,
                    $recovery['amount'],
                    $today,
                    BorrowTransactionType::Settlement,
                    notes: 'Recovered in the final settlement',
                    user: $user,
                );
            }

            Overtime::query()
                ->whereIn('id', $lines->pluck('meta.overtime_id')->filter()->all())
                ->update(['status' => OvertimeStatus::Paid->value]);

            $settlement->status = FinalSettlement::STATUS_FINALIZED;
            $settlement->finalized_at = now();
            $settlement->finalized_by = $user?->id;
            $settlement->save();

            $this->audit->log(
                'settlement.finalized',
                $settlement,
                null,
                ['net_amount' => $settlement->net_amount, 'outstanding_borrow' => $settlement->outstanding_borrow],
                "Final settlement for {$settlement->employee->full_name} finalized at {$settlement->net_amount}",
                $settlement->employee_id,
            );

            return $settlement;
        });
    }

    public function markPaid(FinalSettlement $settlement): FinalSettlement
    {
        if ($settlement->status !== FinalSettlement::STATUS_FINALIZED) {
            throw ValidationException::withMessages(['settlement' => 'Finalize the settlement before marking it as paid.']);
        }

        $settlement->status = FinalSettlement::STATUS_PAID;
        $settlement->paid_at = now();
        $settlement->save();

        $this->audit->log(
            'settlement.paid',
            $settlement,
            ['status' => FinalSettlement::STATUS_FINALIZED],
            ['status' => FinalSettlement::STATUS_PAID],
            "Final settlement for {$settlement->employee->full_name} marked as paid",
            $settlement->employee_id,
        );

        return $settlement;
    }

    private function net(FinalSettlement $settlement): float
    {
        return Money::round(
            $settlement->last_salary
            + $settlement->overtime_amount
            + $settlement->bonus_amount
            + $settlement->other_earnings_amount
            - $settlement->unpaid_leave_deduction
            - $settlement->short_hours_deduction
            - $settlement->outstanding_borrow
            - $settlement->other_deductions
            + $settlement->adjustment_amount,
        );
    }

    private function guardDraft(FinalSettlement $settlement): void
    {
        if ($settlement->isLocked()) {
            throw ValidationException::withMessages([
                'settlement' => 'This final settlement is finalized and can no longer be changed.',
            ]);
        }
    }
}
