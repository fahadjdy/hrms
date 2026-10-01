<?php

namespace Tests\Feature\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\WeeklyHoliday;
use App\Services\AttendanceCalculationService;
use App\Services\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class AttendanceCalculationServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function statusesWithoutHours(): array
    {
        return [
            'absent' => ['absent', 'absent'],
            'paid leave' => ['paid_leave', 'paid_leave'],
            'unpaid leave' => ['unpaid_leave', 'unpaid_leave'],
            'work from home' => ['wfh', 'wfh'],
            'half day' => ['half_day', 'half_day'],
        ];
    }

    #[DataProvider('statusesWithoutHours')]
    public function test_marked_status_is_counted_in_the_monthly_summary(string $status, string $summaryKey): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $this->markAttendance($employee, '2026-09-10', $status);

        $summary = $this->summaryFor($employee, '2026-09-01', '2026-09-30');
        $this->assertSame(1, $summary[$summaryKey]);
    }

    public function test_present_day_without_times_counts_the_full_required_hours_as_worked(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $this->markAttendance($employee, '2026-09-10', 'present');

        $attendance = Attendance::query()->sole();
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame(480, $attendance->required_minutes);
        $this->assertSame(480, $attendance->worked_minutes);
        $this->assertSame(0, $attendance->short_minutes);
    }

    public function test_late_check_in_marks_the_day_as_late(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $this->markAttendance($employee, '2026-09-10', 'present', '09:45', '18:45');

        $attendance = Attendance::query()->sole();
        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame(45, $attendance->late_minutes);
        $this->assertSame(1, $this->summaryFor($employee, '2026-09-01', '2026-09-30')['late']);
    }

    public function test_working_seven_of_eight_required_hours_records_one_short_hour(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        // 09:00 to 17:00 is 8h at work; less the 1h break that is 7h worked.
        $this->markAttendance($employee, '2026-09-10', 'present', '09:00', '17:00');

        $attendance = Attendance::query()->sole();
        $this->assertSame(AttendanceStatus::ShortHours, $attendance->status);
        $this->assertSame(480, $attendance->required_minutes);
        $this->assertSame(420, $attendance->worked_minutes);
        $this->assertSame(60, $attendance->short_minutes);
    }

    public function test_weekly_off_and_holiday_are_derived_and_never_count_as_absence(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        Holiday::factory()->create(['name' => 'Founders Day', 'date' => '2026-09-16']);
        $employee = $this->createEmployee();

        $days = collect($this->service()->resolveDays($employee, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30')))
            ->keyBy('date');
        $summary = $this->service()->summarize($days->values()->all());

        // 6, 13, 20 and 27 September 2026 are Sundays.
        $this->assertSame('weekly_off', $days['2026-09-06']['status']);
        $this->assertSame('holiday', $days['2026-09-16']['status']);
        $this->assertSame('Founders Day', $days['2026-09-16']['holiday_name']);
        $this->assertSame(4, $summary['weekly_off']);
        $this->assertSame(1, $summary['holidays']);
        $this->assertSame(25, $summary['working_days']);
        $this->assertSame(0, $summary['absent']);
    }

    public function test_changing_the_weekly_off_days_changes_the_working_days(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        WeeklyHoliday::query()->create(['day_of_week' => 6]);
        $employee = $this->createEmployee();

        $summary = $this->summaryFor($employee, '2026-09-01', '2026-09-30');

        // September 2026 has four Saturdays and four Sundays.
        $this->assertSame(8, $summary['weekly_off']);
        $this->assertSame(22, $summary['working_days']);
    }

    public function test_unmarked_working_days_are_present_in_automatic_mode(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
        $employee = $this->createEmployee();

        $summary = $this->summaryFor($employee, '2026-09-01', '2026-09-30');

        $this->assertSame(26, $summary['present']);
        $this->assertSame(0, $summary['unmarked']);
        $this->assertSame(26 * 480, $summary['worked_minutes']);
        $this->assertSame(100.0, $summary['attendance_rate']);
    }

    public function test_unmarked_working_days_stay_unmarked_in_manual_mode(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $summary = $this->summaryFor($employee, '2026-09-01', '2026-09-30');

        $this->assertSame(0, $summary['present']);
        $this->assertSame(26, $summary['unmarked']);
    }

    public function test_days_before_joining_are_not_part_of_the_summary(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
        $employee = $this->createEmployee(['joining_date' => '2026-09-21']);

        $summary = $this->summaryFor($employee, '2026-09-01', '2026-09-30');

        $this->assertSame(20, $summary['not_employed_days']);
        $this->assertSame(10, $summary['employed_days']);
        $this->assertSame(9, $summary['present']);
    }

    public function test_approved_leave_appears_in_attendance_and_cancelling_removes_it(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $leaveType = LeaveType::query()->where('code', 'UL')->sole();
        $leaves = app(LeaveService::class);

        // Friday to Monday: the Sunday in between is a weekly off, not a leave day.
        $leave = $leaves->create($employee, [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-14',
            'status' => 'approved',
        ]);

        $this->assertSame(3.0, $leave->days);
        $this->assertSame(3, $this->summaryFor($employee, '2026-09-01', '2026-09-30')['unpaid_leave']);

        $leaves->cancel($leave);

        $this->assertSame(0, $this->summaryFor($employee, '2026-09-01', '2026-09-30')['unpaid_leave']);
    }

    public function test_editing_a_record_keeps_a_log_of_the_old_and_new_values(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->markAttendance($employee, '2026-09-10', 'present');

        $this->markAttendance($employee, '2026-09-10', 'absent');

        $this->assertSame(1, Attendance::query()->count());
        $log = AttendanceLog::query()->latest('id')->first();
        $this->assertSame('present', $log->old_values['status']);
        $this->assertSame('absent', $log->new_values['status']);
        $this->assertTrue(AuditLog::query()->where('action', 'attendance.modified')->where('employee_id', $employee->id)->exists());
    }

    public function test_generating_attendance_stores_days_off_and_never_overwrites_a_marked_day(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
        $employee = $this->createEmployee();
        $this->markAttendance($employee, '2026-09-07', 'absent');

        $created = $this->service()->generateForRange(CarbonImmutable::parse('2026-09-06'), CarbonImmutable::parse('2026-09-08'));

        $this->assertSame(2, $created);
        $statuses = Attendance::query()->orderBy('date')->get()->map(fn (Attendance $a): string => $a->status->value)->all();
        $this->assertSame(['weekly_off', 'absent', 'present'], $statuses);
    }

    /**
     * @return array<string, int|float>
     */
    private function summaryFor(Employee $employee, string $start, string $end): array
    {
        return $this->service()->summarize(
            $this->service()->resolveDays($employee, CarbonImmutable::parse($start), CarbonImmutable::parse($end)),
        );
    }

    private function service(): AttendanceCalculationService
    {
        return app(AttendanceCalculationService::class);
    }
}
