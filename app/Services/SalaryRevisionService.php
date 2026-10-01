<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryRevision;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Salary history. Every change adds a revision with its own components and an
 * effective date; earlier revisions are never modified.
 */
class SalaryRevisionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Add a salary revision for an employee.
     *
     * @param  array{effective_date: string, reason?: string|null, notes?: string|null, components: list<array{code?: string|null, name: string, type: string, amount: float|int|string}>}  $data
     */
    public function revise(Employee $employee, array $data, ?User $user = null): EmployeeSalaryRevision
    {
        $effectiveDate = CarbonImmutable::parse($data['effective_date'])->startOfDay();

        $exists = EmployeeSalaryRevision::query()
            ->where('employee_id', $employee->id)
            ->where('effective_date', $effectiveDate->toDateString())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'effective_date' => 'A salary revision already exists for this date. Choose a different effective date.',
            ]);
        }

        return DB::transaction(function () use ($employee, $data, $effectiveDate, $user): EmployeeSalaryRevision {
            $previous = $this->revisionOn($employee, $effectiveDate);
            $components = $this->normalizeComponents($data['components']);

            $revision = EmployeeSalaryRevision::query()->create([
                'employee_id' => $employee->id,
                'effective_date' => $effectiveDate->toDateString(),
                'previous_gross' => $previous->new_gross ?? 0,
                'new_gross' => $this->gross($components),
                'previous_components' => $previous?->components
                    ->map(fn (EmployeeSalaryComponent $component): array => [
                        'code' => $component->code,
                        'name' => $component->name,
                        'type' => $component->type,
                        'amount' => $component->amount,
                    ])
                    ->all(),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($components as $index => $component) {
                EmployeeSalaryComponent::query()->create([
                    'employee_salary_revision_id' => $revision->id,
                    'code' => $component['code'],
                    'name' => $component['name'],
                    'type' => $component['type'],
                    'amount' => $component['amount'],
                    'sort_order' => $index,
                ]);
            }

            $this->audit->log(
                $previous === null ? 'salary.created' : 'salary.revised',
                $revision,
                $previous !== null ? ['gross' => $previous->new_gross] : null,
                ['gross' => $revision->new_gross, 'effective_date' => $effectiveDate->toDateString(), 'reason' => $revision->reason],
                "Salary for {$employee->full_name} set to {$revision->new_gross} from {$effectiveDate->toDateString()}",
                $employee->id,
            );

            // Drop the cached history so the next read includes this revision.
            $employee->unsetRelation('salaryRevisions');

            return $revision->load('components');
        });
    }

    /**
     * The revision in effect on a date (today by default), or null when no salary is set yet.
     */
    public function revisionOn(Employee $employee, ?CarbonImmutable $date = null): ?EmployeeSalaryRevision
    {
        $date ??= CarbonImmutable::today();

        return $this->revisions($employee)
            ->filter(fn (EmployeeSalaryRevision $revision): bool => $revision->effective_date->toDateString() <= $date->toDateString())
            ->last();
    }

    /**
     * Split a payroll period into stretches of days, each covered by one revision.
     *
     * Days before the first revision have no salary and are left out.
     *
     * @return list<array{revision: EmployeeSalaryRevision, from: CarbonImmutable, to: CarbonImmutable, days: int}>
     */
    public function segments(Employee $employee, PayrollPeriod $period): array
    {
        $revisions = $this->revisions($employee);
        $segments = [];

        foreach ($revisions as $index => $revision) {
            $next = $revisions->get($index + 1);
            $from = $revision->effective_date->max($period->start);
            $to = $next !== null ? $next->effective_date->subDay()->min($period->end) : $period->end;

            if ($from->gt($period->end) || $to->lt($period->start) || $to->lt($from)) {
                continue;
            }

            $segments[] = [
                'revision' => $revision,
                'from' => $from,
                'to' => $to,
                'days' => (int) $from->diffInDays($to) + 1,
            ];
        }

        return $segments;
    }

    /**
     * All revisions of an employee, oldest first, with their components.
     *
     * @return Collection<int, EmployeeSalaryRevision>
     */
    public function revisions(Employee $employee): Collection
    {
        if (! $employee->relationLoaded('salaryRevisions')) {
            $employee->load(['salaryRevisions' => fn ($query) => $query->with('components')]);
        }

        return $employee->salaryRevisions
            ->sortBy(fn (EmployeeSalaryRevision $revision): string => $revision->effective_date->toDateString())
            ->values();
    }

    /**
     * @param  list<array{code?: string|null, name: string, type: string, amount: float|int|string}>  $components
     * @return list<array{code: string, name: string, type: string, amount: float}>
     */
    private function normalizeComponents(array $components): array
    {
        $normalized = [];

        foreach ($components as $component) {
            $amount = Money::round($component['amount']);

            if ($amount <= 0) {
                continue;
            }

            $normalized[] = [
                'code' => Str::slug(($component['code'] ?? null) ?: $component['name'], '_'),
                'name' => $component['name'],
                'type' => $component['type'] === EmployeeSalaryComponent::TYPE_DEDUCTION
                    ? EmployeeSalaryComponent::TYPE_DEDUCTION
                    : EmployeeSalaryComponent::TYPE_EARNING,
                'amount' => $amount,
            ];
        }

        if ($this->gross($normalized) <= 0) {
            throw ValidationException::withMessages([
                'components' => 'Add at least one earning with an amount greater than zero.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array{code: string, name: string, type: string, amount: float}>  $components
     */
    private function gross(array $components): float
    {
        return Money::sum(array_column(
            array_filter($components, fn (array $component): bool => $component['type'] === EmployeeSalaryComponent::TYPE_EARNING),
            'amount',
        ));
    }
}
