<?php

namespace App\Http\Controllers;

use App\Enums\BorrowStatus;
use App\Models\BorrowInstallment;
use App\Models\BorrowTransaction;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Services\BorrowCalculationService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BorrowController extends Controller
{
    /**
     * Borrow / advance overview and the list of all borrow records.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'kind']);
        $thisMonth = $this->tenant()->today()->startOfMonth();

        $borrows = EmployeeBorrow::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('reference_no', 'like', "%{$search}%")
                ->orWhereHas('employee', fn ($employee) => $employee->search($search))))
            ->with('employee:id,first_name,last_name,employee_code,status')
            ->orderByDesc('borrow_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (EmployeeBorrow $borrow): array => $this->present($borrow));

        $issued = EmployeeBorrow::query()->whereIn('status', [BorrowStatus::Active->value, BorrowStatus::Recovered->value]);

        return Inertia::render('finance/Borrows', [
            'borrows' => $borrows,
            'filters' => $filters,
            'statuses' => BorrowStatus::options(),
            'stats' => [
                'total_borrowed' => round((float) (clone $issued)->sum('opening_balance'), 2),
                'total_recovered' => round((float) (clone $issued)->sum('recovered_amount'), 2),
                'total_outstanding' => round((float) EmployeeBorrow::query()->outstanding()->sum('outstanding_amount'), 2),
                'employees_with_borrow' => EmployeeBorrow::query()->outstanding()->distinct()->count('employee_id'),
                'active' => EmployeeBorrow::query()->outstanding()->count(),
                'fully_recovered' => EmployeeBorrow::query()->where('status', BorrowStatus::Recovered->value)->count(),
                'pending_disbursement' => EmployeeBorrow::query()->where('status', BorrowStatus::PendingDisbursement->value)->count(),
                // Installments that were due in an earlier month and have not been recovered.
                'overdue' => BorrowInstallment::query()
                    ->where('status', BorrowInstallment::STATUS_PENDING)
                    ->where('due_month', '<', $thisMonth->toDateString())
                    ->distinct()
                    ->count('employee_borrow_id'),
            ],
            'highestOutstanding' => $this->highestOutstanding(),
            'upcomingDeductions' => BorrowInstallment::query()
                ->where('status', BorrowInstallment::STATUS_PENDING)
                ->whereBetween('due_month', [$thisMonth->toDateString(), $thisMonth->addMonth()->toDateString()])
                ->with(['borrow:id,reference_no', 'employee:id,first_name,last_name,employee_code'])
                ->orderBy('due_month')
                ->orderByDesc('amount')
                ->limit(8)
                ->get()
                ->map(fn (BorrowInstallment $installment): array => [
                    'id' => $installment->id,
                    'borrow_id' => $installment->employee_borrow_id,
                    'reference_no' => $installment->borrow->reference_no,
                    'employee' => $installment->employee->full_name,
                    'due_month' => $installment->due_month->toDateString(),
                    'amount' => $installment->amount,
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $today = $this->tenant()->today();

        return Inertia::render('finance/BorrowCreate', [
            'employees' => Employee::query()
                ->current()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => "{$employee->full_name} ({$employee->employee_code})"]),
            'selectedEmployeeId' => $request->integer('employee_id') ?: null,
            'today' => $today->toDateString(),
            'currentMonth' => $today->format('Y-m'),
            'nextMonth' => $today->addMonthNoOverflow()->format('Y-m'),
        ]);
    }

    /**
     * Record a borrow: an existing one carried in at joining, or a new one
     * given directly or together with a month's salary.
     */
    public function store(Request $request, BorrowCalculationService $borrows): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'kind' => ['required', Rule::in([EmployeeBorrow::KIND_NEW, EmployeeBorrow::KIND_EXISTING])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'opening_balance' => ['nullable', 'numeric', 'min:0.01', 'lte:amount'],
            'borrow_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
            'monthly_deduction' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'required_without:installments_count'],
            'installments_count' => ['nullable', 'integer', 'min:1', 'max:600'],
            'deduction_start_month' => ['required', 'date_format:Y-m'],
            'disbursement_method' => ['required', Rule::in([EmployeeBorrow::DISBURSE_DIRECT, EmployeeBorrow::DISBURSE_WITH_SALARY])],
            'disburse_period' => ['nullable', 'date_format:Y-m', 'required_if:disbursement_method,'.EmployeeBorrow::DISBURSE_WITH_SALARY],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'opening_balance.lte' => 'The outstanding balance cannot be more than the original borrow amount.',
            'monthly_deduction.required_without' => 'Enter a monthly deduction or the number of installments.',
            'disburse_period.required_if' => 'Choose the salary month this borrow is paid with.',
        ], [
            'employee_id' => 'employee',
            'opening_balance' => 'outstanding balance',
            'disburse_period' => 'salary month',
        ]);

        $borrow = $borrows->create(Employee::query()->findOrFail((int) $validated['employee_id']), [
            ...$validated,
            'deduction_start_month' => $validated['deduction_start_month'].'-01',
            'disburse_period' => isset($validated['disburse_period']) ? $validated['disburse_period'].'-01' : null,
        ], $request->user());

        $this->toast("Borrow {$borrow->reference_no} recorded.");

        return to_route('borrows.show', $borrow);
    }

    public function show(EmployeeBorrow $borrow): Response
    {
        $borrow->load(['employee.department:id,name', 'employee.designation:id,name', 'installments', 'transactions.user:id,name']);

        return Inertia::render('finance/BorrowShow', [
            'borrow' => [
                ...$this->present($borrow),
                'reason' => $borrow->reason,
                'notes' => $borrow->notes,
                'source_reference' => $borrow->source_reference,
                'original_amount' => $borrow->amount,
                'installments_count' => $borrow->installments_count,
                'deduction_start_month' => $borrow->deduction_start_month?->toDateString(),
                'disbursed_at' => $borrow->disbursed_at?->toIso8601String(),
                'can_cancel' => $borrow->recovered_amount <= 0
                    && in_array($borrow->status, [BorrowStatus::Active, BorrowStatus::PendingDisbursement], true)
                    && ! ($borrow->status === BorrowStatus::Active && $borrow->disbursement_method === EmployeeBorrow::DISBURSE_WITH_SALARY),
                'can_recover' => $borrow->status === BorrowStatus::Active && $borrow->outstanding_amount > 0,
            ],
            'employee' => $borrow->employee->toBrief(),
            'installments' => $borrow->installments->map(fn (BorrowInstallment $installment): array => [
                'id' => $installment->id,
                'sequence' => $installment->sequence,
                'due_month' => $installment->due_month->toDateString(),
                'amount' => $installment->amount,
                'paid_amount' => $installment->paid_amount,
                'status' => $installment->status,
                'paid_at' => $installment->paid_at?->toIso8601String(),
                'via_payroll' => $installment->payroll_item_id !== null,
            ]),
            'transactions' => $borrow->transactions->map(fn (BorrowTransaction $transaction): array => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'type_label' => $transaction->type->label(),
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'date' => $transaction->transaction_date->toDateString(),
                'notes' => $transaction->notes,
                'user' => $transaction->user?->name,
                'via_payroll' => $transaction->payroll_item_id !== null,
            ]),
            'today' => $this->tenant()->today()->toDateString(),
        ]);
    }

    /**
     * Cancel a borrow that has not been recovered against.
     */
    public function destroy(Request $request, EmployeeBorrow $borrow, BorrowCalculationService $borrows): RedirectResponse
    {
        $borrows->cancel($borrow, $request->user());

        $this->toast("Borrow {$borrow->reference_no} cancelled.");

        return to_route('borrows.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EmployeeBorrow $borrow): array
    {
        return [
            'id' => $borrow->id,
            'reference_no' => $borrow->reference_no,
            'employee' => [
                'id' => $borrow->employee->id,
                'name' => $borrow->employee->full_name,
                'code' => $borrow->employee->employee_code,
                'is_past' => $borrow->employee->isPast(),
            ],
            'kind' => $borrow->kind,
            'amount' => $borrow->opening_balance,
            'recovered' => $borrow->recovered_amount,
            'outstanding' => $borrow->status === BorrowStatus::Cancelled ? 0.0 : $borrow->outstanding_amount,
            'monthly_deduction' => $borrow->monthly_deduction,
            'borrow_date' => $borrow->borrow_date->toDateString(),
            'disbursement_method' => $borrow->disbursement_method,
            'disburse_period' => $borrow->disburse_period?->format('F Y'),
            'status' => $borrow->status->value,
            'status_label' => $borrow->status->label(),
        ];
    }

    /**
     * The five employees who owe the most, summed over all their active borrows.
     *
     * @return list<array{employee_id: int, name: string, code: string, outstanding: float, borrows: int}>
     */
    private function highestOutstanding(): array
    {
        $rows = EmployeeBorrow::query()
            ->outstanding()
            ->selectRaw('employee_id, sum(outstanding_amount) as outstanding, count(*) as borrows')
            ->groupBy('employee_id')
            ->orderByDesc('outstanding')
            ->limit(5)
            ->toBase()
            ->get();

        $employees = Employee::query()
            ->whereKey($rows->pluck('employee_id'))
            ->get(['id', 'first_name', 'last_name', 'employee_code'])
            ->keyBy('id');

        return array_values($rows
            ->filter(fn (object $row): bool => $employees->has($row->employee_id))
            ->map(fn (object $row): array => [
                'employee_id' => (int) $row->employee_id,
                'name' => $employees[$row->employee_id]->full_name,
                'code' => $employees[$row->employee_id]->employee_code,
                'outstanding' => (float) $row->outstanding,
                'borrows' => (int) $row->borrows,
            ])
            ->all());
    }
}
