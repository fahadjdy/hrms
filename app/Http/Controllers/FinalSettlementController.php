<?php

namespace App\Http\Controllers;

use App\Enums\BorrowStatus;
use App\Models\Employee;
use App\Models\FinalSettlement;
use App\Services\FinalSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinalSettlementController extends Controller
{
    /**
     * Past employees and where their final settlement stands.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);
        $status = $filters['status'] ?? null;

        $employees = Employee::query()
            ->past()
            ->search($filters['search'] ?? null)
            ->when($status === 'not_started', fn ($query) => $query->whereDoesntHave('finalSettlement'))
            ->when($status !== null && $status !== 'not_started', fn ($query) => $query->whereHas(
                'finalSettlement',
                fn ($settlement) => $settlement->where('status', $status),
            ))
            ->with(['department:id,name', 'designation:id,name', 'finalSettlement'])
            ->withSum(
                ['borrows as outstanding_borrow' => fn ($query) => $query->where('status', BorrowStatus::Active->value)],
                'outstanding_amount',
            )
            ->orderByDesc('last_working_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                ...$employee->toBrief(),
                'last_working_date' => $employee->last_working_date?->toDateString(),
                'exit_type' => $employee->exit_type?->label(),
                'outstanding_borrow' => round((float) $employee->getAttribute('outstanding_borrow'), 2),
                'settlement' => $employee->finalSettlement === null ? null : [
                    'status' => $employee->finalSettlement->status,
                    'net_amount' => $employee->finalSettlement->net_amount,
                ],
            ]);

        return Inertia::render('settlements/Index', [
            'employees' => $employees,
            'filters' => $filters,
        ]);
    }

    /**
     * The settlement for one past employee. A draft is recalculated from
     * current data each time it is opened; a finalized one is shown as it was.
     */
    public function show(Request $request, Employee $employee, FinalSettlementService $settlements): Response
    {
        abort_unless($employee->isPast(), 404);

        $employee->load(['department:id,name', 'designation:id,name']);
        $settlement = FinalSettlement::query()->where('employee_id', $employee->id)->first();

        if ($settlement === null || ! $settlement->isLocked()) {
            $settlement = $settlements->prepare($employee, $request->user());
        }

        $breakdown = $settlement->breakdown ?? [];

        return Inertia::render('settlements/Show', [
            'employee' => [
                ...$employee->toBrief(),
                'joining_date' => $employee->joining_date->toDateString(),
                'last_working_date' => $employee->last_working_date?->toDateString(),
                'exit_type' => $employee->exit_type?->label(),
                'exit_reason' => $employee->exit_reason,
            ],
            'settlement' => [
                ...$settlement->only([
                    'id', 'last_salary', 'overtime_amount', 'bonus_amount', 'other_earnings_amount',
                    'unpaid_leave_deduction', 'short_hours_deduction', 'outstanding_borrow', 'other_deductions',
                    'adjustment_amount', 'adjustment_reason', 'net_amount', 'status', 'notes',
                ]),
                'period_start' => $settlement->period_start->toDateString(),
                'period_end' => $settlement->period_end->toDateString(),
                'is_locked' => $settlement->isLocked(),
                'finalized_at' => $settlement->finalized_at?->toIso8601String(),
                'paid_at' => $settlement->paid_at?->toIso8601String(),
                'salary_already_paid' => (bool) ($breakdown['salary_already_paid'] ?? false),
            ],
            'lines' => $breakdown['lines'] ?? [],
            'attendance' => $breakdown['attendance'] ?? [],
            'salary' => $breakdown['salary'] ?? null,
            'warnings' => $breakdown['warnings'] ?? [],
        ]);
    }

    /**
     * Recalculate the draft settlement from current data.
     */
    public function store(Request $request, Employee $employee, FinalSettlementService $settlements): RedirectResponse
    {
        abort_unless($employee->isPast(), 404);

        $settlements->prepare($employee, $request->user());

        $this->toast('Final settlement recalculated.');

        return back();
    }

    /**
     * Save the admin's manual adjustment.
     */
    public function update(Request $request, Employee $employee, FinalSettlementService $settlements): RedirectResponse
    {
        $validated = $request->validate([
            'adjustment_amount' => ['required', 'numeric', 'between:-999999999,999999999'],
            'adjustment_reason' => ['nullable', 'string', 'max:255', 'required_unless:adjustment_amount,0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'adjustment_reason.required_unless' => 'Give a reason for the adjustment.',
        ]);

        $settlements->adjust(
            $this->settlementOf($employee),
            (float) $validated['adjustment_amount'],
            $validated['adjustment_reason'] ?? null,
            $validated['notes'] ?? null,
        );

        $this->toast('Final settlement updated.');

        return back();
    }

    public function finalize(Request $request, Employee $employee, FinalSettlementService $settlements): RedirectResponse
    {
        $settlements->finalize($this->settlementOf($employee), $request->user());

        $this->toast('Final settlement finalized.');

        return back();
    }

    public function paid(Employee $employee, FinalSettlementService $settlements): RedirectResponse
    {
        $settlements->markPaid($this->settlementOf($employee));

        $this->toast('Final settlement marked as paid.');

        return back();
    }

    private function settlementOf(Employee $employee): FinalSettlement
    {
        return FinalSettlement::query()
            ->where('employee_id', $employee->id)
            ->with('employee')
            ->firstOrFail();
    }
}
