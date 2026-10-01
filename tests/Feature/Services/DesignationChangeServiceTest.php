<?php

namespace Tests\Feature\Services;

use App\Enums\DesignationChangeType;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDesignationChange;
use App\Services\DesignationChangeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class DesignationChangeServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    private Designation $junior;

    private Designation $senior;

    private Designation $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->junior = Designation::factory()->create(['name' => 'Junior Developer']);
        $this->senior = Designation::factory()->create(['name' => 'Senior Developer']);
        $this->manager = Designation::factory()->create(['name' => 'Engineering Manager']);
    }

    public function test_promotion_updates_the_employee_and_adds_to_the_history(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        $change = $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-07-01'), DesignationChangeType::Promotion, 'Annual review', $this->adminOf($this->tenant()->require()));

        $this->assertSame($this->senior->id, $employee->refresh()->designation_id);
        $this->assertSame($this->junior->id, $change->from_designation_id);
        $this->assertSame($this->senior->id, $change->to_designation_id);
        $this->assertSame(DesignationChangeType::Promotion, $change->type);
        $this->assertSame('2026-07-01', $change->effective_date->toDateString());
        $this->assertSame('Annual review', $change->reason);
        $this->assertSame(2, EmployeeDesignationChange::query()->where('employee_id', $employee->id)->count());
    }

    public function test_history_is_newest_first_and_keeps_every_step(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);
        $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-03-01'), DesignationChangeType::Promotion);
        $this->service()->change($employee, $this->manager, CarbonImmutable::parse('2026-09-15'), DesignationChangeType::Promotion, 'Took over the team');

        $history = $this->service()->history($employee->refresh());

        $this->assertSame(
            [
                ['Senior Developer', 'Engineering Manager', 'promotion', '2026-09-15'],
                ['Junior Developer', 'Senior Developer', 'promotion', '2026-03-01'],
                [null, 'Junior Developer', 'initial', '2025-01-01'],
            ],
            array_map(fn (array $row): array => [$row['from'], $row['to'], $row['type'], $row['effective_date']], $history),
        );
    }

    public function test_the_first_designation_of_an_employee_without_one_is_recorded_as_joining(): void
    {
        $employee = $this->createEmployee(['designation_id' => null]);

        $change = $this->service()->change($employee, $this->junior, CarbonImmutable::parse('2026-09-01'), DesignationChangeType::Change);

        $this->assertSame(DesignationChangeType::Initial, $change->type);
        $this->assertNull($change->from_designation_id);
        $this->assertSame($this->junior->id, $employee->refresh()->designation_id);
    }

    public function test_the_same_designation_is_refused(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        $this->expectValidationError('designation_id', fn () => $this->service()->change($employee, $this->junior, CarbonImmutable::parse('2026-09-01'), DesignationChangeType::Promotion));
    }

    public function test_a_past_employee_cannot_change_designation(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);
        $employee->forceFill(['status' => 'past', 'exit_date' => '2026-08-31', 'last_working_date' => '2026-08-31'])->save();

        $this->expectValidationError('employee', fn () => $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-08-01'), DesignationChangeType::Promotion));
    }

    public function test_effective_date_cannot_be_before_the_joining_date(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        $this->expectValidationError('effective_date', fn () => $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2024-12-31'), DesignationChangeType::Promotion));
    }

    public function test_effective_date_cannot_be_before_the_last_change(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);
        $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-06-01'), DesignationChangeType::Promotion);

        $this->expectValidationError('effective_date', fn () => $this->service()->change($employee->refresh(), $this->manager, CarbonImmutable::parse('2026-05-31'), DesignationChangeType::Promotion));
    }

    public function test_effective_date_cannot_be_in_the_future(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        $this->expectValidationError('effective_date', fn () => $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-10-02'), DesignationChangeType::Promotion));
    }

    public function test_someone_who_has_not_joined_yet_can_be_corrected_up_to_their_joining_date(): void
    {
        $employee = $this->employeeJoinedAs($this->junior, '2026-11-01');

        $change = $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-11-01'), DesignationChangeType::Change);

        $this->assertSame('2026-11-01', $change->effective_date->toDateString());
        $this->assertSame($this->senior->id, $employee->refresh()->designation_id);
    }

    public function test_a_refused_change_leaves_nothing_behind(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        try {
            $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-10-02'), DesignationChangeType::Promotion);
        } catch (ValidationException) {
        }

        $this->assertSame($this->junior->id, $employee->refresh()->designation_id);
        $this->assertSame(1, EmployeeDesignationChange::query()->where('employee_id', $employee->id)->count());
    }

    public function test_a_change_is_audited_with_the_old_and_new_designation(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        $this->service()->change($employee, $this->senior, CarbonImmutable::parse('2026-07-01'), DesignationChangeType::Promotion, 'Annual review');

        $log = AuditLog::query()->where('action', 'employee.designation_changed')->where('employee_id', $employee->id)->sole();
        $this->assertSame('Junior Developer', $log->old_values['designation']);
        $this->assertSame('Senior Developer', $log->new_values['designation']);
        $this->assertSame('2026-07-01', $log->new_values['effective_date']);
        $this->assertStringContainsString('Promotion from Junior Developer to Senior Developer', (string) $log->description);
    }

    public function test_recording_the_initial_designation_happens_once(): void
    {
        $employee = $this->createEmployee(['designation_id' => $this->junior->id]);

        $first = $this->service()->recordInitial($employee);
        $second = $this->service()->recordInitial($employee);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(DesignationChangeType::Initial, $first->type);
        $this->assertSame('2025-01-01', $first->effective_date->toDateString());
        $this->assertNull($this->service()->recordInitial($this->createEmployee(['designation_id' => null])));
    }

    private function employeeJoinedAs(Designation $designation, string $joiningDate = '2025-01-01'): Employee
    {
        $employee = $this->createEmployee(['designation_id' => $designation->id, 'joining_date' => $joiningDate]);
        $this->service()->recordInitial($employee);

        return $employee;
    }

    private function expectValidationError(string $field, callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return;
        }

        $this->fail("Expected a validation error on {$field}.");
    }

    private function service(): DesignationChangeService
    {
        return app(DesignationChangeService::class);
    }
}
