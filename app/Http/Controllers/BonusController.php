<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryBonus;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-off earnings: bonuses and other earnings paid with a month's salary.
 */
class BonusController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in([SalaryBonus::TYPE_BONUS, SalaryBonus::TYPE_OTHER_EARNING])],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : $this->tenant()->today()->startOfMonth();

        $base = SalaryBonus::query()
            ->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->whereHas('employee', fn ($employee) => $employee->search($search)));

        return Inertia::render('finance/Bonuses', [
            'entries' => (clone $base)
                ->with('employee:id,first_name,last_name,employee_code')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (SalaryBonus $entry): array => [
                    ...$entry->only(['id', 'employee_id', 'type', 'title', 'amount', 'reason']),
                    'employee' => ['id' => $entry->employee->id, 'name' => $entry->employee->full_name, 'code' => $entry->employee->employee_code],
                    'date' => $entry->date->toDateString(),
                    'is_locked' => $entry->isLocked(),
                ]),
            'month' => $month->format('Y-m'),
            'filters' => ['search' => $validated['search'] ?? '', 'type' => $validated['type'] ?? null],
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
        $entry = SalaryBonus::query()->create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        $audit->log('bonus.created', $entry, null, $entry->only(['type', 'title', 'amount', 'date']), "\"{$entry->title}\" of {$entry->amount} added", $entry->employee_id);
        $this->toast('Saved.');

        return back();
    }

    public function update(Request $request, SalaryBonus $bonus, AuditLogger $audit): RedirectResponse
    {
        $this->guardUnlocked($bonus);

        $original = $bonus->getAttributes();
        $bonus->update($this->validated($request));
        [$old, $new] = $audit->diff($bonus, $original);

        if ($new !== []) {
            $audit->log('bonus.updated', $bonus, $old, $new, "\"{$bonus->title}\" updated", $bonus->employee_id);
        }

        $this->toast('Updated.');

        return back();
    }

    public function destroy(SalaryBonus $bonus, AuditLogger $audit): RedirectResponse
    {
        $this->guardUnlocked($bonus);

        $audit->log('bonus.deleted', $bonus, $bonus->only(['type', 'title', 'amount', 'date']), null, "\"{$bonus->title}\" deleted", $bonus->employee_id);
        $bonus->delete();

        $this->toast('Deleted.');

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
            'type' => ['required', Rule::in([SalaryBonus::TYPE_BONUS, SalaryBonus::TYPE_OTHER_EARNING])],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], ['employee_id' => 'employee']);
    }

    private function guardUnlocked(SalaryBonus $bonus): void
    {
        if ($bonus->isLocked()) {
            throw ValidationException::withMessages([
                'bonus' => 'This entry was paid in a finalized payroll and can no longer be changed.',
            ]);
        }
    }
}
