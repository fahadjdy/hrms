<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryRevision;
use App\Models\Payroll;
use App\Services\SalaryRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalaryController extends Controller
{
    /**
     * Salary structure: the salary currently in effect for each employee.
     */
    public function index(Request $request, SalaryRevisionService $salaries): Response
    {
        $filters = $request->only(['search', 'department_id']);
        $today = $this->tenant()->today();

        $employees = Employee::query()
            ->current()
            ->search($filters['search'] ?? null)
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->with(['department:id,name', 'designation:id,name', 'salaryRevisions.components'])
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Employee $employee) use ($salaries, $today): array {
                $revision = $salaries->revisionOn($employee, $today);

                return [
                    ...$employee->toBrief(),
                    'gross' => $revision?->new_gross,
                    'effective_date' => $revision?->effective_date->toDateString(),
                    'components' => $revision?->components->map->only(['name', 'type', 'amount'])->values() ?? [],
                    'revisions_count' => $employee->salaryRevisions->count(),
                ];
            });

        return Inertia::render('payroll/SalaryStructure', [
            'employees' => $employees,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * One employee's salary: the current structure and the full revision history.
     */
    public function show(Employee $employee, SalaryRevisionService $salaries): Response
    {
        $employee->load(['department:id,name', 'designation:id,name', 'salaryRevisions' => fn ($query) => $query->with(['components', 'creator:id,name'])]);
        $current = $salaries->revisionOn($employee, $this->tenant()->today());
        $lockedReasons = $salaries->lockedReasons($employee);

        return Inertia::render('payroll/EmployeeSalary', [
            'employee' => [
                ...$employee->toBrief(),
                'joining_date' => $employee->joining_date->toDateString(),
            ],
            'current' => $current === null ? null : $this->present($current),
            'revisions' => $salaries->revisions($employee)
                ->reverse()
                ->values()
                ->map(function (EmployeeSalaryRevision $revision) use ($current, $lockedReasons): array {
                    $lockedReason = $lockedReasons[$revision->id] ?? null;

                    return [
                        ...$this->present($revision),
                        'is_current' => $current?->id === $revision->id,
                        'is_upcoming' => $revision->effective_date->gt($this->tenant()->today()),
                        'is_locked' => $lockedReason !== null,
                        'locked_reason' => $lockedReason,
                    ];
                }),
            'today' => $this->tenant()->today()->toDateString(),
        ]);
    }

    /**
     * Add a salary revision. Refused inside a paid period; open payrolls for the
     * days it takes over go back to draft, which the toast says.
     */
    public function store(Request $request, Employee $employee, SalaryRevisionService $salaries): RedirectResponse
    {
        $validated = $this->validateRevision($request, $employee);

        $result = $salaries->revise($employee, $validated, $request->user());

        $this->toastWithRecalculation('Salary revision saved.', $result['pending_payrolls']);

        return back();
    }

    /**
     * Edit a salary revision. Changing its pay is refused once a finalized payroll
     * or settlement used it; open payrolls for the affected days go back to draft
     * and must be recalculated, which the toast says.
     */
    public function update(Request $request, Employee $employee, EmployeeSalaryRevision $revision, SalaryRevisionService $salaries): RedirectResponse
    {
        $validated = $this->validateRevision($request, $employee);

        $revision->setRelation('employee', $employee);
        $result = $salaries->update($revision, $validated, $request->user());

        $this->toastWithRecalculation('Salary revision updated.', $result['pending_payrolls']);

        return back();
    }

    /**
     * Confirm the change and name the payrolls it moved back to draft, if any.
     *
     * @param  Collection<int, Payroll>  $payrolls
     */
    private function toastWithRecalculation(string $message, Collection $payrolls): void
    {
        $months = $payrolls->map(fn (Payroll $payroll): string => $payroll->label())->values()->all();

        if ($months === []) {
            $this->toast($message);

            return;
        }

        $this->toast(
            count($months) === 1
                ? "{$message} The payroll for {$months[0]} was moved back to draft; recalculate it."
                : "{$message} The payrolls for ".Arr::join($months, ', ', ' and ').' were moved back to draft; recalculate them.',
            'warning',
        );
    }

    /**
     * Rules shared by adding and editing a revision.
     *
     * @return array{effective_date: string, reason?: string|null, notes?: string|null, components: list<array{name: string, type: string, amount: float|int|string}>}
     */
    private function validateRevision(Request $request, Employee $employee): array
    {
        /** @var array{effective_date: string, reason?: string|null, notes?: string|null, components: list<array{name: string, type: string, amount: float|int|string}>} $validated */
        $validated = $request->validate([
            'effective_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$employee->joining_date->toDateString()],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'components' => ['required', 'array', 'min:1', 'max:30'],
            'components.*.name' => ['required', 'string', 'max:100'],
            'components.*.type' => ['required', Rule::in([EmployeeSalaryComponent::TYPE_EARNING, EmployeeSalaryComponent::TYPE_DEDUCTION])],
            'components.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [
            'effective_date.after_or_equal' => 'The effective date cannot be before the joining date.',
        ], [
            'components.*.name' => 'component name',
            'components.*.amount' => 'component amount',
        ]);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EmployeeSalaryRevision $revision): array
    {
        return [
            'id' => $revision->id,
            'effective_date' => $revision->effective_date->toDateString(),
            'previous_gross' => $revision->previous_gross,
            'new_gross' => $revision->new_gross,
            'previous_components' => $revision->previous_components ?? [],
            'components' => $revision->components->map->only(['code', 'name', 'type', 'amount'])->values(),
            'reason' => $revision->reason,
            'notes' => $revision->notes,
            'changed_by' => $revision->relationLoaded('creator') ? $revision->creator?->name : null,
            'created_at' => $revision->created_at?->toIso8601String(),
        ];
    }
}
