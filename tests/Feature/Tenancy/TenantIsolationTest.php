<?php

namespace Tests\Feature\Tenancy;

use App\Jobs\GenerateSalarySlips;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Overtime;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\BorrowCalculationService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * Company A must never reach Company B's data, whichever way the request is
 * shaped. Cross-tenant ids answer 404 so that nothing reveals they exist.
 */
class TenantIsolationTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function employeeUrls(): array
    {
        return [
            'profile' => ['get', '/employees/{id}'],
            'edit form' => ['get', '/employees/{id}/edit'],
            'update' => ['put', '/employees/{id}'],
            'exit' => ['post', '/employees/{id}/exit'],
            'shift' => ['put', '/employees/{id}/shift'],
            'designation change' => ['post', '/employees/{id}/designation-changes'],
            'attendance calendar' => ['get', '/employees/{id}/attendance'],
            'attendance day' => ['put', '/employees/{id}/attendance/2026-09-10'],
            'salary' => ['get', '/employees/{id}/salary'],
            'salary revision' => ['post', '/employees/{id}/salary'],
            'documents' => ['post', '/employees/{id}/documents'],
            'short hours' => ['put', '/short-hours/{id}'],
            'final settlement' => ['get', '/final-settlements/{id}'],
        ];
    }

    #[DataProvider('employeeUrls')]
    public function test_another_companys_employee_is_not_found(string $method, string $url): void
    {
        $this->createTwoCompanies();

        $response = $this->actingAs($this->adminOf($this->companyA))
            ->{$method}(str_replace('{id}', (string) $this->employeeB->id, $url), ['first_name' => 'Hacked', 'status' => 'absent']);

        $response->assertNotFound();
        $this->assertDatabaseHas('employees', ['id' => $this->employeeB->id, 'first_name' => 'Bina']);
        $this->assertDatabaseMissing('attendances', ['employee_id' => $this->employeeB->id]);
    }

    public function test_another_companys_payroll_borrow_slip_and_document_are_not_found(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        Storage::fake('local');
        $this->createTwoCompanies();

        $this->useCompany($this->companyB);
        $borrow = app(BorrowCalculationService::class)->create($this->employeeB, [
            'amount' => 5000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-12-01',
        ]);
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->calculate($payrolls->create(2026, 9));
        $payrolls->finalize($payroll);
        $item = PayrollItem::query()->sole();
        $slip = SalarySlip::query()->sole();
        $document = EmployeeDocument::query()->create([
            'employee_id' => $this->employeeB->id, 'title' => 'Contract', 'file_path' => 'x/contract.pdf', 'original_name' => 'contract.pdf',
        ]);

        $this->actingAs($this->adminOf($this->companyA));

        $this->get("/payroll/{$payroll->id}")->assertNotFound();
        $this->get("/payroll/{$payroll->id}/items/{$item->id}")->assertNotFound();
        $this->post("/payroll/{$payroll->id}/reopen", ['reason' => 'Trying to reopen'])->assertNotFound();
        $this->get("/borrows/{$borrow->id}")->assertNotFound();
        $this->post("/borrows/{$borrow->id}/recoveries", ['amount' => 100, 'date' => '2026-09-30'])->assertNotFound();
        $this->get("/salary-slips/{$slip->id}")->assertNotFound();
        $this->get("/documents/{$document->id}")->assertNotFound();

        $this->assertSame('finalized', Payroll::withoutTenancy()->find($payroll->id)->status->value);
        $this->assertDatabaseHas('employee_borrows', ['id' => $borrow->id, 'outstanding_amount' => 5000]);
    }

    public function test_payroll_item_of_the_same_company_is_not_found_under_another_payroll(): void
    {
        $this->createTwoCompanies();
        $this->useCompany($this->companyA);
        $this->createEmployee([], 26000);
        $payrolls = app(PayrollService::class);
        $september = $payrolls->calculate($payrolls->create(2026, 9));
        $august = $payrolls->calculate($payrolls->create(2026, 8));
        $septemberItem = PayrollItem::query()->where('payroll_id', $september->id)->sole();

        $response = $this->actingAs($this->adminOf($this->companyA))
            ->get("/payroll/{$august->id}/items/{$septemberItem->id}");

        $response->assertNotFound();
    }

    public function test_lists_only_show_the_signed_in_companys_employees(): void
    {
        $this->createTwoCompanies();
        $this->useCompany($this->companyA);
        $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao']);

        $response = $this->actingAs($this->adminOf($this->companyA))->get('/employees');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Asha Rao'));
    }

    public function test_filtering_by_another_companys_department_reveals_nothing(): void
    {
        $this->createTwoCompanies();
        $departmentB = Department::withoutTenancy()->where('company_id', $this->companyB->id)->sole();

        $response = $this->actingAs($this->adminOf($this->companyA))
            ->get('/employees?department_id='.$departmentB->id.'&search=Bina');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 0)
            ->has('departments', 0));
    }

    public function test_another_companys_employee_id_in_the_request_body_is_rejected(): void
    {
        $this->createTwoCompanies();
        $this->actingAs($this->adminOf($this->companyA));

        $this->post('/overtime', [
            'employee_id' => $this->employeeB->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed',
            'amount' => 1500, 'status' => 'approved',
        ])->assertSessionHasErrors(['employee_id' => 'The selected employee is invalid.']);

        $this->post('/attendance/bulk', [
            'date' => '2026-09-10', 'employee_ids' => [$this->employeeB->id], 'status' => 'absent',
        ])->assertSessionHasErrors('employee_ids.0');

        $this->post('/borrows', [
            'employee_id' => $this->employeeB->id, 'kind' => 'new', 'amount' => 5000, 'borrow_date' => '2026-09-10',
            'monthly_deduction' => 1000, 'deduction_start_month' => '2026-10', 'disbursement_method' => 'direct',
        ])->assertSessionHasErrors('employee_id');

        $this->assertSame(0, Overtime::withoutTenancy()->count());
        $this->assertSame(0, Attendance::withoutTenancy()->count());
        $this->assertDatabaseCount('employee_borrows', 0);
    }

    public function test_another_companys_designation_cannot_be_given_to_an_employee(): void
    {
        $this->createTwoCompanies();
        $this->useCompany($this->companyB);
        $designationB = Designation::factory()->create(['name' => 'Secret Lead']);
        $this->useCompany($this->companyA);
        $employee = $this->createEmployee(['first_name' => 'Asha']);

        $response = $this->actingAs($this->adminOf($this->companyA))->post("/employees/{$employee->id}/designation-changes", [
            'designation_id' => $designationB->id, 'type' => 'promotion', 'effective_date' => '2026-09-01',
        ]);

        $response->assertSessionHasErrors('designation_id');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'designation_id' => null]);
        $this->assertDatabaseCount('employee_designation_changes', 0);
    }

    public function test_another_companys_department_cannot_be_assigned_to_an_employee(): void
    {
        $this->createTwoCompanies();
        $departmentB = Department::withoutTenancy()->where('company_id', $this->companyB->id)->sole();

        $response = $this->actingAs($this->adminOf($this->companyA))->post('/employees', [
            'employee_code' => 'EMP-0100', 'first_name' => 'Asha', 'joining_date' => '2026-09-01',
            'employment_type' => 'full_time', 'status' => 'active', 'department_id' => $departmentB->id,
        ]);

        $response->assertSessionHasErrors('department_id');
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-0100']);
    }

    public function test_company_id_in_the_request_body_is_ignored(): void
    {
        $this->createTwoCompanies();

        $response = $this->actingAs($this->adminOf($this->companyA))->post('/employees', [
            'company_id' => $this->companyB->id,
            'employee_code' => 'EMP-0100', 'first_name' => 'Asha', 'joining_date' => '2026-09-01',
            'employment_type' => 'full_time', 'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employees', ['employee_code' => 'EMP-0100', 'company_id' => $this->companyA->id]);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-0100', 'company_id' => $this->companyB->id]);
    }

    public function test_the_same_employee_id_can_be_used_by_two_companies(): void
    {
        $this->createTwoCompanies();
        $this->useCompany($this->companyA);

        $employee = Employee::factory()->create(['employee_code' => $this->employeeB->employee_code]);

        $this->assertSame($this->companyA->id, $employee->company_id);
    }

    public function test_user_of_another_company_cannot_be_updated(): void
    {
        $this->createTwoCompanies();
        $userB = $this->adminOf($this->companyB);

        $response = $this->actingAs($this->adminOf($this->companyA))->put("/settings/users/{$userB->id}", [
            'name' => 'Hacked', 'role_id' => $userB->role_id, 'is_active' => false,
        ]);

        $response->assertNotFound();
        $this->assertTrue($userB->refresh()->is_active);
    }

    public function test_queries_return_nothing_when_no_company_is_set(): void
    {
        $this->createTwoCompanies();
        $this->tenant()->forget();

        $this->assertSame(0, Employee::query()->count());
        $this->assertNull(Employee::query()->find($this->employeeB->id));
    }

    public function test_a_record_cannot_be_moved_to_another_company(): void
    {
        $this->createTwoCompanies();
        $this->useCompany($this->companyB);

        $this->expectException(LogicException::class);

        $this->employeeB->forceFill(['company_id' => $this->companyA->id])->save();
    }

    public function test_super_admin_is_sent_to_the_platform_area_instead_of_company_pages(): void
    {
        $this->createTwoCompanies();

        $response = $this->actingAs(User::factory()->superAdmin()->create())->get('/employees');

        $response->assertRedirect(route('admin.companies.index'));
    }

    public function test_company_user_cannot_see_the_platform_area(): void
    {
        $this->createTwoCompanies();

        $response = $this->actingAs($this->adminOf($this->companyA))->get('/admin/companies');

        $response->assertNotFound();
    }

    public function test_user_of_a_deactivated_company_is_signed_out(): void
    {
        $this->createTwoCompanies();
        $admin = $this->adminOf($this->companyA);
        $this->companyA->forceFill(['is_active' => false])->save();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function createTwoCompanies(): void
    {
        $this->companyA = $this->createCompany(['name' => 'Company A']);
        $this->companyB = $this->createCompany(['name' => 'Company B']);

        $this->useCompany($this->companyB);
        $this->configure(['attendance_mode' => 'automatic']);
        $department = Department::factory()->create(['name' => 'Secret Projects']);
        $this->employeeB = $this->createEmployee(['first_name' => 'Bina', 'last_name' => 'Shah', 'department_id' => $department->id], 26000);

        $this->useCompany($this->companyA);
        $this->configure(['attendance_mode' => 'automatic']);
    }
}
