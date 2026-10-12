<?php

namespace Tests\Feature\Http;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryRevision;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class SalaryRevisionEditTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_editing_components_replaces_them_and_sets_the_new_gross(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 30000);
        $july = $this->setSalary($employee, 35000, '2026-07-01', reason: 'Annual Increment');
        $oldComponentIds = $july->components->modelKeys();

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$july->id}", [
            'effective_date' => '2026-07-01',
            'reason' => 'Annual Increment (corrected)',
            'notes' => 'HRA was missing',
            'components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => 36000],
                ['name' => 'HRA', 'type' => 'earning', 'amount' => 4000],
                ['name' => 'PF', 'type' => 'deduction', 'amount' => 1800],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Salary revision updated.']);
        $july->refresh();
        $this->assertSame(40000.0, $july->new_gross);
        $this->assertSame(30000.0, $july->previous_gross);
        $this->assertSame('Annual Increment (corrected)', $july->reason);
        $this->assertSame('HRA was missing', $july->notes);
        $this->assertSame(
            ['basic' => 36000.0, 'hra' => 4000.0, 'pf' => 1800.0],
            $july->components()->pluck('amount', 'code')->all(),
        );
        $this->assertSame(0, EmployeeSalaryComponent::query()->whereKey($oldComponentIds)->count());
        $this->assertSame(2, EmployeeSalaryRevision::query()->count());
    }

    public function test_moving_the_effective_date_reorders_history_and_fixes_the_neighbours(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 30000);
        $march = $this->setSalary($employee, 35000, '2026-03-01');
        $june = $this->setSalary($employee, 40000, '2026-06-01');
        $first = EmployeeSalaryRevision::query()->where('effective_date', '2026-01-01')->sole();

        // March's revision moves after June's.
        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$march->id}", [
            'effective_date' => '2026-08-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 45000]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(0.0, $first->refresh()->previous_gross);
        $this->assertNull($first->previous_components);
        $this->assertSame(30000.0, $june->refresh()->previous_gross);
        $this->assertSame([['code' => 'basic', 'name' => 'Basic', 'type' => 'earning', 'amount' => 30000]], $june->previous_components);
        $march->refresh();
        $this->assertSame('2026-08-01', $march->effective_date->toDateString());
        $this->assertSame(40000.0, $march->previous_gross);
        $this->assertSame(45000.0, $march->new_gross);
        $this->assertSame([['code' => 'basic', 'name' => 'Basic', 'type' => 'earning', 'amount' => 40000]], $march->previous_components);
    }

    public function test_an_effective_date_used_by_another_revision_is_refused(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 30000);
        $july = $this->setSalary($employee, 35000, '2026-07-01');

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$july->id}", [
            'effective_date' => '2026-01-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 36000]],
        ]);

        $response->assertSessionHasErrors([
            'effective_date' => 'A salary revision already exists for this date. Choose a different effective date.',
        ]);
        $this->assertSame('2026-07-01', $july->refresh()->effective_date->toDateString());
        $this->assertSame(35000.0, $july->new_gross);
    }

    public function test_the_effective_date_cannot_move_before_the_joining_date(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-03-01'], 30000);
        $revision = EmployeeSalaryRevision::query()->sole();

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$revision->id}", [
            'effective_date' => '2026-02-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 30000]],
        ]);

        $response->assertSessionHasErrors(['effective_date' => 'The effective date cannot be before the joining date.']);
    }

    public function test_a_revision_used_by_a_finalized_payroll_cannot_be_edited(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $august = $this->setSalary($employee, 28000, '2026-08-01');
        $this->finalizePayroll(2026, 9);

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$august->id}", [
            'effective_date' => '2026-08-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 99000]],
        ]);

        $response->assertSessionHasErrors([
            'revision' => 'Payroll for September 2026 is finalized and used this salary. Reopen it before editing this revision.',
        ]);
        $this->assertSame(28000.0, $august->refresh()->new_gross);
        $this->assertSame(0, AuditLog::query()->where('action', 'salary.revision_updated')->count());
    }

    public function test_moving_a_later_revision_into_a_finalized_period_is_refused(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $november = $this->setSalary($employee, 30000, '2026-11-01');
        $this->finalizePayroll(2026, 9);

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$november->id}", [
            'effective_date' => '2026-09-15',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 30000]],
        ]);

        $response->assertSessionHasErrors('revision');
        $this->assertSame('2026-11-01', $november->refresh()->effective_date->toDateString());
    }

    public function test_an_open_payroll_does_not_block_the_edit_and_the_toast_asks_for_recalculation(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $september = $this->setSalary($employee, 28000, '2026-09-01');
        $this->finalizePayroll(2026, 8);
        $payrolls = app(PayrollService::class);
        $payrolls->calculate($payrolls->create(2026, 9));

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$september->id}", [
            'effective_date' => '2026-09-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 29000]],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => 'Salary revision updated. Recalculate the payroll for September 2026 to apply it.',
        ]);
        $this->assertSame(29000.0, $september->refresh()->new_gross);
    }

    public function test_an_edit_is_audited_with_the_old_and_new_values(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao', 'joining_date' => '2026-01-01'], 30000);
        $july = $this->setSalary($employee, 35000, '2026-07-01');

        $this->actingAs($admin = $this->adminOf($company))->put("/employees/{$employee->id}/salary/{$july->id}", [
            'effective_date' => '2026-08-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 37000]],
        ])->assertSessionHasNoErrors();

        $log = AuditLog::query()->where('action', 'salary.revision_updated')->sole();
        $this->assertSame($employee->id, $log->employee_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Salary revision of Asha Rao from 2026-07-01 edited', $log->description);
        $this->assertSame('2026-07-01', $log->old_values['effective_date']);
        $this->assertEquals(35000, $log->old_values['gross']);
        $this->assertEquals(35000, $log->old_values['components'][0]['amount']);
        $this->assertSame('2026-08-01', $log->new_values['effective_date']);
        $this->assertEquals(37000, $log->new_values['gross']);
        $this->assertEquals(37000, $log->new_values['components'][0]['amount']);
    }

    public function test_a_user_without_payroll_manage_cannot_edit_a_revision(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee([], 30000);
        $revision = EmployeeSalaryRevision::query()->sole();

        $response = $this->actingAs($this->userWithRole($company, 'viewer'))->put("/employees/{$employee->id}/salary/{$revision->id}", [
            'effective_date' => $revision->effective_date->toDateString(),
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 99000]],
        ]);

        $response->assertForbidden();
        $this->assertSame(30000.0, $revision->refresh()->new_gross);
    }

    public function test_another_employees_revision_is_not_found(): void
    {
        $company = $this->prepareCompany();
        $asha = $this->createEmployee(['joining_date' => '2026-01-01'], 30000);
        $bina = $this->createEmployee(['joining_date' => '2026-01-01'], 40000);
        $binasRevision = EmployeeSalaryRevision::query()->where('employee_id', $bina->id)->sole();

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$asha->id}/salary/{$binasRevision->id}", [
            'effective_date' => '2026-01-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 99000]],
        ]);

        $response->assertNotFound();
        $this->assertSame(40000.0, $binasRevision->refresh()->new_gross);
    }

    public function test_salary_page_marks_revisions_used_by_a_finalized_payroll_as_locked(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $this->setSalary($employee, 28000, '2026-08-01');
        $this->setSalary($employee, 30000, '2026-11-01');
        $this->finalizePayroll(2026, 8);
        $this->finalizePayroll(2026, 9);

        $response = $this->actingAs($this->adminOf($company))->get("/employees/{$employee->id}/salary");

        $response->assertInertia(fn (Assert $page) => $page
            ->has('revisions', 3)
            ->where('revisions.0.effective_date', '2026-11-01')
            ->where('revisions.0.is_locked', false)
            ->where('revisions.0.locked_reason', null)
            ->where('revisions.1.effective_date', '2026-08-01')
            ->where('revisions.1.is_locked', true)
            ->where('revisions.1.locked_reason', 'Payrolls for August 2026 and September 2026 are finalized and used this salary. Reopen them before editing this revision.')
            ->where('revisions.2.is_locked', true));
    }

    private function finalizePayroll(int $year, int $month): void
    {
        $payrolls = app(PayrollService::class);
        $payrolls->finalize($payrolls->calculate($payrolls->create($year, $month)));
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-12 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);

        return $company;
    }
}
