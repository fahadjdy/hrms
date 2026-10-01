<?php

namespace Tests\Feature\Services;

use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Services\PayrollCalculationService;
use App\Support\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class LeaveServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_leave_days_leave_out_weekly_offs_and_holidays(): void
    {
        $this->prepareCompany();
        Holiday::factory()->create(['date' => '2026-09-14']);
        $employee = $this->createEmployee();

        // Friday 11 to Tuesday 15 September: Sunday 13 is a weekly off, Monday 14 a holiday.
        $leave = $this->service()->create($employee, $this->leaveData('CL', '2026-09-11', '2026-09-15'));

        $this->assertSame(3.0, $leave->days);
    }

    public function test_half_day_leave_counts_as_half_a_day_on_its_start_date(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();

        $leave = $this->service()->create($employee, [
            ...$this->leaveData('CL', '2026-09-10', '2026-09-12'),
            'is_half_day' => true,
        ]);

        $this->assertSame(0.5, $leave->days);
        $this->assertSame('2026-09-10', $leave->end_date->toDateString());
    }

    public function test_rejects_leave_that_overlaps_existing_leave(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->service()->create($employee, $this->leaveData('CL', '2026-09-10', '2026-09-12'));

        try {
            $this->service()->create($employee, $this->leaveData('SL', '2026-09-12', '2026-09-14'));
            $this->fail('Overlapping leave was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This employee already has leave in the selected dates.',
                $exception->errors()['start_date'][0],
            );
        }

        $this->assertSame(1, EmployeeLeave::query()->count());
    }

    public function test_cancelled_leave_does_not_block_new_leave_on_the_same_dates(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->service()->cancel($this->service()->create($employee, $this->leaveData('CL', '2026-09-10', '2026-09-12')));

        $leave = $this->service()->create($employee, $this->leaveData('SL', '2026-09-10', '2026-09-11'));

        $this->assertSame(LeaveStatus::Pending, $leave->status);
    }

    public function test_new_leave_is_pending_and_does_not_touch_attendance(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();

        $leave = $this->service()->create($employee, $this->leaveData('CL', '2026-09-10', '2026-09-11'));

        $this->assertSame(LeaveStatus::Pending, $leave->status);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_approving_pending_leave_writes_it_into_attendance(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->service()->create($employee, $this->leaveData('CL', '2026-09-10', '2026-09-11'));

        $this->service()->approve($leave);

        $this->assertSame(LeaveStatus::Approved, $leave->refresh()->status);
        $this->assertNotNull($leave->decided_at);
        $this->assertSame(
            ['2026-09-10' => 'paid_leave', '2026-09-11' => 'paid_leave'],
            Attendance::query()->orderBy('date')->get()
                ->mapWithKeys(fn (Attendance $attendance): array => [$attendance->date->toDateString() => $attendance->status->value])
                ->all(),
        );
        $this->assertTrue(AuditLog::query()->where('action', 'leave.approved')->where('employee_id', $employee->id)->exists());
    }

    public function test_rejecting_pending_leave_leaves_attendance_alone(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->service()->create($employee, $this->leaveData('CL', '2026-09-10', '2026-09-11'));

        $this->service()->reject($leave);

        $this->assertSame(LeaveStatus::Rejected, $leave->refresh()->status);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_cancelling_approved_leave_removes_it_from_attendance(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->service()->create($employee, [...$this->leaveData('CL', '2026-09-10', '2026-09-11'), 'status' => 'approved']);

        $this->service()->cancel($leave);

        $this->assertSame(LeaveStatus::Cancelled, $leave->refresh()->status);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_rejected_leave_cannot_be_approved(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->service()->create($employee, $this->leaveData('CL', '2026-09-10', '2026-09-11'));
        $this->service()->reject($leave);

        try {
            $this->service()->approve($leave);
            $this->fail('A rejected leave was approved.');
        } catch (ValidationException $exception) {
            $this->assertSame('Only a pending leave can be approved.', $exception->errors()['leave'][0]);
        }

        $this->assertSame(LeaveStatus::Rejected, $leave->refresh()->status);
    }

    public function test_editing_approved_leave_moves_its_attendance_to_the_new_dates(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->service()->create($employee, [...$this->leaveData('CL', '2026-09-10', '2026-09-11'), 'status' => 'approved']);

        $this->service()->update($leave, $this->leaveData('CL', '2026-09-15', '2026-09-15'));

        $this->assertSame(1.0, $leave->refresh()->days);
        $this->assertSame(['2026-09-15'], Attendance::query()->get()->map(fn (Attendance $a): string => $a->date->toDateString())->all());
    }

    public function test_changing_approved_leave_from_paid_to_unpaid_changes_its_attendance_status(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->service()->create($employee, [...$this->leaveData('CL', '2026-09-10', '2026-09-10'), 'status' => 'approved']);

        $this->service()->update($leave, $this->leaveData('UL', '2026-09-10', '2026-09-10'));

        $this->assertSame('unpaid_leave', Attendance::query()->sole()->status->value);
    }

    public function test_leave_days_before_the_joining_date_are_not_written_to_attendance(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-09-10']);

        $this->service()->create($employee, [...$this->leaveData('CL', '2026-09-08', '2026-09-11'), 'status' => 'approved']);

        $this->assertSame(
            ['2026-09-10', '2026-09-11'],
            Attendance::query()->orderBy('date')->get()->map(fn (Attendance $a): string => $a->date->toDateString())->all(),
        );
    }

    public function test_balance_is_allocation_plus_adjustment_minus_approved_days_with_pending_tracked_separately(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();
        $casual = LeaveType::query()->where('code', 'CL')->sole();
        $this->service()->setBalance($employee, $casual, 2026, 10, 2);
        $this->service()->create($employee, [...$this->leaveData('CL', '2026-09-08', '2026-09-10'), 'status' => 'approved']);
        $this->service()->create($employee, $this->leaveData('CL', '2026-09-21', '2026-09-21'));

        $balance = collect($this->service()->balances($employee, 2026))->firstWhere('code', 'CL');

        $this->assertSame(10.0, $balance['allocated']);
        $this->assertSame(2.0, $balance['adjustment']);
        $this->assertSame(3.0, $balance['used']);
        $this->assertSame(1.0, $balance['pending']);
        $this->assertSame(9.0, $balance['remaining']);
    }

    public function test_balance_defaults_to_the_leave_types_annual_allowance(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee();

        $balance = collect($this->service()->balances($employee, 2026))->firstWhere('code', 'SL');

        $this->assertSame(12.0, $balance['allocated']);
        $this->assertSame(0.0, $balance['used']);
        $this->assertSame(12.0, $balance['remaining']);
    }

    public function test_unpaid_leave_reduces_pay_and_paid_leave_does_not(): void
    {
        $this->prepareCompany();
        // 26,000 over the 26 working days of September 2026 is 1,000 per day.
        $onUnpaidLeave = $this->createEmployee([], 26000);
        $onPaidLeave = $this->createEmployee([], 26000);
        $this->service()->create($onUnpaidLeave, [...$this->leaveData('UL', '2026-09-08', '2026-09-09'), 'status' => 'approved']);
        $this->service()->create($onPaidLeave, [...$this->leaveData('PL', '2026-09-08', '2026-09-09'), 'status' => 'approved']);

        $unpaid = $this->payFor($onUnpaidLeave);
        $paid = $this->payFor($onPaidLeave);

        $this->assertSame(2000.0, $unpaid['buckets']['unpaid_leave_deduction']['final']);
        $this->assertSame(24000.0, $unpaid['net_payable']);
        $this->assertSame(0.0, $paid['buckets']['unpaid_leave_deduction']['final']);
        $this->assertSame(26000.0, $paid['net_payable']);
    }

    private function prepareCompany(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
    }

    /**
     * @return array{leave_type_id: int, start_date: string, end_date: string}
     */
    private function leaveData(string $typeCode, string $start, string $end): array
    {
        return [
            'leave_type_id' => LeaveType::query()->where('code', $typeCode)->sole()->id,
            'start_date' => $start,
            'end_date' => $end,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payFor(Employee $employee): array
    {
        $result = app(PayrollCalculationService::class)->calculate($employee, PayrollPeriod::forMonth(2026, 9));

        return [...$result, ...PayrollCalculationService::totals($result['buckets'])];
    }

    private function service(): LeaveService
    {
        return app(LeaveService::class);
    }
}
