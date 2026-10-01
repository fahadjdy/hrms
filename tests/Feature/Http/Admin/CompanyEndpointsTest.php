<?php

namespace Tests\Feature\Http\Admin;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Models\WeeklyHoliday;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class CompanyEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_super_admin_creates_a_company_that_is_ready_to_use(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->post('/admin/companies', $this->payload());

        $company = Company::query()->where('name', 'Acme Technologies')->sole();
        $response->assertRedirect(route('admin.companies.show', $company));
        $this->assertSame('acme-technologies', $company->slug);
        $this->assertTrue($company->is_active);
        $this->assertSame('INR', $company->currency);

        $admin = User::query()->where('email', 'admin@acme.test')->sole();
        $this->assertSame($company->id, $admin->company_id);
        $this->assertFalse($admin->is_super_admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertSame(Role::ADMIN_SLUG, $admin->role->slug);
        $this->assertSame(Permission::values(), $admin->permissionList());

        $this->useCompany($company);
        $this->assertSame(['company-admin', 'hr-manager', 'viewer'], Role::query()->orderBy('slug')->pluck('slug')->all());
        $shift = WorkShift::query()->sole();
        $this->assertSame($shift->id, CompanySetting::query()->sole()->default_work_shift_id);
        $this->assertSame([0], WeeklyHoliday::query()->pluck('day_of_week')->all());
        $this->assertSame(['CL', 'PL', 'SL', 'UL'], LeaveType::query()->orderBy('code')->pluck('code')->all());
        $this->assertTrue(AuditLog::query()->where('action', 'company.created')->exists());
    }

    public function test_creating_a_company_hands_the_admin_login_to_the_super_admin_once(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post('/admin/companies', $this->payload());
        $company = Company::query()->where('name', 'Acme Technologies')->sole();

        $this->get(route('admin.companies.show', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->hasFlash('credentials.email', 'admin@acme.test')
                ->hasFlash('credentials.password', 'secret-password')
                ->hasFlash('credentials.login_url', route('login')));

        $this->get(route('admin.companies.show', $company))
            ->assertInertia(fn (Assert $page) => $page->missingFlash('credentials'));
    }

    public function test_new_company_admin_can_sign_in_and_reach_the_dashboard(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->post('/admin/companies', $this->payload());
        $this->post(route('logout'));

        $response = $this->post(route('login.store'), ['email' => 'admin@acme.test', 'password' => 'secret-password']);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs(User::query()->where('email', 'admin@acme.test')->sole());
        $this->get('/dashboard')->assertOk();
    }

    public function test_creating_a_company_requires_company_and_admin_details(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())->post('/admin/companies', []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'currency' => 'The currency field is required.',
            'timezone' => 'The timezone field is required.',
            'admin_name' => 'The admin name field is required.',
            'admin_email' => 'The admin email field is required.',
            'admin_password' => 'The admin password field is required.',
        ]);
        $this->assertSame(0, Company::query()->count());
    }

    public function test_company_admin_email_must_not_belong_to_another_user(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['email' => 'owner@platform.test']);

        $response = $this->actingAs($superAdmin)->post('/admin/companies', $this->payload(['admin_email' => 'owner@platform.test']));

        $response->assertSessionHasErrors(['admin_email' => 'The admin email has already been taken.']);
        $this->assertSame(0, Company::query()->count());
    }

    public function test_two_companies_with_the_same_name_get_different_slugs(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post('/admin/companies', $this->payload());
        $this->post('/admin/companies', $this->payload(['admin_email' => 'second@acme.test']));

        $this->assertSame(
            ['acme-technologies', 'acme-technologies-2'],
            Company::query()->orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_super_admin_updates_company_details(): void
    {
        $company = $this->createCompany(['name' => 'Old Name']);

        $response = $this->actingAs(User::factory()->superAdmin()->create())->put("/admin/companies/{$company->id}", [
            'name' => 'New Name', 'currency' => 'USD', 'timezone' => 'America/New_York', 'date_format' => 'm/d/Y',
        ]);

        $response->assertRedirect(route('admin.companies.show', $company));
        $company->refresh();
        $this->assertSame('New Name', $company->name);
        $this->assertSame('USD', $company->currency);
        $this->assertSame('America/New_York', $company->timezone);
    }

    public function test_deactivating_a_company_stops_its_users_from_signing_in(): void
    {
        $company = $this->createCompany();
        $admin = $this->adminOf($company);

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->put("/admin/companies/{$company->id}/status", ['is_active' => false]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse($company->refresh()->is_active);
        $this->assertTrue(AuditLog::withoutTenancy()->where('company_id', $company->id)->where('action', 'company.deactivated')->exists());

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_reactivating_a_company_lets_its_users_sign_in_again(): void
    {
        $company = $this->createCompany();
        $company->forceFill(['is_active' => false])->save();
        $admin = $this->adminOf($company);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->put("/admin/companies/{$company->id}/status", ['is_active' => true]);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_super_admin_signs_in_as_the_company_admin_and_returns(): void
    {
        $company = $this->createCompany();
        $admin = $this->adminOf($company);
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)->post("/admin/companies/{$company->id}/impersonate");

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $response->assertSessionHas('impersonator_id', $superAdmin->id);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.impersonating', true)
            ->where('company.id', $company->id));
        $this->assertTrue(AuditLog::withoutTenancy()->where('company_id', $company->id)->where('action', 'company.impersonated')->exists());

        $leave = $this->post('/impersonation/leave');

        $leave->assertRedirect(route('admin.companies.index'));
        $leave->assertSessionMissing('impersonator_id');
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_super_admin_cannot_sign_in_as_a_user_of_a_different_company_through_this_company(): void
    {
        $company = $this->createCompany();
        $outsider = $this->adminOf($this->createCompany());
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)
            ->post("/admin/companies/{$company->id}/impersonate", ['user_id' => $outsider->id]);

        $response->assertSessionMissing('impersonator_id');
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_super_admin_can_sign_in_as_a_named_user_of_the_company(): void
    {
        $company = $this->createCompany();
        $viewer = $this->userWithRole($company, 'viewer');
        $superAdmin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($superAdmin)
            ->post("/admin/companies/{$company->id}/impersonate", ['user_id' => $viewer->id]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($viewer);
    }

    public function test_leaving_impersonation_is_forbidden_when_not_impersonating(): void
    {
        $company = $this->createCompany();
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post('/impersonation/leave');

        $response->assertForbidden();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_deactivated_company_cannot_be_impersonated(): void
    {
        $company = $this->createCompany();
        $company->forceFill(['is_active' => false])->save();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post("/admin/companies/{$company->id}/impersonate");

        $this->assertAuthenticatedAs($superAdmin);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function platformEndpoints(): array
    {
        return [
            'company list' => ['get', '/admin/companies'],
            'create form' => ['get', '/admin/companies/create'],
            'store' => ['post', '/admin/companies'],
            'show' => ['get', '/admin/companies/{id}'],
            'edit form' => ['get', '/admin/companies/{id}/edit'],
            'update' => ['put', '/admin/companies/{id}'],
            'status' => ['put', '/admin/companies/{id}/status'],
            'impersonate' => ['post', '/admin/companies/{id}/impersonate'],
            'platform settings' => ['get', '/admin/settings'],
            'update platform settings' => ['put', '/admin/settings'],
        ];
    }

    #[DataProvider('platformEndpoints')]
    public function test_company_admin_gets_not_found_on_every_platform_route(string $method, string $url): void
    {
        $company = $this->createCompany(['name' => 'Company A']);
        $other = $this->createCompany(['name' => 'Company B']);

        $response = $this->actingAs($this->adminOf($company))
            ->{$method}(str_replace('{id}', (string) $other->id, $url), ['name' => 'Hacked', 'is_active' => false]);

        $response->assertNotFound();
        $other->refresh();
        $this->assertSame('Company B', $other->name);
        $this->assertTrue($other->is_active);
        $this->assertSame(2, Company::query()->count());
    }

    public function test_guest_is_redirected_to_login_from_the_platform_area(): void
    {
        $response = $this->get('/admin/companies');

        $response->assertRedirect(route('login'));
    }

    public function test_company_list_counts_employees_and_users_of_each_company(): void
    {
        $company = $this->createCompany(['name' => 'Acme']);
        $this->useCompany($company);
        Employee::factory()->count(2)->create();
        Employee::factory()->past()->create();
        $this->tenant()->forget();

        $response = $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/companies');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('companies.data', 1)
            ->where('companies.data.0.name', 'Acme')
            ->where('companies.data.0.users_count', 1)
            ->where('companies.data.0.employees_count', 3)
            ->where('companies.data.0.active_employees_count', 2)
            ->where('stats.companies', 1)
            ->where('stats.active_companies', 1)
            ->where('stats.employees', 2)
            ->where('stats.users', 1));
    }

    public function test_super_admin_saves_platform_settings(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())->put('/admin/settings', [
            'platform_name' => 'PeopleDesk',
            'support_email' => 'help@peopledesk.test',
            'default_currency' => 'USD',
            'default_timezone' => 'UTC',
            'default_date_format' => 'Y-m-d',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('platform_settings', ['key' => 'platform_name', 'value' => 'PeopleDesk']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'default_currency', 'value' => 'USD']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Acme Technologies',
            'legal_name' => 'Acme Technologies Pvt Ltd',
            'email' => 'hello@acme.test',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'date_format' => 'd M Y',
            'admin_name' => 'Asha Admin',
            'admin_email' => 'admin@acme.test',
            'admin_password' => 'secret-password',
            ...$overrides,
        ];
    }
}
