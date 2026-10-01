<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin-managed leave. Approved leave is written into attendance, so the
 * calendar and payroll pick it up without any extra step.
 */
class LeaveService
{
    public function __construct(
        private readonly WorkingCalendarService $calendar,
        private readonly AttendanceCalculationService $attendance,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Leave days in a range: scheduled working days only, or half a day.
     */
    public function daysFor(CarbonImmutable $start, CarbonImmutable $end, bool $halfDay): float
    {
        if ($halfDay) {
            return 0.5;
        }

        return (float) $this->calendar->workingDaysBetween($start, $end);
    }

    /**
     * @param  array{leave_type_id: int, start_date: string, end_date: string, is_half_day?: bool, reason?: string|null, notes?: string|null, status?: string|null}  $data
     */
    public function create(Employee $employee, array $data, ?User $user = null): EmployeeLeave
    {
        [$start, $end, $halfDay] = $this->range($data);
        $this->guardOverlap($employee, $start, $end);

        return DB::transaction(function () use ($employee, $data, $user, $start, $end, $halfDay): EmployeeLeave {
            $leave = new EmployeeLeave([
                'employee_id' => $employee->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_half_day' => $halfDay,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $leave->days = $this->daysFor($start, $end, $halfDay);
            $leave->status = LeaveStatus::Pending;
            $leave->created_by = $user?->id;
            $leave->save();

            $this->audit->log(
                'leave.created',
                $leave,
                null,
                ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'days' => $leave->days],
                "Leave added for {$employee->full_name} ({$start->toDateString()} to {$end->toDateString()})",
                $employee->id,
            );

            if (($data['status'] ?? null) === LeaveStatus::Approved->value) {
                $this->approve($leave, $user);
            }

            return $leave;
        });
    }

    /**
     * Change the dates or type of a leave that has not been cancelled or rejected.
     *
     * @param  array{leave_type_id: int, start_date: string, end_date: string, is_half_day?: bool, reason?: string|null, notes?: string|null}  $data
     */
    public function update(EmployeeLeave $leave, array $data, ?User $user = null): EmployeeLeave
    {
        if (in_array($leave->status, [LeaveStatus::Cancelled, LeaveStatus::Rejected], true)) {
            throw ValidationException::withMessages(['leave' => 'A cancelled or rejected leave cannot be edited.']);
        }

        [$start, $end, $halfDay] = $this->range($data);
        $this->guardOverlap($leave->employee, $start, $end, $leave->id);

        return DB::transaction(function () use ($leave, $data, $start, $end, $halfDay): EmployeeLeave {
            $old = $leave->only(['leave_type_id', 'start_date', 'end_date', 'is_half_day', 'days']);

            $leave->fill([
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_half_day' => $halfDay,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $leave->days = $this->daysFor($start, $end, $halfDay);
            $leave->save();
            $leave->unsetRelation('leaveType');

            if ($leave->status === LeaveStatus::Approved) {
                $this->attendance->removeLeave($leave);
                $this->attendance->syncLeave($leave);
            }

            $this->audit->log(
                'leave.updated',
                $leave,
                $old,
                $leave->only(['leave_type_id', 'start_date', 'end_date', 'is_half_day', 'days']),
                "Leave updated for {$leave->employee->full_name}",
                $leave->employee_id,
            );

            return $leave;
        });
    }

    public function approve(EmployeeLeave $leave, ?User $user = null): EmployeeLeave
    {
        if ($leave->status === LeaveStatus::Approved) {
            return $leave;
        }

        if ($leave->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages(['leave' => 'Only a pending leave can be approved.']);
        }

        return DB::transaction(function () use ($leave, $user): EmployeeLeave {
            $this->decide($leave, LeaveStatus::Approved, $user);
            $this->attendance->syncLeave($leave);

            return $leave;
        });
    }

    public function reject(EmployeeLeave $leave, ?User $user = null): EmployeeLeave
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages(['leave' => 'Only a pending leave can be rejected.']);
        }

        $this->decide($leave, LeaveStatus::Rejected, $user);

        return $leave;
    }

    public function cancel(EmployeeLeave $leave, ?User $user = null): EmployeeLeave
    {
        if (in_array($leave->status, [LeaveStatus::Cancelled, LeaveStatus::Rejected], true)) {
            return $leave;
        }

        return DB::transaction(function () use ($leave, $user): EmployeeLeave {
            $this->attendance->removeLeave($leave);
            $this->decide($leave, LeaveStatus::Cancelled, $user);

            return $leave;
        });
    }

    /**
     * Leave balance per leave type for an employee and year.
     *
     * Used and pending days are always summed from the leave records, so the
     * balance cannot drift from what was actually taken.
     *
     * @return list<array{leave_type_id: int, name: string, code: string, is_paid: bool, allocated: float, adjustment: float, used: float, pending: float, remaining: float}>
     */
    public function balances(Employee $employee, int $year): array
    {
        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();
        $stored = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get()
            ->keyBy('leave_type_id');

        $taken = EmployeeLeave::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Approved->value, LeaveStatus::Pending->value])
            ->whereBetween('start_date', ["{$year}-01-01", "{$year}-12-31"])
            ->selectRaw('leave_type_id, status, sum(days) as total')
            ->groupBy('leave_type_id', 'status')
            ->toBase()
            ->get();

        $balances = [];

        foreach ($types as $type) {
            $balance = $stored->get($type->id);
            $allocated = $balance->allocated ?? $type->annual_allowance;
            $adjustment = $balance->adjustment ?? 0.0;
            $used = (float) $taken->where('leave_type_id', $type->id)->where('status', LeaveStatus::Approved->value)->sum('total');
            $pending = (float) $taken->where('leave_type_id', $type->id)->where('status', LeaveStatus::Pending->value)->sum('total');

            $balances[] = [
                'leave_type_id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'is_paid' => $type->is_paid,
                'allocated' => (float) $allocated,
                'adjustment' => (float) $adjustment,
                'used' => $used,
                'pending' => $pending,
                'remaining' => round($allocated + $adjustment - $used, 1),
            ];
        }

        return $balances;
    }

