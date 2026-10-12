<?php

namespace Tests\Feature\Mcp;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * Tool calls run the real web route as the connected user: same data,
 * validation, permissions and company isolation as the browser.
 */
class McpToolCallTest extends TestCase
{
    use InteractsWithCompanies, InteractsWithMcp, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        $this->company = $this->useCompany($this->createCompany(['name' => 'Company A']));
    }

    public function test_list_employees_returns_the_companys_employees_and_filters_by_search(): void
    {
        $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao']);
        $this->createEmployee(['first_name' => 'Bina', 'last_name' => 'Shah']);
        $admin = $this->adminOf($this->company);

        $all = $this->toolResult($this->callTool($admin, 'list_employees')->assertOk());
        $filtered = $this->toolResult($this->callTool($admin, 'list_employees', ['search' => 'Bina'])->assertOk());

        $this->assertTrue($all['ok']);
        $this->assertSame(['Asha Rao', 'Bina Shah'], array_column($all['data']['employees']['data'], 'name'));
        $this->assertSame(['Bina Shah'], array_column($filtered['data']['employees']['data'], 'name'));
        $this->assertArrayNotHasKey('auth', $all['data']);
    }

    public function test_create_department_saves_the_department(): void
    {
        $response = $this->callTool($this->adminOf($this->company), 'create_department', ['name' => 'Finance', 'description' => 'Accounts']);

        $response->assertOk();
        $this->assertTrue($this->toolResult($response)['ok']);
        $this->assertDatabaseHas('departments', ['company_id' => $this->company->id, 'name' => 'Finance', 'description' => 'Accounts']);
    }

    public function test_validation_errors_come_back_as_a_tool_error_naming_the_field(): void
    {
        Department::factory()->create(['name' => 'Finance']);

        $response = $this->callTool($this->adminOf($this->company), 'create_department', ['name' => 'Finance']);

        $response->assertHasErrors(['Validation failed', 'name']);
        $this->assertSame(1, Department::query()->count());
    }

    public function test_user_without_the_permission_gets_forbidden_through_call_route(): void
    {
        $viewer = $this->userWithRole($this->company, 'viewer');

        $response = $this->callTool($viewer, 'call_route', ['route' => 'departments.store', 'data' => ['name' => 'Finance']]);

        $response->assertHasErrors(['Forbidden']);
        $this->assertSame(403, $this->toolResult($response)['status']);
        $this->assertDatabaseMissing('departments', ['name' => 'Finance']);
    }

    public function test_another_companys_employee_is_not_found_and_nothing_leaks(): void
    {
        $companyB = $this->useCompany($this->createCompany(['name' => 'Company B']));
        $employeeB = $this->createEmployee(['first_name' => 'Zara', 'last_name' => 'Secret', 'email' => 'zara@company-b.test']);
        $this->useCompany($this->company);

        $response = $this->callTool($this->adminOf($this->company), 'get_employee', ['employee' => $employeeB->id]);

        $response->assertHasErrors(['Not found'])->assertDontSee(['Zara', 'Secret', 'zara@company-b.test', $companyB->name]);
        $this->assertSame(404, $this->toolResult($response)['status']);
    }

    public function test_get_dashboard_includes_the_deferred_sections(): void
    {
        $this->createEmployee([], 26000);

        $result = $this->toolResult($this->callTool($this->adminOf($this->company), 'get_dashboard')->assertOk());

        $this->assertSame('Dashboard', $result['page']);
        $this->assertArrayHasKey('kpis', $result['data']);
        foreach (['attendance', 'hoursTrend', 'payroll', 'borrow', 'people', 'calendar', 'activity'] as $deferred) {
            $this->assertArrayHasKey($deferred, $result['data']);
        }
    }

    public function test_export_report_returns_the_csv_file(): void
    {
        $this->createEmployee(['employee_code' => 'EMP-0001', 'first_name' => 'Asha', 'last_name' => 'Rao']);

        $result = $this->toolResult($this->callTool($this->adminOf($this->company), 'export_report', ['report' => 'attendance', 'month' => '2026-09'])->assertOk());

        $this->assertArrayHasKey('file', $result, 'Expected a file, got: '.json_encode($result));
        $this->assertSame('attendance-2026-09.csv', $result['file']['name']);
        $content = $result['file']['content'] ?? base64_decode($result['file']['content_base64']);
        $this->assertStringStartsWith('"Employee ID",Name,Department', $content);
        $rows = array_map(fn (string $line): array => str_getcsv($line, escape: '\\'), explode("\n", trim($content)));
        $this->assertCount(2, $rows);
        $this->assertSame(['EMP-0001', 'Asha Rao'], array_slice($rows[1], 0, 2));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unavailableRoutes(): array
    {
        return [
            'impersonation' => ['admin.companies.impersonate'],
            'deploy' => ['deploy.migrate'],
            'unknown' => ['no.such.route'],
            'framework route' => ['passport.token'],
        ];
    }

    #[DataProvider('unavailableRoutes')]
    public function test_call_route_rejects_excluded_and_unknown_routes(string $route): void
    {
        $response = $this->callTool($this->adminOf($this->company), 'call_route', ['route' => $route]);

        $response->assertHasErrors(["Unknown or unavailable route [{$route}]"]);
    }

    public function test_list_routes_for_a_viewer_omits_manage_routes(): void
    {
        $result = $this->toolResult($this->callTool($this->userWithRole($this->company, 'viewer'), 'list_routes')->assertOk());

        $routes = array_column($result['routes'], 'route');
        $this->assertContains('departments.index', $routes);
        $this->assertContains('employees.index', $routes);
        $this->assertNotContains('departments.store', $routes);
        $this->assertNotContains('employees.store', $routes);
        $this->assertNotContains('settings.roles.index', $routes);
        $this->assertSame(count($routes), $result['count']);
        $this->assertSame('list_departments', collect($result['routes'])->firstWhere('route', 'departments.index')['tool']);
    }
}
