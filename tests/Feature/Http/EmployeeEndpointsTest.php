<?php

namespace Tests\Feature\Http;

use App\Enums\EmployeeStatus;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\EmployeeSalaryRevision;
use App\Models\WorkShift;
use App\Services\BorrowCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class EmployeeEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_guest_is_redirected_to_the_login_page(): void
    {
        $response = $this->get('/employees');

        $response->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function manageOnlyEndpoints(): array
    {
        return [
            'create form' => ['get', '/employees/create'],
            'store' => ['post', '/employees'],
            'edit form' => ['get', '/employees/{id}/edit'],
            'update' => ['put', '/employees/{id}'],
            'exit' => ['post', '/employees/{id}/exit'],
            'reinstate' => ['delete', '/employees/{id}/exit'],
            'shift' => ['put', '/employees/{id}/shift'],
            'company settings' => ['get', '/settings/company'],
        ];
    }

    #[DataProvider('manageOnlyEndpoints')]
    public function test_viewer_role_is_forbidden_from_changing_employees_and_settings(string $method, string $url): void
    {
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['first_name' => 'Asha']);

        $response = $this->actingAs($this->userWithRole($company, 'viewer'))
            ->{$method}(str_replace('{id}', (string) $employee->id, $url), ['first_name' => 'Changed']);

        $response->assertForbidden();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'first_name' => 'Asha', 'status' => 'active']);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_viewer_role_can_see_the_employee_list_and_profile(): void
    {
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao']);
        $this->actingAs($this->userWithRole($company, 'viewer'));

        $this->get('/employees')->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Asha Rao'));
        $this->get("/employees/{$employee->id}")->assertInertia(fn (Assert $page) => $page
            ->where('employee.name', 'Asha Rao')
            ->where('employee.is_past', false));
    }

    public function test_storing_an_employee_requires_the_core_fields(): void
    {
        $company = $this->createCompany();

        $response = $this->actingAs($this->adminOf($company))->post('/employees', []);

        $response->assertSessionHasErrors([
            'employee_code' => 'The employee ID field is required.',
            'first_name' => 'The first name field is required.',
            'joining_date' => 'The joining date field is required.',
            'employment_type' => 'The employment type field is required.',
            'status' => 'The status field is required.',
        ]);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_employee_id_must_be_unique_within_the_company(): void
    {
        $company = $this->useCompany($this->createCompany());
        $this->createEmployee(['employee_code' => 'EMP-0001']);

        $response = $this->actingAs($this->adminOf($company))->post('/employees', $this->validPayload(['employee_code' => 'EMP-0001']));

        $response->assertSessionHasErrors(['employee_code' => 'The employee ID has already been taken.']);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_past_cannot_be_chosen_as_the_status_of_a_new_employee(): void
    {
        $company = $this->createCompany();

        $response = $this->actingAs($this->adminOf($company))->post('/employees', $this->validPayload(['status' => 'past']));

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_storing_an_employee_saves_the_salary_and_the_existing_borrow(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->post('/employees', $this->validPayload([
            'salary_components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => 35000],
                ['name' => 'HRA', 'type' => 'earning', 'amount' => 5000],
                ['name' => 'Professional Tax', 'type' => 'deduction', 'amount' => 200],
            ],
            'existing_borrow' => [
                'amount' => 20000,
                'opening_balance' => 15000,
                'borrow_date' => '2026-06-15',
                'monthly_deduction' => 5000,
                'deduction_start_month' => '2026-10-01',
                'reason' => 'Advance from previous employer',
            ],
        ]));

        $employee = Employee::query()->sole();
        $response->assertRedirect(route('employees.show', $employee));
        $this->assertSame('Rahul Sharma', $employee->full_name);
        $this->assertSame($company->id, $employee->company_id);

        $revision = EmployeeSalaryRevision::query()->sole();
        $this->assertSame(40000.0, $revision->new_gross);
        $this->assertSame('2026-09-01', $revision->effective_date->toDateString());

        $borrow = EmployeeBorrow::query()->sole();
        $this->assertSame(EmployeeBorrow::KIND_EXISTING, $borrow->kind);
        $this->assertSame(20000.0, $borrow->amount);
        $this->assertSame(15000.0, $borrow->outstanding_amount);
        $this->assertSame('2026-10-01', $borrow->deduction_start_month->toDateString());
    }

    public function test_employee_can_be_added_without_a_salary_when_the_component_rows_are_left_at_zero(): void
    {
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->post('/employees', $this->validPayload([
            'salary_components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => 0],
                ['name' => 'HRA', 'type' => 'earning', 'amount' => 0],
            ],
            'existing_borrow' => ['amount' => null, 'opening_balance' => null, 'monthly_deduction' => null],
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, Employee::query()->count());
        $this->assertSame(0, EmployeeSalaryRevision::query()->count());
        $this->assertSame(0, EmployeeBorrow::query()->count());
    }

    public function test_salary_made_only_of_deductions_is_reported_on_the_salary_field(): void
    {
        $company = $this->useCompany($this->createCompany());

        $response = $this->actingAs($this->adminOf($company))->post('/employees', $this->validPayload([
            'salary_components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => 0],
                ['name' => 'Professional Tax', 'type' => 'deduction', 'amount' => 200],
            ],
        ]));

        $response->assertSessionHasErrors([
            'salary_components' => 'Add at least one earning with an amount greater than zero.',
        ]);
        $this->assertSame(0, Employee::query()->count());
    }

    public function test_existing_borrow_balance_cannot_be_more_than_the_borrowed_amount(): void
    {
        $company = $this->createCompany();

        $response = $this->actingAs($this->adminOf($company))->post('/employees', $this->validPayload([
            'existing_borrow' => ['amount' => 10000, 'opening_balance' => 12000, 'monthly_deduction' => 1000],
        ]));

        $response->assertSessionHasErrors('existing_borrow.opening_balance');
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('employee_borrows', 0);
    }

    public function test_updating_an_employee_changes_the_profile_and_is_audited(): void
    {
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['employee_code' => 'EMP-0001', 'first_name' => 'Rahul']);

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}", $this->validPayload([
            'employee_code' => 'EMP-0001',
            'first_name' => 'Rohit',
            'status' => 'notice',
        ]));

        $response->assertRedirect(route('employees.show', $employee));
        $employee->refresh();
        $this->assertSame('Rohit', $employee->first_name);
        $this->assertSame(EmployeeStatus::NoticePeriod, $employee->status);
        $audit = AuditLog::query()->where('action', 'employee.updated')->sole();
        $this->assertSame('Rahul', $audit->old_values['first_name']);
        $this->assertSame('Rohit', $audit->new_values['first_name']);
    }

    public function test_an_employee_cannot_report_to_themselves(): void
    {
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['employee_code' => 'EMP-0001']);

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}", $this->validPayload([
            'employee_code' => 'EMP-0001',
            'reporting_manager_id' => $employee->id,
        ]));

        $response->assertSessionHasErrors(['reporting_manager_id' => 'An employee cannot report to themselves.']);
    }

    public function test_employee_specific_shift_can_be_set_and_removed_through_the_endpoint(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $own = WorkShift::factory()->timing('09:00', '17:00', 420)->create(['name' => 'Rahul Shift']);
        $this->actingAs($this->adminOf($company));

        $this->put("/employees/{$employee->id}/shift", ['work_shift_id' => $own->id, 'effective_from' => '2026-09-01'])
            ->assertSessionHasNoErrors();
        $this->get("/employees/{$employee->id}")->assertInertia(fn (Assert $page) => $page
            ->where('shift.name', 'Rahul Shift')
            ->where('shift.source', 'employee')
            ->where('shift.required_minutes', 420));

        $this->put("/employees/{$employee->id}/shift", ['work_shift_id' => null, 'effective_from' => '2026-09-20'])
            ->assertSessionHasNoErrors();
        $this->get("/employees/{$employee->id}")->assertInertia(fn (Assert $page) => $page
            ->where('shift.name', 'General Shift')
            ->where('shift.source', 'company'));
        $this->assertSame(2, AuditLog::query()->where('action', 'employee.shift_changed')->count());
    }

    public function test_employee_profile_shows_salary_borrow_totals_and_this_periods_attendance(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['first_name' => 'Rahul', 'last_name' => 'Sharma'], 26000);
        $borrows = app(BorrowCalculationService::class);
        $first = $borrows->create($employee, ['amount' => 20000, 'borrow_date' => '2026-06-01', 'monthly_deduction' => 5000]);
        $borrows->recover($first, 15000, CarbonImmutable::parse('2026-09-20'));
        $borrows->create($employee, ['amount' => 15000, 'borrow_date' => '2026-09-15', 'monthly_deduction' => 3000]);
        $borrows->create($employee, [
            'amount' => 9000, 'borrow_date' => '2026-09-28', 'monthly_deduction' => 3000,
            'disbursement_method' => 'with_salary', 'disburse_period' => '2026-10-01',
        ]);
        $this->markAttendance($employee, '2026-10-01', 'present');

        $response = $this->actingAs($this->adminOf($company))->get("/employees/{$employee->id}");

        // A borrow still waiting to be paid out with salary is listed but not in the totals.
        $response->assertInertia(fn (Assert $page) => $page
            ->where('employee.name', 'Rahul Sharma')
            ->where('employee.status', 'active')
            ->where('salary.gross', 26000)
            ->has('salary.components', 1)
            ->where('borrows.total_borrowed', 35000)
            ->where('borrows.total_recovered', 15000)
            ->where('borrows.total_outstanding', 20000)
            ->has('borrows.items', 3)
            ->where('attendance.period.label', 'October 2026')
            ->where('attendance.summary.present', 1)
            ->has('leaveBalances', 4)
            ->has('activity'));
    }

    public function test_exit_endpoint_makes_the_employee_a_past_employee(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->actingAs($this->adminOf($company));

        $response = $this->post("/employees/{$employee->id}/exit", [
            'exit_date' => '2026-09-30',
            'last_working_date' => '2026-09-30',
            'exit_type' => 'resignation',
            'exit_reason' => 'Relocation',
        ]);

        $response->assertRedirect(route('employees.show', $employee));
        $this->assertSame(EmployeeStatus::Past, $employee->refresh()->status);
        $this->get('/employees')->assertInertia(fn (Assert $page) => $page->has('employees.data', 0));
        $this->get('/employees/past')->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.exit_reason', 'Relocation')
            ->where('employees.data.0.last_working_date', '2026-09-30'));
    }

    public function test_exit_is_rejected_for_an_employee_who_already_left(): void
    {
        $company = $this->useCompany($this->createCompany());
        $employee = Employee::factory()->past('2026-08-31')->create();

        $response = $this->actingAs($this->adminOf($company))->post("/employees/{$employee->id}/exit", [
            'exit_date' => '2026-09-30', 'last_working_date' => '2026-09-30', 'exit_type' => 'resignation',
        ]);

        $response->assertSessionHasErrors(['exit_date' => 'This employee has already left the company.']);
        $this->assertSame('2026-08-31', $employee->refresh()->last_working_date->toDateString());
    }

    public function test_last_working_date_cannot_be_before_the_joining_date(): void
    {
        $company = $this->useCompany($this->createCompany());
        $employee = $this->createEmployee(['joining_date' => '2026-09-01']);

        $response = $this->actingAs($this->adminOf($company))->post("/employees/{$employee->id}/exit", [
            'exit_date' => '2026-09-30', 'last_working_date' => '2026-08-15', 'exit_type' => 'resignation',
        ]);

        $response->assertSessionHasErrors('last_working_date');
        $this->assertSame(EmployeeStatus::Active, $employee->refresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'employee_code' => 'EMP-0100',
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'joining_date' => '2026-09-01',
            'employment_type' => 'full_time',
            'status' => 'active',
            ...$overrides,
        ];
    }
}
