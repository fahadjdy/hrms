<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryDeduction;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-off salary deductions. Recurring deductions belong in the salary structure.
 */
class DeductionController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : $this->tenant()->today()->startOfMonth();

        $base = SalaryDeduction::query()
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->whereHas('employee', fn ($employee) => $employee->search($search)));

        return Inertia::render('finance/Deductions', [
            'entries' => (clone $base)
                ->with('employee:id,first_name,last_name,employee_code')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (SalaryDeduction $entry): array => [
                    ...$entry->only(['id', 'employee_id', 'title', 'amount', 'reason']),
                    'employee' => ['id' => $entry->employee->id, 'name' => $entry->employee->full_name, 'code' => $entry->employee->employee_code],
                    'date' => $entry->date->toDateString(),
                    'is_locked' => $entry->isLocked(),
                ]),
            'month' => $month->format('Y-m'),
            'filters' => ['search' => $validated['search'] ?? ''],
            'total' => round((float) (clone $base)->sum('amount'), 2),
            'employees' => Employee::query()
                ->current()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => "{$employee->full_name} ({$employee->employee_code})"]),
            'today' => $this->tenant()->today()->toDateString(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $entry = SalaryDeduction::query()->create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        $audit->log('deduction.created', $entry, null, $entry->only(['title', 'amount', 'date']), "Deduction \"{$entry->title}\" of {$entry->amount} added", $entry->employee_id);
        $this->toast('Deduction added.');

        return back();
    }

    public function update(Request $request, SalaryDeduction $deduction, AuditLogger $audit): RedirectResponse
    {
        $this->guardUnlocked($deduction);

        $original = $deduction->getAttributes();
        $deduction->update($this->validated($request));
        [$old, $new] = $audit->diff($deduction, $original);

        if ($new !== []) {
            $audit->log('deduction.updated', $deduction, $old, $new, "Deduction \"{$deduction->title}\" updated", $deduction->employee_id);
        }

        $this->toast('Deduction updated.');

        return back();
    }

    public function destroy(SalaryDeduction $deduction, AuditLogger $audit): RedirectResponse
    {
        $this->guardUnlocked($deduction);

        $audit->log('deduction.deleted', $deduction, $deduction->only(['title', 'amount', 'date']), null, "Deduction \"{$deduction->title}\" deleted", $deduction->employee_id);
        $deduction->delete();

        $this->toast('Deduction deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'date' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['employee_id' => 'employee']);
    }

    private function guardUnlocked(SalaryDeduction $deduction): void
    {
        if ($deduction->isLocked()) {
            throw ValidationException::withMessages([
                'deduction' => 'This deduction was applied in a finalized payroll and can no longer be changed.',
            ]);
        }
    }
}
