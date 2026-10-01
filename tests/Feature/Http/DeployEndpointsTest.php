<?php

namespace Tests\Feature\Http;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * The deploy URLs need no login, so these tests pin down what a stranger
 * could and could not do with them.
 */
class DeployEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Each test starts outside the pause that follows a deploy task.
        @unlink(storage_path('framework/deploy.lock'));
    }

    public function test_guest_can_see_the_list_of_deploy_urls(): void
    {
        $response = $this->get('/deploy');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee(url('deploy/migrate'), false);
        $response->assertSee(url('deploy/seed'), false);
        $response->assertSee(url('deploy/storage-link'), false);
    }

    public function test_guest_can_run_pending_migrations(): void
    {
        $response = $this->get('/deploy/migrate');

        $response->assertOk();
        $response->assertSee('Finished without errors.');
    }

    public function test_seed_is_refused_once_the_database_has_users(): void
    {
        User::factory()->create();

        $response = $this->get('/deploy/seed');

        $response->assertConflict();
        $response->assertSee('already has users');
        $this->assertSame(1, User::query()->count());
    }

    public function test_seed_on_an_empty_database_creates_the_configured_super_admin_and_no_demo_data(): void
    {
        config([
            'hrms.seed.demo_data' => false,
            'hrms.seed.super_admin_name' => 'Site Owner',
            'hrms.seed.super_admin_email' => 'owner@example.com',
            'hrms.seed.super_admin_password' => 'a-strong-passphrase',
        ]);

        $response = $this->get('/deploy/seed');

        $response->assertOk();
        $owner = User::query()->sole();
        $this->assertSame('owner@example.com', $owner->email);
        $this->assertTrue($owner->is_super_admin);
        $this->assertTrue(Hash::check('a-strong-passphrase', $owner->password));
        $this->assertSame(0, Company::query()->count());
    }

    public function test_existing_data_is_untouched_by_the_deploy_tasks(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->createCompany(['name' => 'Kept Company']);
        $this->useCompany($company);
        $employee = $this->createEmployee(['first_name' => 'Kept', 'last_name' => 'Employee'], 26000);
        $this->markAttendance($employee, '2026-09-10', 'present');
        $before = $this->rowCounts();

        // The tasks that touch the database. `cache` is left out: it would write
        // cache files built from the test configuration into the project.
        foreach (['run', 'migrate', 'seed', 'clear'] as $task) {
            @unlink(storage_path('framework/deploy.lock'));
            $this->get("/deploy/{$task}");
        }

        $this->assertSame($before, $this->rowCounts());
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'first_name' => 'Kept']);
        $this->assertDatabaseHas('companies', ['id' => $company->id, 'name' => 'Kept Company']);
    }

    public function test_a_second_task_right_after_the_first_is_told_to_wait(): void
    {
        $this->get('/deploy/migrate')->assertOk();

        $response = $this->get('/deploy/migrate');

        $response->assertTooManyRequests();
        $response->assertSee('Wait a few seconds');
    }

    #[TestWith(['/deploy'])]
    #[TestWith(['/deploy/run'])]
    #[TestWith(['/deploy/setup'])]
    #[TestWith(['/deploy/migrate'])]
    #[TestWith(['/deploy/seed'])]
    #[TestWith(['/deploy/storage-link'])]
    #[TestWith(['/deploy/cache'])]
    #[TestWith(['/deploy/clear'])]
    public function test_deploy_urls_are_not_found_when_switched_off(string $url): void
    {
        config(['hrms.deploy.routes_enabled' => false]);

        $response = $this->get($url);

        $response->assertNotFound();
        $this->assertSame(0, User::query()->count());
    }

    public function test_no_deploy_url_runs_a_destructive_command(): void
    {
        $commands = [];
        Artisan::partialMock()
            ->shouldReceive('call')
            ->andReturnUsing(function (string $command) use (&$commands): int {
                $commands[] = $command;

                return 0;
            });
        Artisan::shouldReceive('output')->andReturn('');

        foreach (['run', 'setup', 'migrate', 'storage-link', 'cache', 'clear'] as $task) {
            @unlink(storage_path('framework/deploy.lock'));
            $this->get("/deploy/{$task}")->assertOk();
        }

        $this->assertEqualsCanonicalizing(
            ['migrate', 'storage:link', 'optimize', 'optimize:clear'],
            array_values(array_unique($commands)),
        );
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        $tables = [
            'companies', 'users', 'roles', 'company_settings', 'employees', 'attendances',
            'employee_salary_revisions', 'employee_salary_components', 'work_shifts', 'leave_types', 'audit_logs',
        ];

        return collect($tables)
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }
}
