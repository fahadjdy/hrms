<?php

namespace Tests\Feature\Http;

use App\Enums\EmployeeStatus;
use App\Enums\PayrollBucket;
use App\Jobs\GenerateSalarySlips;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\SalarySlipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_salary_slip_escapes_markup_in_employee_and_company_names(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany(['name' => 'Acme <b>Bold</b> & Sons']));
        $this->configure(['attendance_mode' => 'automatic']);
        $this->createEmployee(['first_name' => '<script>alert(1)</script>', 'last_name' => "O'Brien"], 26000);
        $payrolls = app(PayrollService::class);
        $payrolls->finalize($payrolls->calculate($payrolls->create(2026, 9)));

        $html = view('pdf.salary-slip', app(SalarySlipService::class)->viewData(PayrollItem::query()->sole()))->render();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('Acme &lt;b&gt;Bold&lt;/b&gt; &amp; Sons', $html);
        $this->assertStringNotContainsString('<b>Bold</b>', $html);
    }

    public function test_csv_export_neutralizes_values_a_spreadsheet_would_run_as_a_formula(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->createEmployee(['employee_code' => '=1+1', 'first_name' => '@SUM(A1)', 'last_name' => null]);

        $response = $this->actingAs($this->adminOf($company))->get('/reports/export/attendance?month=2026-09');

        $row = str_getcsv(explode("\n", trim($response->streamedContent()))[1], escape: '\\');
        $this->assertSame("'=1+1", $row[0]);
        $this->assertSame("'@SUM(A1)", $row[1]);
    }

    public function test_request_cannot_set_protected_employee_attributes(): void
    {
        $company = $this->useCompany($this->createCompany());
        $other = $this->createCompany();

        $this->actingAs($this->adminOf($company))->post('/employees', [
            'employee_code' => 'EMP-0100', 'first_name' => 'Asha', 'joining_date' => '2026-09-01',
            'employment_type' => 'full_time', 'status' => 'active',
            'company_id' => $other->id,
            'photo_path' => '../../.env',
            'exit_date' => '2026-09-30',
            'last_working_date' => '2026-09-30',
            'exit_type' => 'termination',
        ])->assertSessionHasNoErrors();

        $employee = Employee::query()->sole();
        $this->assertSame($company->id, $employee->company_id);
        $this->assertNull($employee->photo_path);
        $this->assertNull($employee->exit_date);
        $this->assertNull($employee->last_working_date);
        $this->assertSame(EmployeeStatus::Active, $employee->status);
    }

    public function test_user_cannot_be_given_a_role_of_another_company(): void
    {
        $company = $this->createCompany();
        $otherRole = Role::withoutTenancy()->where('company_id', $this->createCompany()->id)->where('slug', Role::ADMIN_SLUG)->sole();

        $response = $this->actingAs($this->adminOf($company))->post('/settings/users', [
            'name' => 'Hema HR', 'email' => 'hema@acme.test', 'password' => 'secret-password', 'role_id' => $otherRole->id,
        ]);

        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseMissing('users', ['email' => 'hema@acme.test']);
    }

    public function test_role_of_another_company_grants_no_permissions(): void
    {
        $company = $this->useCompany($this->createCompany());
        $this->createEmployee();
        $otherRole = Role::withoutTenancy()->where('company_id', $this->createCompany()->id)->where('slug', Role::ADMIN_SLUG)->sole();
        // A role id pointing at another company's admin role, as if tampered with in the database.
        $user = User::factory()->forCompany($company)->create();
        $user->forceFill(['role_id' => $otherRole->id])->save();

        $this->actingAs($user->fresh());

        $this->get('/employees')->assertForbidden();
        $this->get('/settings/roles')->assertForbidden();
        $this->assertSame([], $user->fresh()->permissionList());
    }

    public function test_super_admin_holds_no_company_permissions(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertFalse($superAdmin->hasPermission('employees.view'));
        $this->assertSame([], $superAdmin->permissionList());
    }

    public function test_adjustment_of_another_payroll_item_is_not_found(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
        $this->createEmployee([], 26000);
        $this->createEmployee([], 30000);
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->calculate($payrolls->create(2026, 9));
        [$first, $second] = PayrollItem::query()->with(['payroll', 'employee'])->orderBy('id')->get()->all();
        $adjustment = $payrolls->addAdjustment($first, PayrollBucket::Bonus, 500, 'Spot award');

        $response = $this->actingAs($this->adminOf($company))
            ->delete("/payroll/{$payroll->id}/items/{$second->id}/adjustments/{$adjustment->id}");

        $response->assertNotFound();
        $this->assertModelExists($adjustment);
        $this->assertSame(1, PayrollAdjustment::query()->count());
    }

    public function test_payroll_export_of_another_company_is_not_found(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $payroll = app(PayrollService::class)->create(2026, 9);
        $company = $this->createCompany();

        $response = $this->actingAs($this->adminOf($company))->get("/reports/export/payroll?payroll_id={$payroll->id}");

        $response->assertNotFound();
    }

    public function test_sidebar_and_page_props_never_expose_the_password_hash(): void
    {
        $company = $this->createCompany();
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->get('/settings/roles');

        $content = json_encode($response->viewData('page'));
        $this->assertStringNotContainsString($admin->password, $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('two_factor_secret', $content);
    }
}
