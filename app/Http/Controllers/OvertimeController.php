<?php

namespace App\Http\Controllers;

use App\Enums\OvertimeStatus;
use App\Models\Employee;
use App\Models\Overtime;
use App\Services\AuditLogger;
use App\Services\OvertimeCalculationService;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(OvertimeStatus::class)],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : $this->tenant()->today()->startOfMonth();
        $range = [$month->toDateString(), $month->endOfMonth()->toDateString()];

        $base = Overtime::query()
            ->whereBetween('date', $range)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->whereHas('employee', fn ($employee) => $employee->search($search)));

        return Inertia::render('finance/Overtime', [
            'entries' => (clone $base)
                ->with('employee:id,first_name,last_name,employee_code')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Overtime $entry): array => [
                    ...$entry->only(['id', 'employee_id', 'calculation_type', 'hours', 'rate', 'amount', 'reason', 'notes']),
                    'employee' => ['id' => $entry->employee->id, 'name' => $entry->employee->full_name, 'code' => $entry->employee->employee_code],
                    'date' => $entry->date->toDateString(),
                    'status' => $entry->status->value,
                    'status_label' => $entry->status->label(),
                    'is_locked' => $entry->isLocked(),
                ]),
            'month' => $month->format('Y-m'),
            'filters' => ['search' => $validated['search'] ?? '', 'status' => $validated['status'] ?? null],
            'totals' => [
                'amount' => round((float) (clone $base)->whereIn('status', [OvertimeStatus::Approved->value, OvertimeStatus::Paid->value])->sum('amount'), 2),
                'hours' => round((float) (clone $base)->whereIn('status', [OvertimeStatus::Approved->value, OvertimeStatus::Paid->value])->sum('hours'), 2),
                'pending' => (clone $base)->where('status', OvertimeStatus::Pending->value)->count(),
            ],
            'statuses' => OvertimeStatus::options(),
            'employees' => $this->employeeOptions(),
            'today' => $this->tenant()->today()->toDateString(),
        ]);
    }

    public function store(Request $request, OvertimeCalculationService $calculator, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);
        $employee = Employee::query()->findOrFail((int) $validated['employee_id']);

        $entry = Overtime::query()->create([
            ...$validated,
            'amount' => $calculator->entryAmount($validated['calculation_type'], $validated['hours'] ?? null, $validated['rate'] ?? null, $validated['amount'] ?? null),
            'created_by' => $request->user()->id,
        ]);

        $audit->log('overtime.created', $entry, null, $entry->only(['date', 'calculation_type', 'hours', 'rate', 'amount', 'status']), "Overtime of {$entry->amount} added for {$employee->full_name}", $employee->id);
        $this->toast('Overtime added.');

        return back();
    }

    public function update(Request $request, Overtime $overtime, OvertimeCalculationService $calculator, AuditLogger $audit): RedirectResponse
    {
        $this->guardUnlocked($overtime);

        $validated = $this->validated($request);
        $original = $overtime->getAttributes();

        $overtime->update([
            ...$validated,
            'amount' => $calculator->entryAmount($validated['calculation_type'], $validated['hours'] ?? null, $validated['rate'] ?? null, $validated['amount'] ?? null),
        ]);

        [$old, $new] = $audit->diff($overtime, $original);

        if ($new !== []) {
            $audit->log('overtime.updated', $overtime, $old, $new, 'Overtime entry updated', $overtime->employee_id);
        }

        $this->toast('Overtime updated.');

        return back();
    }

    public function destroy(Overtime $overtime, AuditLogger $audit): RedirectResponse
    {
        $this->guardUnlocked($overtime);

        $audit->log('overtime.deleted', $overtime, $overtime->only(['date', 'amount', 'status']), null, 'Overtime entry deleted', $overtime->employee_id);
        $overtime->delete();

        $this->toast('Overtime deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'date' => ['required', 'date_format:Y-m-d'],
            'calculation_type' => ['required', Rule::in([Overtime::TYPE_HOURLY, Overtime::TYPE_FIXED])],
            'hours' => ['nullable', 'numeric', 'min:0.25', 'max:24', 'required_if:calculation_type,'.Overtime::TYPE_HOURLY],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:999999', 'required_if:calculation_type,'.Overtime::TYPE_HOURLY],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999', 'required_if:calculation_type,'.Overtime::TYPE_FIXED],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([OvertimeStatus::Pending->value, OvertimeStatus::Approved->value, OvertimeStatus::Rejected->value])],
        ], [
            'hours.required_if' => 'Enter the overtime hours.',
            'rate.required_if' => 'Enter the rate per hour.',
            'amount.required_if' => 'Enter the fixed overtime amount.',
        ], ['employee_id' => 'employee']);

        if ($validated['calculation_type'] === Overtime::TYPE_FIXED) {
            $validated['hours'] = $validated['hours'] ?? null;
            $validated['rate'] = null;
        }

        return $validated;
    }

    private function guardUnlocked(Overtime $overtime): void
    {
        if ($overtime->isLocked()) {
            throw ValidationException::withMessages([
                'overtime' => 'This overtime was paid in a finalized payroll and can no longer be changed.',
            ]);
        }
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function employeeOptions(): array
    {
        return array_values(Employee::query()
            ->current()
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code'])
            ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => "{$employee->full_name} ({$employee->employee_code})"])
            ->all());
    }
}
