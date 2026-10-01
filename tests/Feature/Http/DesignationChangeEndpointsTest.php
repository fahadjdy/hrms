<?php

namespace Tests\Feature\Http;

use App\Enums\DesignationChangeType;
use App\Models\Company;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDesignationChange;
use App\Services\DesignationChangeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class DesignationChangeEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    private Company $company;

    private Designation $junior;

    private Designation $senior;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        $this->company = $this->useCompany($this->createCompany());
        $this->junior = Designation::factory()->create(['name' => 'Junior Developer']);
        $this->senior = Designation::factory()->create(['name' => 'Senior Developer']);
    }

    public function test_recording_a_promotion_updates_the_employee_and_the_history(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);
        $admin = $this->adminOf($this->company);

        $response = $this->actingAs($admin)->from("/employees/{$employee->id}")->post("/employees/{$employee->id}/designation-changes", [
            'designation_id' => $this->senior->id,
            'type' => 'promotion',
            'effective_date' => '2026-09-15',
            'reason' => 'Annual review',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect("/employees/{$employee->id}");
        $this->assertSame($this->senior->id, $employee->refresh()->designation_id);
        $change = EmployeeDesignationChange::query()->where('employee_id', $employee->id)->latest('id')->first();
        $this->assertSame(DesignationChangeType::Promotion, $change->type);
        $this->assertSame('2026-09-15', $change->effective_date->toDateString());
        $this->assertSame($admin->id, $change->changed_by);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'no designation' => [['designation_id' => null], 'designation_id'],
            'unknown designation' => [['designation_id' => 999999], 'designation_id'],
            'the initial type cannot be picked' => [['type' => 'initial'], 'type'],
            'unknown type' => [['type' => 'sideways'], 'type'],
            'future date' => [['effective_date' => '2026-10-02'], 'effective_date'],
            'malformed date' => [['effective_date' => '15/09/2026'], 'effective_date'],
            'reason too long' => [['reason' => str_repeat('x', 256)], 'reason'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_change_is_rejected_and_nothing_is_recorded(array $overrides, string $field): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        $response = $this->actingAs($this->adminOf($this->company))
            ->post("/employees/{$employee->id}/designation-changes", [...$this->payload(), ...$overrides]);

        $response->assertSessionHasErrors($field);
        $this->assertSame($this->junior->id, $employee->refresh()->designation_id);
        $this->assertSame(1, EmployeeDesignationChange::query()->where('employee_id', $employee->id)->count());
    }

    public function test_changing_to_the_current_designation_is_rejected(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);

        $response = $this->actingAs($this->adminOf($this->company))
            ->post("/employees/{$employee->id}/designation-changes", [...$this->payload(), 'designation_id' => $this->junior->id]);

        $response->assertSessionHasErrors('designation_id');
        $this->assertSame(1, EmployeeDesignationChange::query()->where('employee_id', $employee->id)->count());
    }

    public function test_a_past_employee_cannot_be_given_a_new_designation(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);
        $employee->forceFill(['status' => 'past', 'exit_date' => '2026-08-31', 'last_working_date' => '2026-08-31'])->save();

        $response = $this->actingAs($this->adminOf($this->company))
            ->post("/employees/{$employee->id}/designation-changes", $this->payload());

        $response->assertSessionHasErrors('employee');
        $this->assertSame($this->junior->id, $employee->refresh()->designation_id);
    }

    public function test_viewer_role_can_see_the_history_but_not_record_a_change(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);
        $this->actingAs($this->userWithRole($this->company, 'viewer'));

        $this->get('/designation-changes')->assertOk();
        $this->get("/employees/{$employee->id}")->assertOk();
        $this->post("/employees/{$employee->id}/designation-changes", $this->payload())->assertForbidden();

        $this->assertSame($this->junior->id, $employee->refresh()->designation_id);
        $this->assertSame(1, EmployeeDesignationChange::query()->where('employee_id', $employee->id)->count());
    }

    public function test_employee_profile_shows_the_history_newest_first_with_the_choices_for_a_change(): void
    {
        $employee = $this->employeeJoinedAs($this->junior);
        app(DesignationChangeService::class)->change($employee, $this->senior, CarbonImmutable::parse('2026-06-01'), DesignationChangeType::Promotion, 'Annual review');
        Designation::factory()->create(['name' => 'Retired Title', 'is_active' => false]);

        $response = $this->actingAs($this->adminOf($this->company))->get("/employees/{$employee->id}");

        $response->assertInertia(fn (Assert $page) => $page
            ->component('employees/Show')
            ->where('employee.designation', 'Senior Developer')
            ->where('employee.designation_id', $this->senior->id)
            ->has('designationHistory', 2)
            ->where('designationHistory.0.from', 'Junior Developer')
            ->where('designationHistory.0.to', 'Senior Developer')
            ->where('designationHistory.0.type_label', 'Promotion')
            ->where('designationHistory.0.reason', 'Annual review')
            ->where('designationHistory.1.type', 'initial')
            ->where('designationHistory.1.to', 'Junior Developer')
            ->has('designations', 2)
            ->where('designationChangeTypes', DesignationChangeType::recordableOptions()));
    }

    public function test_company_history_page_lists_every_change_and_filters_by_type_employee_and_date(): void
    {
        $rahul = $this->employeeJoinedAs($this->junior, ['first_name' => 'Rahul', 'last_name' => 'Sharma']);
        $asha = $this->employeeJoinedAs($this->junior, ['first_name' => 'Asha', 'last_name' => 'Rao']);
        $changes = app(DesignationChangeService::class);
        $changes->change($rahul, $this->senior, CarbonImmutable::parse('2026-06-01'), DesignationChangeType::Promotion);
        $changes->change($asha, $this->senior, CarbonImmutable::parse('2026-08-01'), DesignationChangeType::Change);
        $this->actingAs($this->adminOf($this->company));

        $this->get('/designation-changes')->assertInertia(fn (Assert $page) => $page
            ->component('employees/DesignationHistory')
            ->has('changes.data', 4)
            ->where('changes.data.0.employee.name', 'Asha Rao')
            ->where('changes.data.0.effective_date', '2026-08-01')
            ->where('changes.data.0.to', 'Senior Developer'));

        $this->get('/designation-changes?type=promotion')->assertInertia(fn (Assert $page) => $page
            ->has('changes.data', 1)
            ->where('changes.data.0.employee.name', 'Rahul Sharma'));

        $this->get('/designation-changes?search=asha')->assertInertia(fn (Assert $page) => $page
            ->has('changes.data', 2));

        $this->get('/designation-changes?from=2026-07-01&to=2026-12-31')->assertInertia(fn (Assert $page) => $page
            ->has('changes.data', 1)
            ->where('changes.data.0.type', 'change'));
    }

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $this->get('/designation-changes')->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function employeeJoinedAs(Designation $designation, array $attributes = []): Employee
    {
        $employee = $this->createEmployee([...$attributes, 'designation_id' => $designation->id]);
        app(DesignationChangeService::class)->recordInitial($employee);

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'designation_id' => $this->senior->id,
            'type' => 'promotion',
            'effective_date' => '2026-09-15',
            'reason' => 'Annual review',
        ];
    }
}
