<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Enums\ExitType;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The employee lifecycle: joining, changes and exit. Employees are never
 * deleted; an exit turns an employee into a past employee and keeps all of
 * their history.
 */
class EmployeeService
{
    private const array PROFILE_FIELDS = [
        'employee_code', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'email',
        'address', 'city', 'state', 'country', 'postal_code', 'joining_date', 'department_id',
        'designation_id', 'employment_type', 'reporting_manager_id', 'status', 'probation_end_date', 'notes',
    ];

    public function __construct(
        private readonly SalaryRevisionService $salaries,
        private readonly BorrowCalculationService $borrows,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create an employee, optionally with a work shift, an initial salary and
     * a borrow the employee already had when joining.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $photo = null, ?User $user = null): Employee
    {
        return DB::transaction(function () use ($data, $photo, $user): Employee {
            $employee = Employee::query()->create(Arr::only($data, self::PROFILE_FIELDS));

            if ($photo !== null) {
                $employee->photo_path = $photo->store("employees/{$employee->company_id}/photos", 'public') ?: null;
                $employee->save();
            }

            $this->audit->log(
                'employee.created',
                $employee,
                null,
                $employee->only(['employee_code', 'first_name', 'last_name', 'joining_date', 'department_id', 'designation_id']),
                "Employee {$employee->full_name} ({$employee->employee_code}) added",
                $employee->id,
            );

            if (! empty($data['work_shift_id'])) {
                $this->assignShift($employee, (int) $data['work_shift_id'], $employee->joining_date, $user);
            }

            // The salary is optional when adding an employee: rows left at zero mean
            // "not set yet", so no revision is created for them.
            $components = array_values(array_filter(
                $data['salary_components'] ?? [],
                fn (array $component): bool => (float) ($component['amount'] ?? 0) > 0,
            ));

            if ($components !== []) {
                try {
                    $this->salaries->revise($employee, [
                        'effective_date' => $employee->joining_date->toDateString(),
                        'reason' => 'Initial salary',
                        'components' => $components,
                    ], $user);
                } catch (ValidationException $exception) {
                    // Report the problem on the field the employee form actually has.
                    throw ValidationException::withMessages([
                        'salary_components' => collect($exception->errors())->flatten()->first(),
                    ]);
                }
            }

            if (! empty($data['existing_borrow']['amount'])) {
                $this->borrows->create($employee, [
                    ...$data['existing_borrow'],
                    'kind' => 'existing',
                    'borrow_date' => $data['existing_borrow']['borrow_date'] ?? $employee->joining_date->toDateString(),
                ], $user);
            }

            return $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, ?UploadedFile $photo = null): Employee
    {
        return DB::transaction(function () use ($employee, $data, $photo): Employee {
            $original = $employee->getAttributes();
            $employee->fill(Arr::only($data, self::PROFILE_FIELDS));

            if ($photo !== null) {
                if ($employee->photo_path !== null) {
                    Storage::disk('public')->delete($employee->photo_path);
                }

                $employee->photo_path = $photo->store("employees/{$employee->company_id}/photos", 'public') ?: null;
            }

            $employee->save();
            [$old, $new] = $this->audit->diff($employee, $original);

            if ($new !== []) {
                $this->audit->log('employee.updated', $employee, $old, $new, "Employee {$employee->full_name} updated", $employee->id);
            }

            return $employee;
        });
    }

    /**
     * Give the employee their own work shift from a date onwards. Passing no
     * shift ends the employee-specific timing, so the gender or company
     * default applies again.
     */
    public function assignShift(Employee $employee, ?int $workShiftId, CarbonImmutable $effectiveFrom, ?User $user = null): void
    {
        DB::transaction(function () use ($employee, $workShiftId, $effectiveFrom, $user): void {
            $from = $effectiveFrom->toDateString();

            // Close assignments that are open on the new start date and drop later ones.
            EmployeeShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('effective_from', '>=', $from)
                ->delete();

            EmployeeShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                ->update(['effective_to' => $effectiveFrom->subDay()->toDateString()]);

            if ($workShiftId !== null) {
                EmployeeShiftAssignment::query()->create([
                    'employee_id' => $employee->id,
                    'work_shift_id' => $workShiftId,
                    'effective_from' => $from,
                    'created_by' => $user?->id,
                ]);
            }

            $employee->unsetRelation('shiftAssignments');

            $this->audit->log(
                'employee.shift_changed',
                $employee,
                null,
                ['work_shift_id' => $workShiftId, 'effective_from' => $from],
                $workShiftId !== null
                    ? "Work shift assigned to {$employee->full_name} from {$from}"
                    : "Employee-specific work shift removed for {$employee->full_name} from {$from}",
                $employee->id,
            );
        });
    }

    /**
     * Record that an employee left the company. Nothing is deleted.
     *
     * @param  array{exit_date: string, last_working_date: string, exit_type: string, exit_reason?: string|null, exit_notes?: string|null}  $data
     */
    public function exit(Employee $employee, array $data): Employee
    {
        $old = ['status' => $employee->status->value];

        $employee->status = EmployeeStatus::Past;
        $employee->exit_date = CarbonImmutable::parse($data['exit_date']);
        $employee->last_working_date = CarbonImmutable::parse($data['last_working_date']);
        $employee->exit_type = ExitType::from($data['exit_type']);
        $employee->exit_reason = $data['exit_reason'] ?? null;
        $employee->exit_notes = $data['exit_notes'] ?? null;
        $employee->save();

        $this->audit->log(
            'employee.exited',
            $employee,
            $old,
            $employee->only(['status', 'exit_date', 'last_working_date', 'exit_type', 'exit_reason']),
            "{$employee->full_name} left the company (last working day {$employee->last_working_date->toDateString()})",
            $employee->id,
        );

        return $employee;
    }

    /**
     * Bring a past employee back, e.g. when an exit was recorded by mistake.
     */
    public function reinstate(Employee $employee): Employee
    {
        $old = $employee->only(['status', 'exit_date', 'last_working_date', 'exit_type']);

        $employee->status = EmployeeStatus::Active;
        $employee->exit_date = null;
        $employee->last_working_date = null;
        $employee->exit_type = null;
        $employee->exit_reason = null;
        $employee->exit_notes = null;
        $employee->save();

        $this->audit->log('employee.reinstated', $employee, $old, ['status' => 'active'], "{$employee->full_name} reinstated as an active employee", $employee->id);

        return $employee;
    }

    /**
     * Suggest the next employee code, following the highest existing numeric suffix.
     */
    public function nextCode(): string
    {
        $highest = Employee::query()
            ->pluck('employee_code')
            ->map(fn (string $code): int => (int) preg_replace('/\D/', '', $code))
            ->max() ?? 0;

        return 'EMP-'.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}
