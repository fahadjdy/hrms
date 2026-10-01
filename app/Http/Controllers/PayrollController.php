<?php

namespace App\Http\Controllers;

use App\Enums\PayrollStatus;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(): Response
    {
        $payrolls = Payroll::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate(12)
            ->through(fn (Payroll $payroll): array => $this->present($payroll));

        $latest = Payroll::query()->orderByDesc('period_year')->orderByDesc('period_month')->first();

        // Suggest the month after the latest payroll, or last month for a first run.
        $suggested = $latest !== null
            ? CarbonImmutable::create($latest->period_year, $latest->period_month, 1)->addMonth()
            : $this->tenant()->today()->startOfMonth()->subMonth();

        return Inertia::render('payroll/Index', [
            'payrolls' => $payrolls,
            'suggestedMonth' => $suggested->format('Y-m'),
        ]);
    }

    public function store(Request $request, PayrollService $payrolls): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        [$year, $month] = array_map(intval(...), explode('-', $validated['month']));

        $payroll = $payrolls->create($year, $month, $request->user(), $validated['notes'] ?? null);

        $this->toast("Payroll for {$payroll->label()} created. Calculate it to see each employee's pay.");

        return to_route('payroll.show', $payroll);
    }

    /**
     * Payroll preview: every employee in the payroll, before and after finalization.
     */
    public function show(Request $request, Payroll $payroll): Response
    {
        $filters = $request->only(['search', 'department_id', 'status']);

        $items = PayrollItem::query()
            ->where('payroll_id', $payroll->id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($inner) => $inner
                ->where('employee_name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when(($filters['status'] ?? null) === 'adjusted', fn ($query) => $query->where('is_adjusted', true))
            ->when(($filters['status'] ?? null) === 'calculated', fn ($query) => $query->where('is_adjusted', false))
            ->orderBy('employee_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PayrollItem $item): array => [
                ...$item->only([
                    'id', 'employee_id', 'employee_name', 'employee_code', 'department_name', 'designation_name',
                    'gross_salary', 'overtime_amount', 'bonus_amount', 'other_earnings_amount', 'attendance_deduction',
                    'unpaid_leave_deduction', 'short_hours_deduction', 'borrow_recovery', 'other_deductions',
                    'borrow_given', 'net_salary', 'net_payable', 'present_days', 'absent_days', 'leave_days',
                    'short_minutes', 'overtime_minutes', 'is_adjusted',
                ]),
                'warnings' => count($item->breakdown['warnings'] ?? []),
            ]);

        return Inertia::render('payroll/Show', [
            'payroll' => [
                ...$this->present($payroll),
                'notes' => $payroll->notes,
                'reopen_reason' => $payroll->reopen_reason,
                'reopened_at' => $payroll->reopened_at?->toIso8601String(),
                'finalized_by' => $payroll->finalizer?->name,
            ],
            'items' => $items,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'workflow' => PayrollStatus::options(),
        ]);
    }

    public function destroy(Payroll $payroll, PayrollService $payrolls): RedirectResponse
    {
        $label = $payroll->label();
        $payrolls->delete($payroll);

        $this->toast("Payroll for {$label} deleted.");

        return to_route('payroll.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Payroll $payroll): array
    {
        return [
            ...$payroll->only([
                'id', 'period_year', 'period_month', 'employee_count', 'total_gross', 'total_earnings',
                'total_deductions', 'total_borrow_given', 'total_borrow_recovery', 'total_net_payable',
            ]),
            'label' => $payroll->label(),
            'period_start' => $payroll->period_start->toDateString(),
            'period_end' => $payroll->period_end->toDateString(),
            'status' => $payroll->status->value,
            'status_label' => $payroll->status->label(),
            'is_locked' => $payroll->isLocked(),
            'calculated_at' => $payroll->calculated_at?->toIso8601String(),
            'finalized_at' => $payroll->finalized_at?->toIso8601String(),
        ];
    }
}
