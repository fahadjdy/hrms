<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\EmployeeSalaryRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class SalaryEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_revising_a_salary_adds_a_revision_and_keeps_the_old_one(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee([], 30000);
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2026-07-01',
            'reason' => 'Annual Increment',
            'notes' => 'Approved by the director',
            'components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => 32000],
                ['name' => 'HRA', 'type' => 'earning', 'amount' => 3000],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $revisions = EmployeeSalaryRevision::query()->orderBy('effective_date')->get();
        $this->assertSame([30000.0, 35000.0], $revisions->pluck('new_gross')->all());
        $this->assertSame(30000.0, $revisions[1]->previous_gross);
        $this->assertSame('Annual Increment', $revisions[1]->reason);
        $this->assertSame($admin->id, $revisions[1]->created_by);
    }

    public function test_revision_cannot_take_effect_before_the_joining_date(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-03-01']);

        $response = $this->actingAs($this->adminOf($company))->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2026-02-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 30000]],
        ]);

        $response->assertSessionHasErrors(['effective_date' => 'The effective date cannot be before the joining date.']);
        $this->assertSame(0, EmployeeSalaryRevision::query()->count());
    }

    public function test_second_revision_on_the_same_date_is_rejected(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2025-01-01'], 30000);

        $response = $this->actingAs($this->adminOf($company))->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2025-01-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 40000]],
        ]);

        $response->assertSessionHasErrors([
            'effective_date' => 'A salary revision already exists for this date. Choose a different effective date.',
        ]);
        $this->assertSame(30000.0, EmployeeSalaryRevision::query()->sole()->new_gross);
    }

    public function test_salary_revision_requires_components(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2026-07-01',
            'components' => [],
        ]);

        $response->assertSessionHasErrors('components');
        $this->assertSame(0, EmployeeSalaryRevision::query()->count());
    }

    public function test_employee_salary_page_marks_the_current_and_upcoming_revisions(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01']);
        $this->setSalary($employee, 30000, '2026-01-01');
        $this->setSalary($employee, 35000, '2026-07-01', reason: 'Annual Increment');
        $this->setSalary($employee, 40000, '2026-11-01', reason: 'Promotion');

        $response = $this->actingAs($this->adminOf($company))->get("/employees/{$employee->id}/salary");

        // Today is 1 October 2026: July's revision is in effect, November's is upcoming.
        $response->assertInertia(fn (Assert $page) => $page
            ->where('current.new_gross', 35000)
            ->where('current.effective_date', '2026-07-01')
            ->has('revisions', 3)
            ->where('revisions.0.new_gross', 40000)
            ->where('revisions.0.previous_gross', 35000)
            ->where('revisions.0.is_upcoming', true)
            ->where('revisions.0.is_current', false)
            ->where('revisions.1.is_current', true)
            ->where('revisions.1.reason', 'Annual Increment')
            ->where('revisions.2.new_gross', 30000)
            ->where('revisions.2.previous_gross', 0)
            ->has('revisions.2.components', 1));
    }

    public function test_salary_structure_lists_each_employee_with_the_salary_in_effect(): void
    {
        $company = $this->prepareCompany();
        $withSalary = $this->createEmployee(['first_name' => 'Asha'], 30000);
        $this->setSalary($withSalary, 45000, '2026-12-01');
        $this->createEmployee(['first_name' => 'Bina']);

        $response = $this->actingAs($this->adminOf($company))->get('/salary-structure');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 2)
            ->where('employees.data.0.gross', 30000)
            ->where('employees.data.0.revisions_count', 2)
            ->where('employees.data.1.gross', null)
            ->where('employees.data.1.revisions_count', 0));
    }

    public function test_salary_history_lists_revisions_newest_first_with_the_change(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao', 'joining_date' => '2026-01-01']);
        $this->setSalary($employee, 30000, '2026-01-01');
        $this->setSalary($employee, 35000, '2026-07-01', reason: 'Annual Increment');

        $response = $this->actingAs($this->adminOf($company))->get('/salary-revisions');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('revisions.data', 2)
            ->where('revisions.data.0.effective_date', '2026-07-01')
            ->where('revisions.data.0.employee.name', 'Asha Rao')
            ->where('revisions.data.0.previous_gross', 30000)
            ->where('revisions.data.0.new_gross', 35000)
            ->where('revisions.data.0.change', 5000)
            ->where('revisions.data.0.reason', 'Annual Increment'));
    }

    public function test_viewer_role_can_read_salary_but_not_revise_it(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee([], 30000);
        $this->actingAs($this->userWithRole($company, 'viewer'));

        $this->get("/employees/{$employee->id}/salary")->assertOk();
        $this->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2026-07-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 99000]],
        ])->assertForbidden();

        $this->assertSame(1, EmployeeSalaryRevision::query()->count());
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');

        return $this->useCompany($this->createCompany());
    }
}
