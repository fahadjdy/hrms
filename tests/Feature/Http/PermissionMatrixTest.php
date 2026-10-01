<?php

namespace Tests\Feature\Http;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * Each permission opens its own area and nothing else: a user holding only
 * that permission gets in, and a user holding every other permission does not.
 */
class PermissionMatrixTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'employees.view' => ['employees.view', 'get', '/employees'],
            'employees.view (profile)' => ['employees.view', 'get', '/employees/{employee}'],
            'employees.view (departments)' => ['employees.view', 'get', '/departments'],
            'employees.manage' => ['employees.manage', 'get', '/employees/create'],
            'employees.manage (departments)' => ['employees.manage', 'post', '/departments'],
            'attendance.view' => ['attendance.view', 'get', '/attendance'],
            'attendance.view (calendar)' => ['attendance.view', 'get', '/employees/{employee}/attendance'],
            'attendance.manage' => ['attendance.manage', 'post', '/attendance/generate'],
            'attendance.manage (holidays)' => ['attendance.manage', 'post', '/holidays'],
            'leave.view' => ['leave.view', 'get', '/leaves'],
            'leave.manage' => ['leave.manage', 'post', '/leaves'],
            'payroll.view' => ['payroll.view', 'get', '/payroll'],
            'payroll.view (salary)' => ['payroll.view', 'get', '/employees/{employee}/salary'],
            'payroll.view (slips)' => ['payroll.view', 'get', '/salary-slips'],
            'payroll.manage' => ['payroll.manage', 'post', '/payroll'],
            'payroll.manage (salary revision)' => ['payroll.manage', 'post', '/employees/{employee}/salary'],
            'payroll.finalize' => ['payroll.finalize', 'post', '/payroll/{payroll}/finalize'],
            'payroll.finalize (reopen)' => ['payroll.finalize', 'post', '/payroll/{payroll}/reopen'],
            'finance.view' => ['finance.view', 'get', '/borrows'],
            'finance.view (overtime)' => ['finance.view', 'get', '/overtime'],
            'finance.manage' => ['finance.manage', 'get', '/borrows/create'],
            'finance.manage (overtime)' => ['finance.manage', 'post', '/overtime'],
            'settlements.manage' => ['settlements.manage', 'get', '/final-settlements'],
            'reports.view' => ['reports.view', 'get', '/reports'],
            'reports.view (export)' => ['reports.view', 'get', '/reports/export/borrow'],
            'settings.manage' => ['settings.manage', 'get', '/settings/company'],
            'settings.manage (payroll)' => ['settings.manage', 'put', '/settings/payroll'],
            'roles.manage' => ['roles.manage', 'get', '/settings/roles'],
            'roles.manage (users)' => ['roles.manage', 'post', '/settings/users'],
            'audit.view' => ['audit.view', 'get', '/audit-logs'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_endpoint_is_open_with_its_permission_and_forbidden_without_it(string $permission, string $method, string $url): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $url = strtr($url, [
            '{employee}' => (string) $this->createEmployee()->id,
            '{payroll}' => (string) app(PayrollService::class)->create(2026, 9)->id,
        ]);
        $holder = $this->userWith($company, 'holder', [$permission]);
        $others = $this->userWith($company, 'others', array_values(array_diff(Permission::values(), [$permission])));

        $allowed = $this->actingAs($holder)->{$method}($url);
        $refused = $this->actingAs($others)->{$method}($url);

        $this->assertNotSame(403, $allowed->getStatusCode(), "{$permission} did not open {$method} {$url}.");
        $refused->assertForbidden();
    }

    public function test_every_permission_is_covered_by_the_matrix(): void
    {
        $covered = array_values(array_unique(array_column(self::protectedEndpoints(), 0)));
        sort($covered);
        $all = Permission::values();
        sort($all);

        $this->assertSame($all, $covered);
    }

    public function test_user_without_a_role_can_only_open_the_dashboard(): void
    {
        $company = $this->useCompany($this->createCompany());
        $this->actingAs(User::factory()->forCompany($company)->create());

        $this->get('/dashboard')->assertOk();
        $this->get('/employees')->assertForbidden();
        $this->get('/payroll')->assertForbidden();
        $this->get('/audit-logs')->assertForbidden();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(Company $company, string $slug, array $permissions): User
    {
        $role = new Role(['name' => ucfirst($slug), 'slug' => $slug, 'permissions' => $permissions]);
        $role->save();

        return User::factory()->forCompany($company, $role)->create();
    }
}
