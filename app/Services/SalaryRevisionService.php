<?php

namespace App\Services;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryRevision;
use App\Models\Payroll;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Salary history. Every change adds a revision with its own components and an
 * effective date. History is append-only except for explicit admin edits
 * (`update()`), which are audited and refused once a finalized payroll has
 * paid using the revision.
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
                'previous_components' => $previous !== null ? $this->componentSnapshot($previous) : null,
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
     * Edit an existing revision: its effective date, reason, notes and components.
     *
     * Refused when a finalized payroll of the employee ends on or after the
     * earlier of the old and new effective dates, because that payroll already
     * paid using this salary. The "previous" values of every revision of the
     * employee are recomputed, since moving or changing one revision changes
     * its neighbours.
     *
     * Returns the edited revision and the payrolls not yet finalized that were
     * calculated for the affected dates and must be recalculated to apply it.
     *
     * @param  array{effective_date: string, reason?: string|null, notes?: string|null, components: list<array{code?: string|null, name: string, type: string, amount: float|int|string}>}  $data
     * @return array{revision: EmployeeSalaryRevision, pending_payrolls: Collection<int, Payroll>}
     */
    public function update(EmployeeSalaryRevision $revision, array $data, ?User $user = null): array
    {
        $employee = $revision->employee;
        $oldDate = $revision->effective_date->startOfDay();
        $newDate = CarbonImmutable::parse($data['effective_date'])->startOfDay();
        $affectedFrom = $oldDate->min($newDate);

        return DB::transaction(function () use ($revision, $employee, $data, $oldDate, $newDate, $affectedFrom): array {
            $lockedReason = $this->lockedReason($affectedFrom, $this->finalizedPayrolls($employee));

            if ($lockedReason !== null) {
                throw ValidationException::withMessages(['revision' => $lockedReason]);
            }

            $taken = EmployeeSalaryRevision::query()
                ->where('employee_id', $employee->id)
                ->whereKeyNot($revision->id)
                ->where('effective_date', $newDate->toDateString())
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'effective_date' => 'A salary revision already exists for this date. Choose a different effective date.',
                ]);
            }

            $components = $this->normalizeComponents($data['components']);
            $old = [
                'effective_date' => $oldDate->toDateString(),
                'gross' => $revision->new_gross,
                'components' => $this->componentSnapshot($revision),
            ];

            $revision->components()->delete();

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

            $revision->fill([
                'effective_date' => $newDate->toDateString(),
                'new_gross' => $this->gross($components),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $revision->unsetRelation('components');
            $this->recomputePreviousValues($employee);

            $this->audit->log(
                'salary.revision_updated',
                $revision,
                $old,
                [
                    'effective_date' => $newDate->toDateString(),
                    'gross' => $revision->new_gross,
                    'components' => $this->componentSnapshot($revision),
                ],
                "Salary revision of {$employee->full_name} from {$oldDate->toDateString()} edited",
                $employee->id,
            );

            // Drop the cached history so the next read includes the edit.
            $employee->unsetRelation('salaryRevisions');

            return [
                'revision' => $revision->refresh()->load('components'),
                'pending_payrolls' => $this->pendingPayrolls($employee, $affectedFrom),
            ];
        });
    }

    /**
     * Finalized payrolls that include the employee, oldest first.
     *
     * @return Collection<int, Payroll>
     */
    public function finalizedPayrolls(Employee $employee): Collection
    {
        return Payroll::query()
            ->where('status', PayrollStatus::Finalized->value)
            ->whereHas('items', fn ($query) => $query->where('employee_id', $employee->id))
            ->orderBy('period_start')
            ->get()
            ->toBase();
    }

    /**
     * Why a revision taking effect on a date can no longer be edited, or null
     * when it can: a finalized payroll ending on or after that date paid using it.
     *
     * @param  Collection<int, Payroll>  $finalizedPayrolls  from `finalizedPayrolls()`
     */
    public function lockedReason(CarbonImmutable $effectiveDate, Collection $finalizedPayrolls): ?string
    {
        $labels = $finalizedPayrolls
            ->filter(fn (Payroll $payroll): bool => $payroll->period_end->toDateString() >= $effectiveDate->toDateString())
            ->map(fn (Payroll $payroll): string => $payroll->label())
            ->values()
            ->all();

        if ($labels === []) {
            return null;
        }

        return count($labels) === 1
            ? "Payroll for {$labels[0]} is finalized and used this salary. Reopen it before editing this revision."
            : 'Payrolls for '.Arr::join($labels, ', ', ' and ').' are finalized and used this salary. Reopen them before editing this revision.';
    }

    /**
     * Payrolls not yet finalized that were calculated for the employee and end
     * on or after a date: they must be recalculated to pick up a salary edit.
     *
     * @return Collection<int, Payroll>
     */
    public function pendingPayrolls(Employee $employee, CarbonImmutable $from): Collection
    {
        return Payroll::query()
            ->where('status', '!=', PayrollStatus::Finalized->value)
            ->where('period_end', '>=', $from->toDateString())
            ->whereHas('items', fn ($query) => $query->where('employee_id', $employee->id))
            ->orderBy('period_start')
            ->get()
            ->toBase();
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
     * Set each revision's previous gross and components from the revision
     * before it in effective-date order.
     */
    private function recomputePreviousValues(Employee $employee): void
    {
        $previous = null;

        $revisions = EmployeeSalaryRevision::query()
            ->where('employee_id', $employee->id)
            ->with('components')
            ->orderBy('effective_date')
            ->get();

        foreach ($revisions as $revision) {
            $revision->fill([
                'previous_gross' => $previous->new_gross ?? 0,
                'previous_components' => $previous !== null ? $this->componentSnapshot($previous) : null,
            ])->save();

            $previous = $revision;
        }
    }

    /**
     * The components of a revision as stored in a later revision's `previous_components`.
     *
     * @return list<array{code: string, name: string, type: string, amount: float}>
     */
    private function componentSnapshot(EmployeeSalaryRevision $revision): array
    {
        return array_values($revision->components
            ->map(fn (EmployeeSalaryComponent $component): array => [
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
                'amount' => $component->amount,
            ])
            ->all());
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
