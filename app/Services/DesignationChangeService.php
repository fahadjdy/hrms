<?php

namespace App\Services;

use App\Enums\DesignationChangeType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDesignationChange;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Designation history: every designation an employee has held, with the date
 * it took effect. Entries are append-only; a mistake is corrected by recording
 * another change.
 */
class DesignationChangeService
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Record the designation an employee joined with. Does nothing when the
     * employee has no designation or the joining entry already exists.
     */
    public function recordInitial(Employee $employee, ?User $user = null): ?EmployeeDesignationChange
    {
        if ($employee->designation_id === null) {
            return null;
        }

        $exists = EmployeeDesignationChange::query()
            ->where('employee_id', $employee->id)
            ->exists();

        if ($exists) {
            return null;
        }

        $change = EmployeeDesignationChange::query()->create([
            'employee_id' => $employee->id,
            'from_designation_id' => null,
            'to_designation_id' => $employee->designation_id,
            'type' => DesignationChangeType::Initial,
            'effective_date' => $employee->joining_date->toDateString(),
            'reason' => null,
            'changed_by' => $user?->id,
        ]);

        $employee->unsetRelation('designationChanges');

        return $change;
    }

    /**
     * Move an employee to another designation from a date onwards.
     *
     * The employee's current designation is updated right away, so the
     * effective date can be today or earlier, never in the future.
     */
    public function change(
        Employee $employee,
        Designation $to,
        CarbonImmutable $effectiveDate,
        DesignationChangeType $type,
        ?string $reason = null,
        ?User $user = null,
    ): EmployeeDesignationChange {
        $effectiveDate = $effectiveDate->startOfDay();

        if ($employee->isPast()) {
            throw ValidationException::withMessages([
                'employee' => 'This employee has left the company, so their designation can no longer change.',
            ]);
        }

        if ($type === DesignationChangeType::Initial) {
            throw ValidationException::withMessages([
                'type' => 'Choose a promotion, demotion or role change.',
            ]);
        }

        if ($to->id === $employee->designation_id) {
            throw ValidationException::withMessages([
                'designation_id' => "{$employee->full_name} already holds the designation {$to->name}. Choose a different one.",
            ]);
        }

        if ($effectiveDate->lt($employee->joining_date)) {
            throw ValidationException::withMessages([
                'effective_date' => "The effective date cannot be before the joining date ({$employee->joining_date->toDateString()}).",
            ]);
        }

        $latest = $this->latest($employee);

        if ($latest !== null && $effectiveDate->lt($latest->effective_date)) {
            throw ValidationException::withMessages([
                'effective_date' => "The effective date cannot be before the last recorded change ({$latest->effective_date->toDateString()}).",
            ]);
        }

        // Someone who has not joined yet may still be corrected up to their joining date.
        if ($effectiveDate->gt($this->tenant->today()->max($employee->joining_date))) {
            throw ValidationException::withMessages([
                'effective_date' => 'The effective date cannot be in the future. Record the change on the day it takes effect.',
            ]);
        }

        return DB::transaction(function () use ($employee, $to, $effectiveDate, $type, $reason, $user, $latest): EmployeeDesignationChange {
            $from = $employee->designation_id;

            // An employee whose history is empty gets their joining entry with
            // the new designation, so the history starts somewhere.
            $change = EmployeeDesignationChange::query()->create([
                'employee_id' => $employee->id,
                'from_designation_id' => $from,
                'to_designation_id' => $to->id,
                'type' => $from === null && $latest === null ? DesignationChangeType::Initial : $type,
                'effective_date' => $effectiveDate->toDateString(),
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'changed_by' => $user?->id,
            ]);

            $employee->designation_id = $to->id;
            $employee->save();
            $employee->unsetRelation('designation');
            $employee->unsetRelation('designationChanges');

            $fromName = $from !== null ? Designation::query()->whereKey($from)->value('name') : null;

            $this->audit->log(
                'employee.designation_changed',
                $employee,
                ['designation_id' => $from, 'designation' => $fromName],
                [
                    'designation_id' => $to->id,
                    'designation' => $to->name,
                    'type' => $change->type->value,
                    'effective_date' => $effectiveDate->toDateString(),
                    'reason' => $change->reason,
                ],
                $fromName !== null
                    ? "{$employee->full_name}: {$change->type->label()} from {$fromName} to {$to->name}, effective {$effectiveDate->toDateString()}"
                    : "{$employee->full_name} given the designation {$to->name}, effective {$effectiveDate->toDateString()}",
                $employee->id,
            );

            return $change;
        });
    }

    /**
     * The whole history of an employee, newest first, ready for a screen.
     *
     * @return list<array{id: int, type: string, type_label: string, from: string|null, to: string, effective_date: string, reason: string|null, changed_by: string|null, created_at: string|null}>
     */
    public function history(Employee $employee): array
    {
        return array_values($this->changes($employee)
            ->reverse()
            ->map(fn (EmployeeDesignationChange $change): array => $change->toRow())
            ->all());
    }

    /**
     * Today in the company's timezone, the latest date a change may take effect.
     */
    public function today(): CarbonImmutable
    {
        return $this->tenant->today();
    }

    /**
     * The most recent change on record, by effective date.
     */
    public function latest(Employee $employee): ?EmployeeDesignationChange
    {
        return $this->changes($employee)->last();
    }

    /**
     * All changes of an employee, oldest first.
     *
     * @return Collection<int, EmployeeDesignationChange>
     */
    private function changes(Employee $employee): Collection
    {
        if (! $employee->relationLoaded('designationChanges')) {
            $employee->load(['designationChanges' => fn ($query) => $query->with(['fromDesignation:id,name', 'toDesignation:id,name', 'changedBy:id,name'])]);
        }

        return $employee->designationChanges
            ->sortBy(fn (EmployeeDesignationChange $change): string => $change->effective_date->toDateString().'-'.str_pad((string) $change->id, 12, '0', STR_PAD_LEFT))
            ->values();
    }
}
