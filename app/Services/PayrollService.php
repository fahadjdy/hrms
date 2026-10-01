<?php

namespace App\Services;

use App\Enums\OvertimeStatus;
use App\Enums\PayrollBucket;
use App\Enums\PayrollStatus;
use App\Jobs\GenerateSalarySlips;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\Overtime;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\SalaryBonus;
use App\Models\SalaryDeduction;
use App\Models\SalarySlip;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The payroll workflow: Draft -> Calculated -> Admin Review -> Adjusted -> Finalized.
 *
 * A finalized payroll is locked. Nothing in it can change until it is
 * reopened, and reopening is audited and reverses the borrow postings that
 * finalizing made.
 */
class PayrollService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PayrollCalculationService $calculator,
        private readonly BorrowCalculationService $borrows,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Start a payroll for a month. Each month has at most one payroll per company.
     */
    public function create(int $year, int $month, ?User $user = null, ?string $notes = null): Payroll
    {
        if (Payroll::query()->where('period_year', $year)->where('period_month', $month)->exists()) {
            throw ValidationException::withMessages([
                'month' => 'A payroll already exists for this month.',
            ]);
        }

        $period = PayrollPeriod::forMonth($year, $month, $this->tenant->settings()->payroll_period_start_day);

        $payroll = Payroll::query()->create([
            'period_year' => $year,
            'period_month' => $month,
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
            'notes' => $notes,
            'created_by' => $user?->id,
        ]);

        $this->audit->log('payroll.created', $payroll, null, ['period' => $period->label()], "Payroll created for {$period->label()}");

        return $payroll;
    }

    /**
     * Calculate (or recalculate) every eligible employee. Manual adjustments
     * already made are kept and applied on top of the new system amounts.
     */
    public function calculate(Payroll $payroll): Payroll
    {
        $this->guardUnlocked($payroll);

        $period = $payroll->period();
        $calculatedIds = [];

        Employee::query()
            ->current()
            ->where('joining_date', '<=', $period->end->toDateString())
            ->with(['department', 'designation', 'shiftAssignments', 'salaryRevisions.components'])
            ->chunkById(100, function (Collection $employees) use ($payroll, $period, &$calculatedIds): void {
                $items = PayrollItem::query()
                    ->where('payroll_id', $payroll->id)
                    ->whereIn('employee_id', $employees->modelKeys())
                    ->with('adjustments')
                    ->get()
                    ->keyBy('employee_id');

                foreach ($employees as $employee) {
                    $result = $this->calculator->calculate($employee, $period);
                    $item = $items->get($employee->id) ?? new PayrollItem([
                        'payroll_id' => $payroll->id,
                        'employee_id' => $employee->id,
                    ]);

                    $this->fill($item, $result, $item->exists ? $item->adjustments : new Collection);
                    $item->save();

                    $calculatedIds[] = $employee->id;
                }
            });

        // Employees who left or were removed since the last calculation.
        PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->whereNotIn('employee_id', $calculatedIds)
            ->delete();

        $hasAdjustments = PayrollAdjustment::query()
            ->whereIn('payroll_item_id', PayrollItem::query()->where('payroll_id', $payroll->id)->select('id'))
            ->exists();

        $payroll->status = $hasAdjustments ? PayrollStatus::Adjusted : PayrollStatus::Calculated;
        $payroll->calculated_at = now();
        $payroll->save();

        $this->refreshTotals($payroll);

        $this->audit->log(
            'payroll.calculated',
            $payroll,
            null,
            ['employees' => count($calculatedIds), 'net_payable' => $payroll->total_net_payable],
            "Payroll for {$payroll->label()} calculated for ".count($calculatedIds).' employee(s)',
        );

        return $payroll;
    }

    public function markUnderReview(Payroll $payroll): Payroll
    {
        $this->guardUnlocked($payroll);
        $this->guardCalculated($payroll);

        $payroll->status = PayrollStatus::UnderReview;
        $payroll->save();

        $this->audit->log('payroll.review_started', $payroll, null, null, "Payroll for {$payroll->label()} moved to admin review");

        return $payroll;
    }

    /**
     * Add a manual adjustment to one bucket of a payroll item.
     *
     * The amount is a signed change to the bucket: +500 on a deduction bucket
     * deducts 500 more, -500 deducts 500 less.
     */
    public function addAdjustment(PayrollItem $item, PayrollBucket $bucket, float $amount, string $reason, ?User $user = null): PayrollAdjustment
    {
        $payroll = $item->payroll;
        $this->guardUnlocked($payroll);

        $amount = Money::round($amount);

        if ($amount == 0.0) {
            throw ValidationException::withMessages(['amount' => 'Enter an adjustment amount other than zero.']);
        }

        if (! in_array($bucket, PayrollBucket::adjustable(), true)) {
            throw ValidationException::withMessages([
                'bucket' => 'A new borrow is changed on the borrow itself, not by a payroll adjustment.',
            ]);
        }

        return DB::transaction(function () use ($item, $payroll, $bucket, $amount, $reason, $user): PayrollAdjustment {
            $current = $item->breakdown['buckets'][$bucket->value] ?? ['system' => 0.0, 'adjustment' => 0.0, 'final' => 0.0];
            $final = Money::round($current['final'] + $amount);

            if ($final < 0) {
                throw ValidationException::withMessages([
                    'amount' => "This adjustment would make {$bucket->label()} negative. The largest reduction possible is {$current['final']}.",
                ]);
            }

            // Over-recovery prevention: never recover more than the employee owes.
            if ($bucket === PayrollBucket::BorrowRecovery) {
                $outstanding = $this->borrows->outstandingFor($item->employee);

                if ($final > $outstanding + 0.001) {
                    throw ValidationException::withMessages([
                        'amount' => "Borrow recovery of {$final} would be more than the outstanding borrow of {$outstanding}.",
                    ]);
                }
            }

            $adjustment = PayrollAdjustment::query()->create([
                'payroll_item_id' => $item->id,
                'bucket' => $bucket,
                'amount' => $amount,
                'reason' => $reason,
                'user_id' => $user?->id,
            ]);

            $this->reapply($item);

            $payroll->status = PayrollStatus::Adjusted;
            $payroll->save();
            $this->refreshTotals($payroll);

            $this->audit->log(
                $bucket === PayrollBucket::BorrowRecovery ? 'payroll.borrow_recovery_adjusted' : 'payroll.adjusted',
                $item,
                ['bucket' => $bucket->value, 'amount' => $current['final']],
                ['bucket' => $bucket->value, 'amount' => $final, 'adjustment' => $amount, 'reason' => $reason],
                "{$bucket->label()} for {$item->employee_name} adjusted by {$amount} ({$payroll->label()})",
                $item->employee_id,
            );

            return $adjustment;
        });
    }

    public function removeAdjustment(PayrollAdjustment $adjustment): void
    {
        $item = $adjustment->payrollItem;
        $payroll = $item->payroll;
        $this->guardUnlocked($payroll);

        DB::transaction(function () use ($adjustment, $item, $payroll): void {
            $this->audit->log(
                'payroll.adjustment_removed',
                $item,
                ['bucket' => $adjustment->bucket->value, 'adjustment' => $adjustment->amount, 'reason' => $adjustment->reason],
                null,
                "Adjustment of {$adjustment->amount} on {$adjustment->bucket->label()} removed for {$item->employee_name} ({$payroll->label()})",
                $item->employee_id,
            );

            $adjustment->delete();
            $this->reapply($item);
            $this->refreshTotals($payroll);
        });
    }

    /**
     * Lock the payroll and post its effects: borrows are paid out and
     * recovered, and the overtime, bonuses and deductions it included are
     * marked as paid.
     */
    public function finalize(Payroll $payroll, ?User $user = null): Payroll
    {
        $this->guardUnlocked($payroll);
        $this->guardCalculated($payroll);

        try {
            DB::transaction(function () use ($payroll, $user): void {
                $today = $this->tenant->today();
                $periodMonth = CarbonImmutable::create($payroll->period_year, $payroll->period_month, 1);

                PayrollItem::query()
                    ->where('payroll_id', $payroll->id)
                    ->with('employee')
                    ->chunkById(100, function (Collection $items) use ($payroll, $user, $today, $periodMonth): void {
                        foreach ($items as $item) {
                            $this->post($item, $payroll, $today, $periodMonth, $user);
                        }
                    });

                $payroll->status = PayrollStatus::Finalized;
                $payroll->finalized_at = now();
                $payroll->finalized_by = $user?->id;
                $payroll->save();

                $this->audit->log(
                    'payroll.finalized',
                    $payroll,
                    null,
                    ['net_payable' => $payroll->total_net_payable, 'employees' => $payroll->employee_count],
                    "Payroll for {$payroll->label()} finalized",
                );
            });
        } catch (ValidationException $exception) {
            // A borrow changed after the payroll was calculated (repaid, cancelled…), so
            // the reviewed figures can no longer be posted. Nothing was saved; the
            // admin is told, on the payroll itself, to recalculate.
            throw ValidationException::withMessages([
                'payroll' => collect($exception->errors())->flatten()->first().' Recalculate the payroll and try again.',
            ]);
        }

        GenerateSalarySlips::dispatch($payroll->company_id, $payroll->id);

        return $payroll;
    }

    /**
     * Unlock a finalized payroll. Everything finalizing posted is reversed.
     */
    public function reopen(Payroll $payroll, string $reason, ?User $user = null): Payroll
    {
        if (! $payroll->isLocked()) {
            throw ValidationException::withMessages(['payroll' => 'Only a finalized payroll can be reopened.']);
        }

        DB::transaction(function () use ($payroll, $reason, $user): void {
            PayrollItem::query()
                ->where('payroll_id', $payroll->id)
                ->chunkById(100, function (Collection $items) use ($user): void {
                    foreach ($items as $item) {
                        $this->borrows->reversePayrollPostings($item, $user);
                    }

                    $ids = $items->modelKeys();

                    Overtime::query()->whereIn('payroll_item_id', $ids)
                        ->update(['payroll_item_id' => null, 'status' => OvertimeStatus::Approved->value]);
                    SalaryBonus::query()->whereIn('payroll_item_id', $ids)->update(['payroll_item_id' => null]);
                    SalaryDeduction::query()->whereIn('payroll_item_id', $ids)->update(['payroll_item_id' => null]);
                });

            $slips = SalarySlip::query()->where('payroll_id', $payroll->id)->get();
            Storage::disk('local')->delete($slips->pluck('file_path')->filter()->all());
            SalarySlip::query()->where('payroll_id', $payroll->id)->delete();

            $payroll->status = PayrollStatus::UnderReview;
            $payroll->reopened_at = now();
            $payroll->reopened_by = $user?->id;
            $payroll->reopen_reason = $reason;
            $payroll->finalized_at = null;
            $payroll->finalized_by = null;
            $payroll->save();

            $this->audit->log(
                'payroll.reopened',
                $payroll,
                ['status' => PayrollStatus::Finalized->value],
                ['status' => PayrollStatus::UnderReview->value, 'reason' => $reason],
                "Payroll for {$payroll->label()} reopened: {$reason}",
            );
        });

        return $payroll;
    }

    /**
     * Delete a payroll that has not been finalized.
     */
    public function delete(Payroll $payroll): void
    {
        $this->guardUnlocked($payroll);

        $this->audit->log('payroll.deleted', $payroll, ['period' => $payroll->label()], null, "Payroll for {$payroll->label()} deleted");

        $payroll->delete();
    }

    public function refreshTotals(Payroll $payroll): void
    {
        $totals = PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->selectRaw('count(*) as employee_count')
            ->selectRaw('coalesce(sum(gross_salary), 0) as gross')
            ->selectRaw('coalesce(sum(gross_salary + overtime_amount + bonus_amount + other_earnings_amount), 0) as earnings')
            ->selectRaw('coalesce(sum(attendance_deduction + unpaid_leave_deduction + short_hours_deduction + borrow_recovery + other_deductions), 0) as deductions')
            ->selectRaw('coalesce(sum(borrow_given), 0) as borrow_given')
            ->selectRaw('coalesce(sum(borrow_recovery), 0) as borrow_recovery')
            ->selectRaw('coalesce(sum(net_payable), 0) as net_payable')
            ->toBase()
            ->first();

        $payroll->employee_count = (int) $totals->employee_count;
        $payroll->total_gross = (float) $totals->gross;
        $payroll->total_earnings = (float) $totals->earnings;
        $payroll->total_deductions = (float) $totals->deductions;
        $payroll->total_borrow_given = (float) $totals->borrow_given;
        $payroll->total_borrow_recovery = (float) $totals->borrow_recovery;
        $payroll->total_net_payable = (float) $totals->net_payable;
        $payroll->save();
    }

    /**
     * Rebuild an item's amounts from its stored breakdown and current adjustments.
     */
    private function reapply(PayrollItem $item): void
    {
        $item->load('adjustments');
        $breakdown = $item->breakdown ?? [];

        $this->fill($item, $breakdown, $item->adjustments);
        $item->save();
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  Collection<int, PayrollAdjustment>  $adjustments
     */
    private function fill(PayrollItem $item, array $result, Collection $adjustments): void
    {
        $buckets = PayrollCalculationService::buckets($result['lines'] ?? [], $adjustments);
        $totals = PayrollCalculationService::totals($buckets);
        $summary = $result['attendance'] ?? [];

        $result['buckets'] = $buckets;
        $result['totals'] = $totals;

        $item->fill([
            'employee_name' => $result['employee']['name'],
            'employee_code' => $result['employee']['code'],
            'department_id' => $result['employee']['department_id'],
            'department_name' => $result['employee']['department'],
            'designation_name' => $result['employee']['designation'],
            'net_salary' => $totals['net_salary'],
            'net_payable' => $totals['net_payable'],
            'present_days' => ($summary['present'] ?? 0) + (($summary['half_day'] ?? 0) * 0.5),
            'absent_days' => ($summary['absent'] ?? 0) + ($summary['unmarked'] ?? 0),
            'leave_days' => ($summary['paid_leave'] ?? 0) + ($summary['unpaid_leave'] ?? 0),
            'short_minutes' => $summary['short_minutes'] ?? 0,
            'overtime_minutes' => $summary['overtime_minutes'] ?? 0,
            'attendance_summary' => $summary,
            'breakdown' => $result,
            'is_adjusted' => $adjustments->isNotEmpty(),
        ]);

        foreach (PayrollBucket::cases() as $bucket) {
            $item->setAttribute($bucket->value, $buckets[$bucket->value]['final']);
        }
    }

    /**
     * Post one finalized item to the borrow ledger and mark its entries as paid.
     */
    private function post(PayrollItem $item, Payroll $payroll, CarbonImmutable $today, CarbonImmutable $periodMonth, ?User $user): void
    {
        $breakdown = $item->breakdown ?? [];
        $employee = $item->employee;

        foreach ($breakdown['borrows']['given'] ?? [] as $given) {
            $borrow = EmployeeBorrow::query()->findOrFail((int) $given['borrow_id']);
            $this->borrows->disburse($borrow, $today, $item, $user);
        }

        $systemRecoveries = $breakdown['borrows']['recoveries'] ?? [];
        $recoveredBorrowIds = [];

        if ($item->borrow_recovery > 0) {
            $systemTotal = Money::sum(array_column($systemRecoveries, 'amount'));

            // Without an adjustment the scheduled split per borrow is used. With one,
            // each borrow gets up to its scheduled amount and any extra goes to the oldest.
            $allocations = abs($systemTotal - $item->borrow_recovery) < 0.005
                ? $systemRecoveries
                : $this->borrows->allocate($employee, $item->borrow_recovery, $systemRecoveries);

            foreach ($allocations as $allocation) {
                $recoveredBorrowIds[] = $allocation['borrow_id'];
                $borrow = EmployeeBorrow::query()->findOrFail((int) $allocation['borrow_id']);

                $this->borrows->recover(
                    $borrow,
                    $allocation['amount'],
                    $today,
                    payrollItem: $item,
                    notes: "Recovered from {$payroll->label()} salary",
                    user: $user,
                    installmentMonth: $periodMonth,
                );
            }
        }

        // A scheduled borrow that an adjustment left out this month keeps its full
        // balance; its plan simply moves on, instead of leaving a stale installment
        // behind in a month whose payroll is closed.
        foreach ($systemRecoveries as $scheduled) {
            if (! in_array($scheduled['borrow_id'], $recoveredBorrowIds, true)) {
                $this->borrows->schedule(
                    EmployeeBorrow::query()->findOrFail((int) $scheduled['borrow_id']),
                    $periodMonth->addMonth(),
                );
            }
        }

        $lines = collect(PayrollCalculationService::linesOf($breakdown));

        Overtime::query()
            ->whereIn('id', $lines->pluck('meta.overtime_id')->filter()->all())
            ->update(['payroll_item_id' => $item->id, 'status' => OvertimeStatus::Paid->value]);
        SalaryBonus::query()
            ->whereIn('id', $lines->pluck('meta.bonus_id')->filter()->all())
            ->update(['payroll_item_id' => $item->id]);
        SalaryDeduction::query()
            ->whereIn('id', $lines->pluck('meta.deduction_id')->filter()->all())
            ->update(['payroll_item_id' => $item->id]);

        SalarySlip::query()->firstOrCreate(
            ['payroll_item_id' => $item->id],
            [
                'payroll_id' => $payroll->id,
                'employee_id' => $item->employee_id,
                'slip_number' => sprintf('SLIP-%04d%02d-%s', $payroll->period_year, $payroll->period_month, $item->employee_code),
            ],
        );
    }

    private function guardUnlocked(Payroll $payroll): void
    {
        if ($payroll->isLocked()) {
            throw ValidationException::withMessages([
                'payroll' => "The payroll for {$payroll->label()} is finalized and locked. Reopen it to make changes.",
            ]);
        }
    }

    private function guardCalculated(Payroll $payroll): void
    {
        if ($payroll->status === PayrollStatus::Draft || $payroll->items()->doesntExist()) {
            throw ValidationException::withMessages([
                'payroll' => 'Calculate the payroll before continuing.',
            ]);
        }
    }
}
