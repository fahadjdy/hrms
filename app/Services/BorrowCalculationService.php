<?php

namespace App\Services;

use App\Enums\BorrowStatus;
use App\Enums\BorrowTransactionType;
use App\Models\BorrowInstallment;
use App\Models\BorrowTransaction;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\PayrollItem;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Borrow / salary advance bookkeeping.
 *
 * Each borrow is its own record with an installment schedule and an
 * append-only transaction ledger. The balance on the borrow only ever changes
 * through this service, so `opening balance = recovered + outstanding` holds.
 */
class BorrowCalculationService
{
    /** Upper bound on generated installments, as a guard against tiny deductions. */
    private const int MAX_INSTALLMENTS = 600;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Record a borrow: one the employee already had when joining, or a new one.
     *
     * @param  array<string, mixed>  $data  amount and borrow_date, and optionally kind, opening_balance,
     *                                      reason, monthly_deduction, installments_count, deduction_start_month,
     *                                      disbursement_method, disburse_period, source_reference, notes
     */
    public function create(Employee $employee, array $data, ?User $user = null): EmployeeBorrow
    {
        $kind = ($data['kind'] ?? EmployeeBorrow::KIND_NEW) === EmployeeBorrow::KIND_EXISTING
            ? EmployeeBorrow::KIND_EXISTING
            : EmployeeBorrow::KIND_NEW;

        $amount = Money::round($data['amount']);
        $opening = $kind === EmployeeBorrow::KIND_EXISTING && isset($data['opening_balance']) && $data['opening_balance'] !== ''
            ? Money::round($data['opening_balance'])
            : $amount;

        if ($amount <= 0 || $opening <= 0) {
            throw ValidationException::withMessages(['amount' => 'The borrow amount must be greater than zero.']);
        }

        if ($opening > $amount) {
            throw ValidationException::withMessages([
                'opening_balance' => 'The outstanding balance cannot be more than the original borrow amount.',
            ]);
        }

        $withSalary = $kind === EmployeeBorrow::KIND_NEW
            && ($data['disbursement_method'] ?? null) === EmployeeBorrow::DISBURSE_WITH_SALARY;

        $borrowDate = CarbonImmutable::parse($data['borrow_date'])->startOfDay();
        $installments = isset($data['installments_count']) && (int) $data['installments_count'] > 0
            ? (int) $data['installments_count']
            : null;
        $monthly = Money::round($data['monthly_deduction'] ?? 0);

        if ($monthly <= 0 && $installments !== null) {
            $monthly = Money::round(ceil($opening / $installments * 100) / 100);
        }

        $monthly = min($monthly, $opening);

        $startMonth = ! empty($data['deduction_start_month'])
            ? CarbonImmutable::parse($data['deduction_start_month'])->startOfMonth()
            : $borrowDate->startOfMonth()->addMonth();

        $disbursePeriod = $withSalary
            ? CarbonImmutable::parse($data['disburse_period'] ?? $borrowDate->toDateString())->startOfMonth()
            : null;

        return DB::transaction(function () use (
            $employee, $data, $user, $kind, $amount, $opening, $withSalary, $borrowDate, $installments, $monthly, $startMonth, $disbursePeriod,
        ): EmployeeBorrow {
            $borrow = EmployeeBorrow::query()->create([
                'employee_id' => $employee->id,
                'reference_no' => 'TMP-'.bin2hex(random_bytes(8)),
                'kind' => $kind,
                'amount' => $amount,
                'opening_balance' => $opening,
                'recovered_amount' => 0,
                'outstanding_amount' => $opening,
                'borrow_date' => $borrowDate->toDateString(),
                'reason' => $data['reason'] ?? null,
                'monthly_deduction' => $monthly,
                'installments_count' => $installments,
                'deduction_start_month' => $startMonth->toDateString(),
                'disbursement_method' => $withSalary ? EmployeeBorrow::DISBURSE_WITH_SALARY : EmployeeBorrow::DISBURSE_DIRECT,
                'disburse_period' => $disbursePeriod?->toDateString(),
                'disbursed_at' => $withSalary ? null : now(),
                'status' => $withSalary ? BorrowStatus::PendingDisbursement : BorrowStatus::Active,
                'source_reference' => $data['source_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            $borrow->update(['reference_no' => 'BRW-'.str_pad((string) $borrow->id, 5, '0', STR_PAD_LEFT)]);

            if (! $withSalary) {
                $this->transaction(
                    $borrow,
                    $kind === EmployeeBorrow::KIND_EXISTING ? BorrowTransactionType::Opening : BorrowTransactionType::Disbursement,
                    $opening,
                    $borrowDate,
                    null,
                    $kind === EmployeeBorrow::KIND_EXISTING ? 'Existing borrow carried in at joining' : 'Borrow given',
                    $user,
                );
            }

            $this->schedule($borrow);

            $this->audit->log(
                'borrow.created',
                $borrow,
                null,
                [
                    'reference_no' => $borrow->reference_no,
                    'kind' => $kind,
                    'amount' => $amount,
                    'opening_balance' => $opening,
                    'monthly_deduction' => $monthly,
                    'disbursement_method' => $borrow->disbursement_method,
                ],
                ($kind === EmployeeBorrow::KIND_EXISTING ? 'Existing borrow' : 'New borrow')." {$borrow->reference_no} of {$opening} recorded for {$employee->full_name}",
                $employee->id,
            );

            return $borrow;
        });
    }

    /**
     * Rebuild the pending installment schedule from the outstanding balance.
     * Paid installments are history and are left untouched.
     */
    public function schedule(EmployeeBorrow $borrow, ?CarbonImmutable $fromMonth = null): void
    {
        $firstPending = BorrowInstallment::query()
            ->where('employee_borrow_id', $borrow->id)
            ->where('status', BorrowInstallment::STATUS_PENDING)
            ->min('due_month');

        $lastPaid = BorrowInstallment::query()
            ->where('employee_borrow_id', $borrow->id)
            ->where('status', BorrowInstallment::STATUS_PAID)
            ->orderByDesc('sequence')
            ->first();

        BorrowInstallment::query()
            ->where('employee_borrow_id', $borrow->id)
            ->where('status', BorrowInstallment::STATUS_PENDING)
            ->delete();

        $remaining = $borrow->outstanding_amount;

        if ($remaining <= 0 || $borrow->monthly_deduction <= 0 || $borrow->status === BorrowStatus::Cancelled) {
            return;
        }

        $month = $fromMonth ?? ($firstPending !== null ? CarbonImmutable::parse($firstPending) : null);

        if ($month === null) {
            $month = $borrow->deduction_start_month ?? $borrow->borrow_date->startOfMonth()->addMonth();

            if ($lastPaid !== null && $month->lte($lastPaid->due_month)) {
                $month = $lastPaid->due_month->addMonth();
            }
        }

        // Payroll only deducts from the agreed start month onwards, so the
        // schedule must not show installments before it, e.g. after an early
        // voluntary repayment.
        if ($borrow->deduction_start_month !== null && $month->lt($borrow->deduction_start_month)) {
            $month = $borrow->deduction_start_month;
        }

        $month = $month->startOfMonth();
        $sequence = ($lastPaid->sequence ?? 0) + 1;
        $rows = [];
        $now = now();

        while ($remaining > 0 && count($rows) < self::MAX_INSTALLMENTS) {
            $amount = min($borrow->monthly_deduction, $remaining);

            $rows[] = [
                'company_id' => $borrow->company_id,
                'employee_borrow_id' => $borrow->id,
                'employee_id' => $borrow->employee_id,
                'sequence' => $sequence++,
                'due_month' => $month->toDateString(),
                'amount' => $amount,
                'paid_amount' => 0,
                'status' => BorrowInstallment::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $remaining = Money::round($remaining - $amount);
            $month = $month->addMonth();
        }

        BorrowInstallment::query()->insert($rows);
    }

    /**
     * Recover money against a borrow. Never recovers more than is outstanding.
     */
    public function recover(
        EmployeeBorrow $borrow,
        float $amount,
        CarbonImmutable $date,
        BorrowTransactionType $type = BorrowTransactionType::Recovery,
        ?PayrollItem $payrollItem = null,
        ?string $notes = null,
        ?User $user = null,
        ?CarbonImmutable $installmentMonth = null,
    ): BorrowTransaction {
        $amount = Money::round($amount);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The recovery amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($borrow, $amount, $date, $type, $payrollItem, $notes, $user, $installmentMonth): BorrowTransaction {
            /** @var EmployeeBorrow $locked */
            $locked = EmployeeBorrow::query()->lockForUpdate()->findOrFail($borrow->id);

            if ($locked->status !== BorrowStatus::Active) {
                throw ValidationException::withMessages([
                    'amount' => "Borrow {$locked->reference_no} is not active, so nothing can be recovered against it.",
                ]);
            }

            if ($amount > $locked->outstanding_amount + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => "The recovery of {$amount} is more than the outstanding balance of {$locked->outstanding_amount} on borrow {$locked->reference_no}.",
                ]);
            }

            $locked->recovered_amount = Money::round($locked->recovered_amount + $amount);
            $locked->outstanding_amount = Money::round($locked->outstanding_amount - $amount);

            if ($locked->outstanding_amount <= 0) {
                $locked->outstanding_amount = 0;
                $locked->status = BorrowStatus::Recovered;
            }

            $locked->save();

            $transaction = $this->transaction($locked, $type, $amount, $date, $payrollItem, $notes, $user);

            $this->markInstallmentPaid($locked, $amount, $payrollItem, $installmentMonth);
            $this->schedule($locked, $installmentMonth?->addMonth());

            $this->audit->log(
                'borrow.recovered',
                $locked,
                ['outstanding_amount' => Money::round($locked->outstanding_amount + $amount)],
                ['outstanding_amount' => $locked->outstanding_amount, 'recovered' => $amount, 'type' => $type->value],
                "{$amount} recovered against borrow {$locked->reference_no}",
                $locked->employee_id,
            );

            $borrow->refresh();

            return $transaction;
        });
    }

    /**
     * Pay out a borrow that was created to be given with salary.
     */
    public function disburse(EmployeeBorrow $borrow, CarbonImmutable $date, ?PayrollItem $payrollItem = null, ?User $user = null): BorrowTransaction
    {
        return DB::transaction(function () use ($borrow, $date, $payrollItem, $user): BorrowTransaction {
            /** @var EmployeeBorrow $locked */
            $locked = EmployeeBorrow::query()->lockForUpdate()->findOrFail($borrow->id);

            if ($locked->status !== BorrowStatus::PendingDisbursement) {
                throw ValidationException::withMessages([
                    'borrow' => $locked->status === BorrowStatus::Cancelled
                        ? "Borrow {$locked->reference_no} was cancelled, so it cannot be paid out."
                        : "Borrow {$locked->reference_no} has already been paid out.",
                ]);
            }

            $locked->status = BorrowStatus::Active;
            $locked->disbursed_at = now();
            $locked->save();

            $transaction = $this->transaction(
                $locked,
                BorrowTransactionType::Disbursement,
                $locked->opening_balance,
                $date,
                $payrollItem,
                $payrollItem !== null ? 'Borrow given with salary' : 'Borrow given',
                $user,
            );

            $this->audit->log(
                $payrollItem !== null ? 'borrow.given_with_payroll' : 'borrow.disbursed',
                $locked,
                null,
                ['amount' => $locked->opening_balance, 'payroll_item_id' => $payrollItem?->id],
                "Borrow {$locked->reference_no} of {$locked->opening_balance} given".($payrollItem !== null ? ' with salary' : ''),
                $locked->employee_id,
            );

            $borrow->refresh();

            return $transaction;
        });
    }

    /**
     * Undo the borrow postings a payroll item made when it was finalized.
     * The original entries stay in the ledger and a reversal is added.
     */
    public function reversePayrollPostings(PayrollItem $payrollItem, ?User $user = null): void
    {
        $transactions = BorrowTransaction::query()
            ->where('payroll_item_id', $payrollItem->id)
            ->whereIn('type', [BorrowTransactionType::Recovery->value, BorrowTransactionType::Disbursement->value])
            ->orderByDesc('id')
            ->get();

        foreach ($transactions as $transaction) {
            /** @var EmployeeBorrow $borrow */
            $borrow = EmployeeBorrow::query()->lockForUpdate()->findOrFail($transaction->employee_borrow_id);

            if ($transaction->type === BorrowTransactionType::Recovery) {
                $borrow->recovered_amount = Money::round($borrow->recovered_amount - $transaction->amount);
                $borrow->outstanding_amount = Money::round($borrow->outstanding_amount + $transaction->amount);
                $borrow->status = BorrowStatus::Active;
                $borrow->save();

                $paid = BorrowInstallment::query()
                    ->where('employee_borrow_id', $borrow->id)
                    ->where('payroll_item_id', $payrollItem->id)
                    ->first();
                $dueMonth = $paid?->due_month;
                $paid?->delete();

                $this->schedule($borrow, $dueMonth);
            } else {
                $borrow->status = BorrowStatus::PendingDisbursement;
                $borrow->disbursed_at = null;
                $borrow->save();
            }

            $this->transaction(
                $borrow,
                BorrowTransactionType::Reversal,
                $transaction->amount,
                $this->tenant->today(),
                null,
                "Reversal of {$transaction->type->label()} #{$transaction->id} (payroll reopened)",
                $user,
            );

            // Unlink the original entry so a later finalization can post again.
            $transaction->payroll_item_id = null;
            $transaction->notes = trim(($transaction->notes ?? '').' (reversed)');
            $transaction->save();
        }
    }

    /**
     * Cancel a borrow that has not been paid out or recovered against yet.
     */
    public function cancel(EmployeeBorrow $borrow, ?User $user = null): void
    {
        if ($borrow->recovered_amount > 0) {
            throw ValidationException::withMessages([
                'borrow' => 'This borrow already has recoveries, so it cannot be cancelled.',
            ]);
        }

        if ($borrow->status === BorrowStatus::Active && $borrow->disbursement_method === EmployeeBorrow::DISBURSE_WITH_SALARY) {
            throw ValidationException::withMessages([
                'borrow' => 'This borrow was paid out with a finalized payroll. Reopen that payroll to change it.',
            ]);
        }

        DB::transaction(function () use ($borrow): void {
            $borrow->status = BorrowStatus::Cancelled;
            $borrow->outstanding_amount = 0;
            $borrow->save();

            BorrowInstallment::query()
                ->where('employee_borrow_id', $borrow->id)
                ->where('status', BorrowInstallment::STATUS_PENDING)
                ->delete();

            $this->audit->log(
                'borrow.cancelled',
                $borrow,
                ['status' => BorrowStatus::Active->value],
                ['status' => BorrowStatus::Cancelled->value],
                "Borrow {$borrow->reference_no} cancelled",
                $borrow->employee_id,
            );
        });
    }

    /**
     * Recoveries scheduled for an employee in a payroll period: one monthly
     * deduction per active borrow whose deductions have started, capped at
     * that borrow's outstanding balance.
     *
     * @return list<array{borrow_id: int, reference_no: string, amount: float, outstanding: float}>
     */
    public function dueForPeriod(Employee $employee, PayrollPeriod $period): array
    {
        if (! $this->tenant->settings()->borrow_auto_deduct) {
            return [];
        }

        $periodMonth = CarbonImmutable::create($period->year, $period->month, 1)->toDateString();

        return array_values(EmployeeBorrow::query()
            ->where('employee_id', $employee->id)
            ->outstanding()
            ->where('monthly_deduction', '>', 0)
            ->where('deduction_start_month', '<=', $periodMonth)
            ->orderBy('borrow_date')
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeBorrow $borrow): array => [
                'borrow_id' => $borrow->id,
                'reference_no' => $borrow->reference_no,
                'amount' => Money::round(min($borrow->monthly_deduction, $borrow->outstanding_amount)),
                'outstanding' => $borrow->outstanding_amount,
            ])
            ->all());
    }

    /**
     * New borrows to be paid out together with the payroll of a period.
     *
     * @return list<array{borrow_id: int, reference_no: string, amount: float, reason: string|null}>
     */
    public function givenWithPeriod(Employee $employee, PayrollPeriod $period): array
    {
        $periodMonth = CarbonImmutable::create($period->year, $period->month, 1)->toDateString();

        return array_values(EmployeeBorrow::query()
            ->where('employee_id', $employee->id)
            ->where('status', BorrowStatus::PendingDisbursement->value)
            ->where('disburse_period', $periodMonth)
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeBorrow $borrow): array => [
                'borrow_id' => $borrow->id,
                'reference_no' => $borrow->reference_no,
                'amount' => $borrow->opening_balance,
                'reason' => $borrow->reason,
            ])
            ->all());
    }

    /**
     * Total outstanding across all of an employee's active borrows.
     */
    public function outstandingFor(Employee $employee): float
    {
        return Money::round(
            EmployeeBorrow::query()->where('employee_id', $employee->id)->outstanding()->sum('outstanding_amount'),
        );
    }

    /**
     * Spread a recovery amount over an employee's active borrows, never
     * exceeding what each borrow has outstanding.
     *
     * Each borrow first receives up to its scheduled amount, in the scheduled
     * order, so a reduced total still pays every borrow its share as far as
     * the money goes. Anything above the schedule goes to the oldest borrows.
     *
     * @param  list<array{borrow_id: int, amount: float|int}>  $scheduled
     * @return list<array{borrow_id: int, amount: float}>
     */
    public function allocate(Employee $employee, float $amount, array $scheduled = []): array
    {
        $remaining = Money::round($amount);

        $borrows = EmployeeBorrow::query()
            ->where('employee_id', $employee->id)
            ->outstanding()
            ->orderBy('borrow_date')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        /** @var array<int, float> $allocated */
        $allocated = [];

        foreach ($scheduled as $installment) {
            $borrow = $borrows->get($installment['borrow_id']);

            if ($borrow === null || $remaining <= 0) {
                continue;
            }

            $portion = Money::round(min($remaining, (float) $installment['amount'], $borrow->outstanding_amount));
            $allocated[$borrow->id] = $portion;
            $remaining = Money::round($remaining - $portion);
        }

        foreach ($borrows as $borrow) {
            if ($remaining <= 0) {
                break;
            }

            $room = Money::round($borrow->outstanding_amount - ($allocated[$borrow->id] ?? 0));
            $portion = Money::round(min($remaining, $room));

            if ($portion > 0) {
                $allocated[$borrow->id] = Money::round(($allocated[$borrow->id] ?? 0) + $portion);
                $remaining = Money::round($remaining - $portion);
            }
        }

        if ($remaining > 0.001) {
            throw ValidationException::withMessages([
                'amount' => "The borrow recovery is {$remaining} more than {$employee->full_name}'s total outstanding borrow.",
            ]);
        }

        $allocations = [];

        foreach ($allocated as $borrowId => $portion) {
            if ($portion > 0) {
                $allocations[] = ['borrow_id' => $borrowId, 'amount' => $portion];
            }
        }

        return $allocations;
    }

    private function transaction(
        EmployeeBorrow $borrow,
        BorrowTransactionType $type,
        float $amount,
        CarbonImmutable $date,
        ?PayrollItem $payrollItem,
        ?string $notes,
        ?User $user,
    ): BorrowTransaction {
        return BorrowTransaction::query()->create([
            'employee_borrow_id' => $borrow->id,
            'employee_id' => $borrow->employee_id,
            'type' => $type,
            'amount' => Money::round($amount),
            'balance_after' => $borrow->outstanding_amount,
            'transaction_date' => $date->toDateString(),
            'payroll_item_id' => $payrollItem?->id,
            'notes' => $notes,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Record the recovery as a paid installment so the schedule shows what was collected and when.
     */
    private function markInstallmentPaid(EmployeeBorrow $borrow, float $amount, ?PayrollItem $payrollItem, ?CarbonImmutable $month): void
    {
        $lastSequence = (int) BorrowInstallment::query()
            ->where('employee_borrow_id', $borrow->id)
            ->where('status', BorrowInstallment::STATUS_PAID)
            ->max('sequence');

        BorrowInstallment::query()->create([
            'employee_borrow_id' => $borrow->id,
            'employee_id' => $borrow->employee_id,
            'sequence' => $lastSequence + 1,
            'due_month' => ($month ?? $this->tenant->today())->startOfMonth()->toDateString(),
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => BorrowInstallment::STATUS_PAID,
            'payroll_item_id' => $payrollItem?->id,
            'paid_at' => now(),
        ]);
    }
}
