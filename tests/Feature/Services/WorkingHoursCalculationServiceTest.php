<?php

namespace Tests\Feature\Services;

use App\Models\WorkShift;
use App\Services\EmployeeService;
use App\Services\WorkingHoursCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class WorkingHoursCalculationServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_employee_without_overrides_gets_the_company_default_shift(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['gender' => 'male']);

        $shift = $this->service()->shiftFor($employee, CarbonImmutable::parse('2026-09-10'));

        $this->assertSame('General Shift', $shift->name);
        $this->assertSame('company', $this->service()->shiftSourceFor($employee, CarbonImmutable::parse('2026-09-10')));
    }

    public function test_gender_based_shift_takes_priority_over_the_company_default(): void
    {
        $this->useCompany($this->createCompany());
        $femaleShift = WorkShift::factory()->timing('09:30', '17:30', 420)->create(['name' => 'Female Shift']);
        $this->configure(['female_work_shift_id' => $femaleShift->id]);
        $female = $this->createEmployee(['gender' => 'female']);
        $male = $this->createEmployee(['gender' => 'male']);

        $date = CarbonImmutable::parse('2026-09-10');

        $this->assertSame('Female Shift', $this->service()->shiftFor($female, $date)->name);
        $this->assertSame(420, $this->service()->requiredMinutesFor($female, $date));
        $this->assertSame('gender', $this->service()->shiftSourceFor($female, $date));
        $this->assertSame('General Shift', $this->service()->shiftFor($male, $date)->name);
    }

    public function test_employee_specific_shift_takes_priority_over_gender_and_company_defaults(): void
    {
        $this->useCompany($this->createCompany());
        $femaleShift = WorkShift::factory()->timing('09:30', '17:30', 420)->create(['name' => 'Female Shift']);
        $ownShift = WorkShift::factory()->timing('09:00', '17:00', 420)->create(['name' => 'Rahul Shift']);
        $this->configure(['female_work_shift_id' => $femaleShift->id]);
        $employee = $this->createEmployee(['gender' => 'female']);

        app(EmployeeService::class)->assignShift($employee, $ownShift->id, CarbonImmutable::parse('2026-09-01'));

        $date = CarbonImmutable::parse('2026-09-10');
        $this->assertSame('Rahul Shift', $this->service()->shiftFor($employee, $date)->name);
        $this->assertSame('employee', $this->service()->shiftSourceFor($employee, $date));
    }

    public function test_employee_specific_shift_only_applies_from_its_effective_date(): void
    {
        $this->useCompany($this->createCompany());
        $ownShift = WorkShift::factory()->timing('10:00', '15:00', 300)->create(['name' => 'Short Shift']);
        $employee = $this->createEmployee();

        app(EmployeeService::class)->assignShift($employee, $ownShift->id, CarbonImmutable::parse('2026-09-15'));

        $this->assertSame('General Shift', $this->service()->shiftFor($employee, CarbonImmutable::parse('2026-09-14'))->name);
        $this->assertSame('Short Shift', $this->service()->shiftFor($employee, CarbonImmutable::parse('2026-09-15'))->name);
    }

    public function test_calculates_worked_and_short_minutes_after_deducting_the_break(): void
    {
        $shift = WorkShift::factory()->make();

        // 09:32 to 17:41 is 8h 09m at work; less the 1h break that is 7h 09m worked.
        $result = $this->service()->calculate($shift, '09:32', '17:41', graceMinutes: 10);

        $this->assertSame(480, $result['required_minutes']);
        $this->assertSame(429, $result['worked_minutes']);
        $this->assertSame(51, $result['short_minutes']);
        $this->assertSame(0, $result['overtime_minutes']);
        $this->assertSame(32, $result['late_minutes']);
    }

    public function test_calculates_overtime_when_more_than_the_required_hours_are_worked(): void
    {
        $shift = WorkShift::factory()->make();

        $result = $this->service()->calculate($shift, '09:00', '20:15');

        $this->assertSame(615, $result['worked_minutes']);
        $this->assertSame(135, $result['overtime_minutes']);
        $this->assertSame(0, $result['short_minutes']);
    }

    /**
     * @return array<string, array{string, string, string, string, string, string, int}>
     */
    public static function fixedBreakDays(): array
    {
        return [
            'full day loses the whole break' => ['09:00', '18:00', '13:00', '14:00', '09:00', '18:00', 480],
            'leaving before the break loses none of it' => ['09:00', '18:00', '13:00', '14:00', '09:00', '13:00', 240],
            'arriving during the break loses only the rest of it' => ['09:00', '18:00', '13:00', '14:00', '13:30', '18:00', 240],
            'overnight shift and break' => ['22:00', '07:00', '02:00', '03:00', '22:00', '07:00', 480],
        ];
    }

    #[DataProvider('fixedBreakDays')]
    public function test_fixed_break_is_deducted_only_for_the_time_spent_at_work(
        string $shiftStart,
        string $shiftEnd,
        string $breakStart,
        string $breakEnd,
        string $checkIn,
        string $checkOut,
        int $expectedWorked,
    ): void {
        $shift = WorkShift::factory()->timing($shiftStart, $shiftEnd, 480)->breakBetween($breakStart, $breakEnd)->make();

        $result = $this->service()->calculate($shift, $checkIn, $checkOut);

        $this->assertSame($expectedWorked, $result['worked_minutes']);
    }

    public function test_arrival_within_the_grace_period_is_not_late(): void
    {
        $shift = WorkShift::factory()->make();

        $result = $this->service()->calculate($shift, '09:10', '18:10', graceMinutes: 10);

        $this->assertSame(0, $result['late_minutes']);
    }

    public function test_short_minutes_within_the_tolerance_are_ignored(): void
    {
        $shift = WorkShift::factory()->make();

        $result = $this->service()->calculate($shift, '09:00', '17:50', toleranceMinutes: 15);

        $this->assertSame(470, $result['worked_minutes']);
        $this->assertSame(0, $result['short_minutes']);
    }

    private function service(): WorkingHoursCalculationService
    {
        return app(WorkingHoursCalculationService::class);
    }
}
