<?php

namespace App\Http\Controllers;

use App\Enums\BorrowTransactionType;
use App\Models\BorrowTransaction;
use App\Models\EmployeeBorrow;
use App\Services\BorrowCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BorrowRecoveryController extends Controller
{
    /**
     * The borrow ledger: everything that changed a borrow balance.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'type', 'from', 'to']);
        $thisMonth = $this->tenant()->today()->startOfMonth();

        $transactions = BorrowTransaction::query()
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('transaction_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('transaction_date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->whereHas('borrow', fn ($borrow) => $borrow->where('reference_no', 'like', "%{$search}%"))
                ->orWhereHas('employee', fn ($employee) => $employee->search($search))))
            ->with(['borrow:id,reference_no', 'employee:id,first_name,last_name,employee_code', 'user:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BorrowTransaction $transaction): array => [
                'id' => $transaction->id,
                'borrow_id' => $transaction->employee_borrow_id,
                'reference_no' => $transaction->borrow->reference_no,
                'employee' => [
                    'id' => $transaction->employee->id,
                    'name' => $transaction->employee->full_name,
                    'code' => $transaction->employee->employee_code,
                ],
                'type' => $transaction->type->value,
                'type_label' => $transaction->type->label(),
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'date' => $transaction->transaction_date->toDateString(),
                'notes' => $transaction->notes,
                'user' => $transaction->user?->name,
                'via_payroll' => $transaction->payroll_item_id !== null,
            ]);

        $recovered = fn (CarbonImmutable $from, CarbonImmutable $to): float => round((float) BorrowTransaction::query()
            ->whereIn('type', [BorrowTransactionType::Recovery->value, BorrowTransactionType::Settlement->value])
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount'), 2);

        return Inertia::render('finance/BorrowRecoveries', [
            'transactions' => $transactions,
            'filters' => $filters,
            'types' => BorrowTransactionType::options(),
            'stats' => [
                'recovered_this_month' => $recovered($thisMonth, $thisMonth->endOfMonth()),
                'recovered_last_month' => $recovered($thisMonth->subMonth(), $thisMonth->subDay()),
            ],
            'activeBorrows' => EmployeeBorrow::query()
                ->outstanding()
                ->with('employee:id,first_name,last_name,employee_code')
                ->orderBy('reference_no')
                ->get()
                ->map(fn (EmployeeBorrow $borrow): array => [
                    'id' => $borrow->id,
                    'label' => "{$borrow->reference_no} - {$borrow->employee->full_name}",
                    'outstanding' => $borrow->outstanding_amount,
                ]),
            'today' => $this->tenant()->today()->toDateString(),
        ]);
    }

    /**
     * Record a repayment made outside payroll, e.g. cash returned by the employee.
     */
    public function store(Request $request, EmployeeBorrow $borrow, BorrowCalculationService $borrows): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$borrow->outstanding_amount],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->tenant()->today()->toDateString()],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.max' => "The recovery cannot be more than the outstanding balance of {$borrow->outstanding_amount}.",
        ]);

        $borrows->recover(
            $borrow,
            (float) $validated['amount'],
            CarbonImmutable::parse($validated['date']),
            notes: $validated['notes'] ?? 'Manual recovery',
            user: $request->user(),
        );

        $this->toast('Recovery recorded.');

        return back();
    }
}
