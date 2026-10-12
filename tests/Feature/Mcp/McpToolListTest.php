<?php

namespace Tests\Feature\Mcp;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * tools/list only offers the tools the connected account can actually use.
 */
class McpToolListTest extends TestCase
{
    use InteractsWithCompanies, InteractsWithMcp, RefreshDatabase;

    private const ALWAYS_LISTED = ['get_me', 'list_routes', 'call_route', 'get_my_profile', 'update_my_profile', 'change_my_password'];

    public function test_company_admin_gets_the_company_tools_and_no_platform_tools(): void
    {
        $company = $this->useCompany($this->createCompany());

        $tools = $this->toolNamesFor($this->adminOf($company));

        foreach ([...self::ALWAYS_LISTED, 'list_employees', 'create_employee', 'create_department', 'get_dashboard', 'export_report', 'finalize_payroll', 'create_role'] as $tool) {
            $this->assertContains($tool, $tools);
        }
        $this->assertNotContains('list_companies', $tools);
        $this->assertNotContains('update_platform_settings', $tools);
    }

    public function test_view_only_role_gets_read_tools_but_not_manage_tools(): void
    {
        $company = $this->useCompany($this->createCompany());
        $role = Role::query()->create(['name' => 'Reader', 'slug' => 'reader', 'permissions' => [Permission::EmployeesView->value]]);
        $reader = User::factory()->forCompany($company, $role)->create();

        $tools = $this->toolNamesFor($reader);

        foreach ([...self::ALWAYS_LISTED, 'list_departments', 'list_employees', 'get_employee'] as $tool) {
            $this->assertContains($tool, $tools);
        }
        foreach (['create_department', 'create_employee', 'update_employee', 'list_payrolls', 'export_report', 'create_role'] as $tool) {
            $this->assertNotContains($tool, $tools);
        }
    }

    public function test_super_admin_gets_the_platform_tools_and_no_company_tools(): void
    {
        $tools = $this->toolNamesFor(User::factory()->superAdmin()->create());

        foreach ([...self::ALWAYS_LISTED, 'list_companies', 'create_company', 'update_platform_settings'] as $tool) {
            $this->assertContains($tool, $tools);
        }
        foreach (['list_employees', 'create_department', 'get_dashboard', 'list_payrolls'] as $tool) {
            $this->assertNotContains($tool, $tools);
        }
    }

    public function test_user_of_a_deactivated_company_only_gets_the_account_tools(): void
    {
        $company = $this->useCompany($this->createCompany());
        $admin = $this->adminOf($company);
        Company::query()->whereKey($company->id)->update(['is_active' => false]);

        $tools = $this->toolNamesFor($admin->fresh());

        foreach (self::ALWAYS_LISTED as $tool) {
            $this->assertContains($tool, $tools);
        }
        $this->assertNotContains('list_employees', $tools);
    }
}