    public function setBalance(Employee $employee, LeaveType $type, int $year, float $allocated, float $adjustment): LeaveBalance
    {
        $balance = LeaveBalance::query()->firstOrNew([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => $year,
        ]);

        $old = $balance->exists ? $balance->only(['allocated', 'adjustment']) : null;
        $balance->fill(['allocated' => $allocated, 'adjustment' => $adjustment])->save();

        $this->audit->log(
            'leave.balance_updated',
            $balance,
            $old,
            ['allocated' => $allocated, 'adjustment' => $adjustment, 'year' => $year, 'leave_type' => $type->name],
            "{$type->name} balance for {$employee->full_name} set for {$year}",
            $employee->id,
        );

        return $balance;
    }

    private function decide(EmployeeLeave $leave, LeaveStatus $status, ?User $user): void
    {
        $old = $leave->status;

        $leave->status = $status;
        $leave->decided_by = $user?->id;
        $leave->decided_at = now();
        $leave->save();

        $this->audit->log(
            'leave.'.$status->value,
            $leave,
            ['status' => $old->value],
            ['status' => $status->value],
            "Leave for {$leave->employee->full_name} {$status->label()}",
            $leave->employee_id,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}
     */
    private function range(array $data): array
    {
        $start = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $halfDay = (bool) ($data['is_half_day'] ?? false);
        $end = $halfDay ? $start : CarbonImmutable::parse($data['end_date'])->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
        }

        return [$start, $end, $halfDay];
    }

    private function guardOverlap(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, ?int $ignoreId = null): void
    {
        $overlaps = EmployeeLeave::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveStatus::Approved->value, LeaveStatus::Pending->value])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => 'This employee already has leave in the selected dates.',
            ]);
        }
    }
}
