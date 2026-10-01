<?php

namespace Tests\Feature\Http;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class AttendanceEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_marking_a_day_saves_the_attendance_with_a_log_and_an_audit_entry(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->put("/employees/{$employee->id}/attendance/2026-09-10", [
            'status' => 'present',
            'check_in' => '09:32',
            'check_out' => '17:41',
            'notes' => 'Client visit',
            'reason' => 'Late biometric sync',
        ]);

        $response->assertSessionHasNoErrors();
        $attendance = Attendance::query()->sole();
        $this->assertSame('2026-09-10', $attendance->date->toDateString());
        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame(429, $attendance->worked_minutes);
        $this->assertSame(51, $attendance->short_minutes);
        $this->assertSame(Attendance::SOURCE_MANUAL, $attendance->source);
        $this->assertSame($admin->id, $attendance->modified_by);

        $log = AttendanceLog::query()->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertNull($log->old_values);
        $this->assertSame('late', $log->new_values['status']);
        $this->assertSame('Late biometric sync', $log->reason);

        $audit = AuditLog::query()->where('action', 'attendance.marked')->sole();
        $this->assertSame($employee->id, $audit->employee_id);
        $this->assertSame($admin->id, $audit->user_id);
    }

    public function test_correcting_a_day_records_the_old_and_new_values(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->markAttendance($employee, '2026-09-10', 'present');

        $this->actingAs($this->adminOf($company))
            ->put("/employees/{$employee->id}/attendance/2026-09-10", ['status' => 'half_day']);

        $this->assertSame(AttendanceStatus::HalfDay, Attendance::query()->sole()->status);
        $audit = AuditLog::query()->where('action', 'attendance.modified')->sole();
        $this->assertSame('present', $audit->old_values['status']);
        $this->assertSame('half_day', $audit->new_values['status']);
    }

    public function test_attendance_cannot_be_marked_for_a_future_date(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))
            ->put("/employees/{$employee->id}/attendance/2026-10-05", ['status' => 'present']);

        $response->assertSessionHasErrors(['status' => 'Attendance cannot be marked for a future date.']);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_attendance_cannot_be_marked_before_the_joining_date(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['joining_date' => '2026-09-15']);

        $response = $this->actingAs($this->adminOf($company))
            ->put("/employees/{$employee->id}/attendance/2026-09-10", ['status' => 'present']);

        $response->assertSessionHasErrors('status');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_check_out_is_required_when_check_in_is_given(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))
            ->put("/employees/{$employee->id}/attendance/2026-09-10", ['status' => 'present', 'check_in' => '09:00']);

        $response->assertSessionHasErrors('check_out');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_unknown_status_is_rejected(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))
            ->put("/employees/{$employee->id}/attendance/2026-09-10", ['status' => 'vacation']);

        $response->assertSessionHasErrors('status');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_clearing_a_day_removes_the_record_and_is_audited(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->markAttendance($employee, '2026-09-10', 'absent');

        $response = $this->actingAs($this->adminOf($company))
            ->delete("/employees/{$employee->id}/attendance/2026-09-10");

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, Attendance::query()->count());
        $audit = AuditLog::query()->where('action', 'attendance.cleared')->sole();
        $this->assertSame('absent', $audit->old_values['status']);
    }

    public function test_bulk_marking_saves_the_same_status_for_every_selected_employee(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employees = [$this->createEmployee(), $this->createEmployee(), $this->createEmployee()];
        $notJoinedYet = $this->createEmployee(['joining_date' => '2026-09-20']);

        $response = $this->actingAs($this->adminOf($company))->post('/attendance/bulk', [
            'date' => '2026-09-10',
            'employee_ids' => [...array_map(fn ($employee) => $employee->id, $employees), $notJoinedYet->id],
            'status' => 'wfh',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(3, Attendance::query()->where('status', 'wfh')->where('date', '2026-09-10')->count());
        $this->assertSame(0, Attendance::query()->where('employee_id', $notJoinedYet->id)->count());
    }

    public function test_bulk_marking_is_rejected_for_a_future_date(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/attendance/bulk', [
            'date' => '2026-10-02', 'employee_ids' => [$employee->id], 'status' => 'present',
        ]);

        $response->assertSessionHasErrors(['date' => 'Attendance cannot be marked for a future date.']);
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_viewer_role_cannot_mark_attendance(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->userWithRole($company, 'viewer'))
            ->put("/employees/{$employee->id}/attendance/2026-09-10", ['status' => 'absent']);

        $response->assertForbidden();
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_employee_calendar_shows_every_day_of_the_month_with_its_summary(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->markAttendance($employee, '2026-09-10', 'present', '09:32', '17:41');
        $this->markAttendance($employee, '2026-09-11', 'absent');

        $response = $this->actingAs($this->adminOf($company))
            ->get("/employees/{$employee->id}/attendance?month=2026-09");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('month', '2026-09')
            ->where('monthLabel', 'September 2026')
            ->where('previousMonth', '2026-08')
            ->where('nextMonth', '2026-10')
            ->has('days', 30)
            ->where('days.5.status', 'weekly_off')
            ->where('days.9.date', '2026-09-10')
            ->where('days.9.status', 'late')
            ->where('days.9.check_in', '09:32')
            ->where('days.9.check_out', '17:41')
            ->where('days.9.worked_minutes', 429)
            ->where('days.9.short_minutes', 51)
            ->where('days.10.status', 'absent')
            ->where('summary.working_days', 26)
            ->where('summary.present', 1)
            ->where('summary.absent', 1)
            ->where('summary.late', 1)
            ->where('summary.weekly_off', 4)
            ->where('summary.unmarked', 24)
            ->where('summary.short_minutes', 51)
            ->has('logs.2026-09-10', 1)
            ->where('shift.name', 'General Shift')
            ->where('shift.source', 'company')
            ->has('statuses', 11));
    }

    public function test_daily_attendance_counts_each_status_for_the_date(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $present = $this->createEmployee(['first_name' => 'Asha']);
        $absent = $this->createEmployee(['first_name' => 'Bina']);
        $late = $this->createEmployee(['first_name' => 'Chetan']);
        $this->createEmployee(['first_name' => 'Deepa']);
        $this->markAttendance($present, '2026-09-10', 'present');
        $this->markAttendance($absent, '2026-09-10', 'absent');
        $this->markAttendance($late, '2026-09-10', 'present', '10:00', '20:30');

        $response = $this->actingAs($this->adminOf($company))->get('/attendance?date=2026-09-10');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('date', '2026-09-10')
            ->where('isFuture', false)
            ->where('mode', 'manual')
            ->where('kpis.total', 4)
            ->where('kpis.present', 2)
            ->where('kpis.absent', 1)
            ->where('kpis.late', 1)
            ->where('kpis.overtime', 1)
            ->where('kpis.unmarked', 1)
            ->has('employees.data', 4)
            ->where('employees.data.0.name', $present->full_name)
            ->where('employees.data.0.day.status', 'present')
            ->where('employees.data.3.day.state', 'unmarked'));
    }

    public function test_daily_attendance_can_be_filtered_to_one_status(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $absent = $this->createEmployee();
        $this->createEmployee();
        $this->markAttendance($absent, '2026-09-10', 'absent');
        $this->actingAs($this->adminOf($company));

        $this->get('/attendance?date=2026-09-10&status=absent')->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $absent->id));
        $this->get('/attendance?date=2026-09-10&status=unmarked')->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.day.state', 'unmarked'));
    }

    public function test_opening_a_weekly_off_stores_it_for_every_employee(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->createEmployee();
        $this->createEmployee();

        // 27 September 2026 is a Sunday, the company's weekly off.
        $response = $this->actingAs($this->adminOf($company))->get('/attendance?date=2026-09-27');

        $response->assertInertia(fn (Assert $page) => $page->where('isWeeklyOff', true)->where('kpis.unmarked', 0));
        $this->assertSame(2, Attendance::query()->where('status', 'weekly_off')->where('source', 'automatic')->count());
    }

    public function test_generating_attendance_for_a_range_creates_the_missing_records(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
        $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/attendance/generate', [
            'start_date' => '2026-09-26', 'end_date' => '2026-09-28',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            ['present', 'weekly_off', 'present'],
            Attendance::query()->orderBy('date')->get()->map(fn (Attendance $a): string => $a->status->value)->all(),
        );
        $this->assertTrue(AuditLog::query()->where('action', 'attendance.generated')->exists());
    }
}
