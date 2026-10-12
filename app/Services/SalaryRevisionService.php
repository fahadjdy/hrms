<?php

namespace App\Services;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryRevision;
use App\Models\FinalSettlement;
use App\Models\Payroll;
use App\Models\User;
use App\Support\Money;
use App\Support\PayrollPeriod;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Salary history. Every change adds a revision with its own components and an
 * effective date. History is append-only except for explicit admin edits
 * (`update()`), which are audited and refused once a finalized payroll or a
 * finalized / paid final settlement has paid using the revision. Neither a new
 * revision nor an edit may change the salary of days already paid.
 */
class SalaryRevisionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * Add a salary revision for an employee.
     *
     * Refused when the days it would take over (from its effective date to the
     * day before the next revision) overlap a finalized payroll of the employee
     * or a finalized / paid final settlement.
     *
     * Open payrolls calculated for the employee over those days are moved back
     * to draft, like an edit does, and returned so the admin can be told to
     * recalculate them.
     *
     * @param  array{effective_date: string, reason?: string|null, notes?: string|null, components: list<array{code?: string|null, name: string, type: string, amount: float|int|string}>}  $data
     * @return array{revision: EmployeeSalaryRevision, pending_payrolls: Collection<int, Payroll>}
     */
    public function revise(Employee $employee, array $data, ?User $user = null): array
    {
        $effectiveDate = CarbonImmutable::parse($data['effective_date'])->startOfDay();
        $components = $this->normalizeComponents($data['components']);

        return DB::transaction(function () use ($employee, $data, $effectiveDate, $components, $user): array {
            $this->lockEmployee($employee);
            $others = $this->freshRevisions($employee);

            if ($others->contains(fn (EmployeeSalaryRevision $other): bool => $other->effective_date->isSameDay($effectiveDate))) {
                throw ValidationException::withMessages([
                    'effective_date' => 'A salary revision already exists for this date. Choose a different effective date.',
                ]);
            }

            $to = $this->nextDateAfter($others, $effectiveDate)?->subDay();
            $lockedReason = $this->lockedReason($effectiveDate, $to, $this->lockingPeriods($employee), adding: true);

            if ($lockedReason !== null) {
                throw ValidationException::withMessages(['effective_date' => $lockedReason]);
            }

            $previous = $others
                ->filter(fn (EmployeeSalaryRevision $other): bool => $other->effective_date->lt($effectiveDate))
                ->last();

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

            $this->createComponents($revision, $components);

            // A revision added before an existing one changes that one's "previous" values.
            $this->recomputePreviousValues($employee);
            $pending = $this->moveOpenPayrollsBackToDraft($employee, $effectiveDate, $to);

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

            return [
                'revision' => $revision->refresh()->load('components'),
                'pending_payrolls' => $pending,
            ];
        });
    }

    /**
     * Edit an existing revision: its effective date, reason, notes and components.
     *
     * The edit affects the days from the earlier of the old and new effective
     * dates up to the day before the later of the next revision after the old
     * position and the next revision after the new one (open-ended when there
     * is none). It is refused when those days overlap a finalized payroll of
     * the employee or a finalized / paid final settlement, because they already
     * paid using this salary. An edit of the reason or notes alone changes no
     * pay and is always allowed.
     *
     * Open payrolls (not finalized) calculated for the employee over the
     * affected days are moved back to draft, so they cannot be finalized with
     * the old salary; they are returned so the admin can be told to recalculate
     * them. The "previous" values of every revision are recomputed, since moving
     * or changing one revision changes its neighbours.
     *
     * @param  array{effective_date: string, reason?: string|null, notes?: string|null, components: list<array{code?: string|null, name: string, type: string, amount: float|int|string}>}  $data
     * @return array{revision: EmployeeSalaryRevision, pending_payrolls: Collection<int, Payroll>}
     */
    public function update(EmployeeSalaryRevision $revision, array $data, ?User $user = null): array
    {
        $employee = $revision->employee;
        $newDate = CarbonImmutable::parse($data['effective_date'])->startOfDay();
        $components = $this->normalizeComponents($data['components']);

        return DB::transaction(function () use ($revision, $employee, $data, $newDate, $components): array {
            $this->lockEmployee($employee);

            $revision = EmployeeSalaryRevision::query()
                ->whereKey($revision->id)
                ->where('employee_id', $employee->id)
                ->with('components')
                ->lockForUpdate()
                ->firstOrFail();
            $oldDate = $revision->effective_date->startOfDay();
            $oldComponents = $this->componentSnapshot($revision);
            $others = $this->freshRevisions($employee)
                ->reject(fn (EmployeeSalaryRevision $other): bool => $other->id === $revision->id)
                ->values();

            if ($others->contains(fn (EmployeeSalaryRevision $other): bool => $other->effective_date->isSameDay($newDate))) {
                throw ValidationException::withMessages([
                    'effective_date' => 'A salary revision already exists for this date. Choose a different effective date.',
                ]);
            }

            $changesPay = ! $oldDate->isSameDay($newDate) || ! $this->sameComponents($oldComponents, $components);
            $from = $oldDate->min($newDate);
            $nextOld = $this->nextDateAfter($others, $oldDate);
            $nextNew = $this->nextDateAfter($others, $newDate);
            $to = $nextOld === null || $nextNew === null ? null : $nextOld->max($nextNew)->subDay();

            if ($changesPay) {
                $lockedReason = $this->lockedReason($from, $to, $this->lockingPeriods($employee));

                if ($lockedReason !== null) {
                    throw ValidationException::withMessages(['revision' => $lockedReason]);
                }

                $revision->components()->delete();
                $this->createComponents($revision, $components);
            }

            $old = [
                'effective_date' => $oldDate->toDateString(),
                'gross' => $revision->new_gross,
                'components' => $oldComponents,
                'reason' => $revision->reason,
                'notes' => $revision->notes,
            ];

            $revision->fill([
                'effective_date' => $newDate->toDateString(),
                'new_gross' => $this->gross($components),
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $revision->unsetRelation('components');
            $pending = new Collection;

            if ($changesPay) {
                $this->recomputePreviousValues($employee);
                $pending = $this->moveOpenPayrollsBackToDraft($employee, $from, $to);
            }

            $this->audit->log(
                'salary.revision_updated',
                $revision,
                $old,
                [
                    'effective_date' => $newDate->toDateString(),
                    'gross' => $revision->new_gross,
                    'components' => $this->componentSnapshot($revision),
                    'reason' => $revision->reason,
                    'notes' => $revision->notes,
                ],
                "Salary revision of {$employee->full_name} from {$oldDate->toDateString()} edited",
                $employee->id,
            );

            // Drop the cached history so the next read includes the edit.
            $employee->unsetRelation('salaryRevisions');

            return [
                'revision' => $revision->refresh()->load('components'),
                'pending_payrolls' => $pending,
            ];
        });
    }

    /**
     * Periods already paid for the employee: finalized payrolls that include the
     * employee and the finalized or paid final settlement. The salary in effect
     * on these days can no longer change.
     *
     * @return list<array{kind: string, label: string, status: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function lockingPeriods(Employee $employee): array
    {
        $periods = [];

        $payrolls = Payroll::query()
            ->where('status', PayrollStatus::Finalized->value)
            ->whereHas('items', fn ($query) => $query->where('employee_id', $employee->id))
            ->orderBy('period_start')
            ->get();

        foreach ($payrolls as $payroll) {
            $periods[] = [
                'kind' => 'payroll',
                'label' => $payroll->label(),
                'status' => $payroll->status->value,
                'start' => $payroll->period_start,
                'end' => $payroll->period_end,
            ];
        }

        $settlements = FinalSettlement::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [FinalSettlement::STATUS_FINALIZED, FinalSettlement::STATUS_PAID])
            ->get();
        $startDay = $this->tenant->settings()->payroll_period_start_day;

        foreach ($settlements as $settlement) {
            $periods[] = [
                'kind' => 'settlement',
                'label' => PayrollPeriod::containing($settlement->period_start, $startDay)->label(),
                'status' => $settlement->status,
                'start' => $settlement->period_start,
                'end' => $settlement->period_end,
            ];
        }

        return $periods;
    }

    /**
     * Why the salary on the days from `$from` to `$to` (open-ended when null)
     * can no longer change, or null when it can.
     *
     * @param  list<array{kind: string, label: string, status: string, start: CarbonImmutable, end: CarbonImmutable}>  $lockingPeriods  from `lockingPeriods()`
     * @param  bool  $adding  true when a new revision is being added rather than one edited
     */
    public function lockedReason(CarbonImmutable $from, ?CarbonImmutable $to, array $lockingPeriods, bool $adding = false): ?string
    {
        $payrolls = [];
        $parts = [];

        foreach ($lockingPeriods as $period) {
            $overlaps = ($to === null || $period['start']->toDateString() <= $to->toDateString())
                && $period['end']->toDateString() >= $from->toDateString();

            if (! $overlaps) {
                continue;
            }

            if ($period['kind'] === 'payroll') {
                $payrolls[] = $period['label'];
            } else {
                $parts[] = "the final settlement for {$period['label']} is {$period['status']}";
            }
        }

        if ($payrolls === [] && $parts === []) {
            return null;
        }

        if ($payrolls !== []) {
            array_unshift($parts, count($payrolls) === 1
                ? "payroll for {$payrolls[0]} is finalized"
                : 'payrolls for '.Arr::join($payrolls, ', ', ' and ').' are finalized');
        }

        $sentence = ucfirst(Arr::join($parts, ', ', ' and ')).($adding ? ' and covers this date.' : ' and used this salary.');

        if ($payrolls === []) {
            return $sentence.($adding ? ' A revision can no longer take effect on this date.' : ' This revision can no longer be changed.');
        }

        return $sentence.' '.(count($payrolls) === 1 ? 'Reopen it' : 'Reopen them')
            .($adding ? ' before adding a revision on this date.' : ' before editing this revision.');
    }

    /**
     * For each revision of the employee, why its pay can no longer be edited
     * (null when it can): its own days, from its effective date to the day
     * before the next revision, overlap a finalized payroll or settlement.
     * Its reason and notes stay editable either way.
     *
     * @return array<int, string|null> keyed by revision id
     */
    public function lockedReasons(Employee $employee): array
    {
        $periods = $this->lockingPeriods($employee);
        $revisions = $this->revisions($employee);
        $reasons = [];

        foreach ($revisions as $index => $revision) {
            $next = $revisions->get($index + 1);
            $reasons[$revision->id] = $this->lockedReason($revision->effective_date, $next?->effective_date->subDay(), $periods);
        }

        return $reasons;
    }

    /**
     * Open payrolls whose period overlaps the days from `$from` to `$to`
     * (open-ended when null) and that were calculated for the employee, or are
     * being calculated right now, go back to draft: they must be recalculated
     * with the changed salary before they can be finalized. Clearing
     * `calculation_started_at` tells a running calculation its result is stale.
     * Manual adjustments are kept and re-applied by the recalculation.
     *
     * @return Collection<int, Payroll> the payrolls concerned, oldest first
     */
    private function moveOpenPayrollsBackToDraft(Employee $employee, CarbonImmutable $from, ?CarbonImmutable $to): Collection
    {
        // Ids first, then lock by primary key, so the lock covers these rows only.
        $ids = Payroll::query()
            ->where('status', '!=', PayrollStatus::Finalized->value)
            ->where('period_end', '>=', $from->toDateString())
            ->when($to !== null, fn ($query) => $query->where('period_start', '<=', $to?->toDateString()))
            ->where(fn ($query) => $query
                ->whereHas('items', fn ($items) => $items->where('employee_id', $employee->id))
                ->orWhereNotNull('calculation_started_at'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $payrolls = Payroll::query()
            ->whereKey($ids)
            ->where('status', '!=', PayrollStatus::Finalized->value)
            ->lockForUpdate()
            ->get()
            ->sortBy(fn (Payroll $payroll): string => $payroll->period_start->toDateString())
            ->values();

        foreach ($payrolls as $payroll) {
            if ($payroll->status === PayrollStatus::Draft && $payroll->calculation_started_at === null) {
                continue;
            }

            $previousStatus = $payroll->status;
            $payroll->status = PayrollStatus::Draft;
            $payroll->calculation_started_at = null;
            // Its figures no longer reflect the salary history.
            $payroll->calculated_at = null;
            $payroll->save();

            $this->audit->log(
                'payroll.reset_to_draft',
                $payroll,
                ['status' => $previousStatus->value],
                ['status' => PayrollStatus::Draft->value],
                "Payroll for {$payroll->label()} moved back to draft: the salary of {$employee->full_name} changed",
                $employee->id,
            );
        }

        return $payrolls->toBase();
    }

    /**
     * Lock the employee row for the rest of the transaction, so salary changes
     * and payroll finalization for the same employee run one after the other.
     */
    private function lockEmployee(Employee $employee): void
    {
        Employee::query()->whereKey($employee->id)->lockForUpdate()->value('id');
    }

    /**
     * The employee's revisions read from the database (not the cached relation), oldest first.
     *
     * @return Collection<int, EmployeeSalaryRevision>
     */
    private function freshRevisions(Employee $employee): Collection
    {
        return EmployeeSalaryRevision::query()
            ->where('employee_id', $employee->id)
            ->with('components')
            ->orderBy('effective_date')
            ->get()
            ->toBase();
    }

    /**
     * @param  Collection<int, EmployeeSalaryRevision>  $revisions  oldest first
     */
    private function nextDateAfter(Collection $revisions, CarbonImmutable $date): ?CarbonImmutable
    {
        return $revisions
            ->first(fn (EmployeeSalaryRevision $revision): bool => $revision->effective_date->gt($date))
            ?->effective_date;
    }

    /**
     * @param  list<array{code: string, name: string, type: string, amount: float}>  $stored
     * @param  list<array{code: string, name: string, type: string, amount: float}>  $normalized
     */
    private function sameComponents(array $stored, array $normalized): bool
    {
        $key = fn (array $components): array => array_map(
            fn (array $component): string => implode('|', [
                $component['code'], $component['name'], $component['type'], number_format($component['amount'], 2, '.', ''),
            ]),
            $components,
        );

        return $key($stored) === $key($normalized);
    }

    /**
     * @param  list<array{code: string, name: string, type: string, amount: float}>  $components
     */
    private function createComponents(EmployeeSalaryRevision $revision, array $components): void
    {
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
