<?php

namespace Tests\Feature\Http;

use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class LeaveEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_adding_leave_saves_it_as_pending(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post('/leaves', [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType('CL')->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-11',
            'reason' => 'Family function',
        ]);

        $response->assertSessionHasNoErrors();
        $leave = EmployeeLeave::query()->sole();
        $this->assertSame(LeaveStatus::Pending, $leave->status);
        $this->assertSame(2.0, $leave->days);
        $this->assertSame('Family function', $leave->reason);
        $this->assertSame($admin->id, $leave->created_by);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_adding_leave_as_approved_shows_it_in_attendance(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post('/leaves', [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType('UL')->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
            'status' => 'approved',
        ]);

        $response->assertSessionHasNoErrors();
        $leave = EmployeeLeave::query()->sole();
        $this->assertSame(LeaveStatus::Approved, $leave->status);
        $this->assertSame($admin->id, $leave->decided_by);
        $this->assertSame('unpaid_leave', Attendance::query()->sole()->status->value);
    }

    public function test_leave_end_date_cannot_be_before_its_start_date(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/leaves', [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType('CL')->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-08',
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertSame(0, EmployeeLeave::query()->count());
    }

    public function test_overlapping_leave_is_rejected_with_a_message(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->leave($employee, 'CL', '2026-09-10', '2026-09-12');

        $response = $this->actingAs($this->adminOf($company))->post('/leaves', [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->leaveType('SL')->id,
            'start_date' => '2026-09-12',
            'end_date' => '2026-09-13',
        ]);

        $response->assertSessionHasErrors(['start_date' => 'This employee already has leave in the selected dates.']);
        $this->assertSame(1, EmployeeLeave::query()->count());
    }

    #[TestWith(['approve', 'approved', 2])]
    #[TestWith(['reject', 'rejected', 0])]
    #[TestWith(['cancel', 'cancelled', 0])]
    public function test_decision_endpoint_moves_pending_leave_to_the_chosen_status(string $decision, string $status, int $attendanceDays): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->leave($employee, 'CL', '2026-09-10', '2026-09-11');

        $response = $this->actingAs($this->adminOf($company))->put("/leaves/{$leave->id}/decision", ['decision' => $decision]);

        $response->assertSessionHasNoErrors();
        $this->assertSame($status, $leave->refresh()->status->value);
        $this->assertSame($attendanceDays, Attendance::query()->count());
    }

    public function test_cancelling_approved_leave_through_the_endpoint_clears_its_attendance(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->leave($employee, 'CL', '2026-09-10', '2026-09-11', approved: true);

        $this->actingAs($this->adminOf($company))->put("/leaves/{$leave->id}/decision", ['decision' => 'cancel']);

        $this->assertSame(LeaveStatus::Cancelled, $leave->refresh()->status);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_unknown_decision_is_rejected(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->leave($employee, 'CL', '2026-09-10', '2026-09-11');

        $response = $this->actingAs($this->adminOf($company))->put("/leaves/{$leave->id}/decision", ['decision' => 'delete']);

        $response->assertSessionHasErrors('decision');
        $this->assertSame(LeaveStatus::Pending, $leave->refresh()->status);
    }

    public function test_rejected_leave_cannot_be_approved_through_the_endpoint(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->leave($employee, 'CL', '2026-09-10', '2026-09-11');
        app(LeaveService::class)->reject($leave);

        $response = $this->actingAs($this->adminOf($company))->put("/leaves/{$leave->id}/decision", ['decision' => 'approve']);

        $response->assertSessionHasErrors(['leave' => 'Only a pending leave can be approved.']);
        $this->assertSame(LeaveStatus::Rejected, $leave->refresh()->status);
    }

    public function test_editing_leave_updates_its_dates_and_days(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->leave($employee, 'CL', '2026-09-10', '2026-09-11');

        $response = $this->actingAs($this->adminOf($company))->put("/leaves/{$leave->id}", [
            'leave_type_id' => $this->leaveType('SL')->id,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-16',
            'reason' => 'Fever',
        ]);

        $response->assertSessionHasNoErrors();
        $leave->refresh();
        $this->assertSame('2026-09-14', $leave->start_date->toDateString());
        $this->assertSame(3.0, $leave->days);
        $this->assertSame($this->leaveType('SL')->id, $leave->leave_type_id);
    }

    public function test_leave_list_shows_records_with_the_pending_count(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao']);
        $this->leave($employee, 'CL', '2026-09-10', '2026-09-11');
        $this->leave($employee, 'SL', '2026-09-21', '2026-09-21', approved: true);

        $response = $this->actingAs($this->adminOf($company))->get('/leaves?status=approved');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('leaves.data', 1)
            ->where('leaves.data.0.employee.name', 'Asha Rao')
            ->where('leaves.data.0.leave_type', 'Sick Leave')
            ->where('leaves.data.0.status', 'approved')
            ->where('leaves.data.0.days', 1)
            ->where('counts.pending', 1)
            ->has('leaveTypes', 4));
    }

    public function test_viewer_role_can_see_leave_but_not_change_it(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $leave = $this->leave($employee, 'CL', '2026-09-10', '2026-09-11');
        $this->actingAs($this->userWithRole($company, 'viewer'));

        $this->get('/leaves')->assertOk();
        $this->post('/leaves', ['employee_id' => $employee->id])->assertForbidden();
        $this->put("/leaves/{$leave->id}/decision", ['decision' => 'approve'])->assertForbidden();
        $this->put('/leave-balances', [])->assertForbidden();

        $this->assertSame(LeaveStatus::Pending, $leave->refresh()->status);
    }

    public function test_leave_balance_endpoint_saves_the_allocation_and_adjustment(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $casual = $this->leaveType('CL');

        $response = $this->actingAs($this->adminOf($company))->put('/leave-balances', [
            'employee_id' => $employee->id, 'leave_type_id' => $casual->id, 'year' => 2026, 'allocated' => 10, 'adjustment' => -1.5,
        ]);

        $response->assertSessionHasNoErrors();
        $balance = LeaveBalance::query()->sole();
        $this->assertSame(10.0, $balance->allocated);
        $this->assertSame(-1.5, $balance->adjustment);

        $this->get('/leave-balances?year=2026')->assertInertia(fn (Assert $page) => $page
            ->where('year', 2026)
            ->has('employees.data', 1)
            ->where('employees.data.0.balances', fn ($balances) => collect($balances)->firstWhere('code', 'CL')['remaining'] == 8.5));
    }

    public function test_leave_type_code_must_be_unique_in_the_company(): void
    {
        $company = $this->prepareCompany();
        $this->actingAs($this->adminOf($company));

        $this->post('/leave-types', ['name' => 'Compensatory Off', 'code' => 'CO', 'is_paid' => true, 'annual_allowance' => 6])
            ->assertSessionHasNoErrors();
        $this->post('/leave-types', ['name' => 'Casual Again', 'code' => 'CL', 'is_paid' => true, 'annual_allowance' => 6])
            ->assertSessionHasErrors(['code' => 'The code has already been taken.']);

        $this->assertSame(5, LeaveType::query()->count());
    }

    public function test_leave_type_with_recorded_leave_cannot_be_deleted(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->leave($employee, 'CL', '2026-09-10', '2026-09-11');
        $used = $this->leaveType('CL');
        $unused = $this->leaveType('PL');
        $this->actingAs($this->adminOf($company));

        $this->delete("/leave-types/{$used->id}")->assertSessionHasErrors('leave_type');
        $this->delete("/leave-types/{$unused->id}")->assertSessionHasNoErrors();

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');

        return $this->useCompany($this->createCompany());
    }

    private function leaveType(string $code): LeaveType
    {
        return LeaveType::query()->where('code', $code)->sole();
    }

    private function leave(Employee $employee, string $typeCode, string $start, string $end, bool $approved = false): EmployeeLeave
    {
        return app(LeaveService::class)->create(
            $employee,
            [
                'leave_type_id' => $this->leaveType($typeCode)->id,
                'start_date' => $start,
                'end_date' => $end,
                'status' => $approved ? 'approved' : null,
            ],
        );
    }
}
