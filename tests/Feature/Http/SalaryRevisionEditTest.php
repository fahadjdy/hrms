<?php

namespace Tests\Feature\Http;

use App\Enums\PayrollBucket;
use App\Enums\PayrollStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryRevision;
use App\Models\PayrollItem;
use App\Services\EmployeeService;
use App\Services\FinalSettlementService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_an_open_payroll_goes_back_to_draft_and_must_be_recalculated(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $september = $this->setSalary($employee, 28000, '2026-09-01');
        $this->finalizePayroll(2026, 8);
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->markUnderReview($payrolls->calculate($payrolls->create(2026, 9)));

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$september->id}", [
            'effective_date' => '2026-09-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 29000]],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => 'Salary revision updated. The payroll for September 2026 was moved back to draft; recalculate it.',
        ]);
        $this->assertSame(29000.0, $september->refresh()->new_gross);
        $this->assertSame(PayrollStatus::Draft, $payroll->refresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'payroll.reset_to_draft')->count());

        try {
            $payrolls->finalize($payroll);
            $this->fail('A payroll moved back to draft was finalized with the old salary.');
        } catch (ValidationException $exception) {
            $this->assertSame(['payroll' => ['Calculate the payroll before continuing.']], $exception->errors());
        }

        $payrolls->calculate($payroll);
        $this->assertSame(PayrollStatus::Calculated, $payroll->refresh()->status);
        $this->assertSame(29000.0, PayrollItem::query()->where('payroll_id', $payroll->id)->sole()->gross_salary);
    }

    public function test_an_open_payroll_outside_the_affected_days_is_left_alone(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $march = $this->setSalary($employee, 27000, '2026-03-01');
        $this->setSalary($employee, 28000, '2026-06-01');
        $payrolls = app(PayrollService::class);
        $september = $payrolls->calculate($payrolls->create(2026, 9));

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$march->id}", [
            'effective_date' => '2026-03-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 27500]],
        ]);

        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Salary revision updated.']);
        $this->assertSame(PayrollStatus::Calculated, $september->refresh()->status);
    }

    public function test_an_edit_is_audited_with_the_old_and_new_values(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao', 'joining_date' => '2026-01-01'], 30000);
        $july = $this->setSalary($employee, 35000, '2026-07-01');

        $july->forceFill(['reason' => 'Increment', 'notes' => 'Old note'])->save();

        $this->actingAs($admin = $this->adminOf($company))->put("/employees/{$employee->id}/salary/{$july->id}", [
            'effective_date' => '2026-08-01',
            'reason' => 'Annual Increment',
            'notes' => 'Moved to August',
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
        $this->assertSame('Increment', $log->old_values['reason']);
        $this->assertSame('Old note', $log->old_values['notes']);
        $this->assertSame('Annual Increment', $log->new_values['reason']);
        $this->assertSame('Moved to August', $log->new_values['notes']);
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

    public function test_salary_page_locks_only_revisions_whose_own_days_were_paid(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $this->setSalary($employee, 28000, '2026-08-01');
        $this->setSalary($employee, 30000, '2026-11-01');
        $this->finalizePayroll(2026, 8);
        $this->finalizePayroll(2026, 9);

        // January's revision covers 1 January - 31 July: no finalized payroll paid it.

        $response = $this->actingAs($this->adminOf($company))->get("/employees/{$employee->id}/salary");

        $response->assertInertia(fn (Assert $page) => $page
            ->has('revisions', 3)
            ->where('revisions.0.effective_date', '2026-11-01')
            ->where('revisions.0.is_locked', false)
            ->where('revisions.0.locked_reason', null)
            ->where('revisions.1.effective_date', '2026-08-01')
            ->where('revisions.1.is_locked', true)
            ->where('revisions.1.locked_reason', 'Payrolls for August 2026 and September 2026 are finalized and used this salary. Reopen them before editing this revision.')
            ->where('revisions.2.effective_date', '2026-01-01')
            ->where('revisions.2.is_locked', false)
            ->where('revisions.2.locked_reason', null));
    }

    public function test_an_earlier_revision_not_used_by_the_finalized_payroll_can_be_edited(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $january = EmployeeSalaryRevision::query()->sole();
        $this->setSalary($employee, 28000, '2026-09-01');
        $this->finalizePayroll(2026, 9);

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$january->id}", [
            'effective_date' => '2026-01-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 25000]],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(25000.0, $january->refresh()->new_gross);
        $this->assertSame(25000.0, EmployeeSalaryRevision::query()->where('effective_date', '2026-09-01')->sole()->previous_gross);
    }

    public function test_reason_and_notes_of_a_locked_revision_can_still_be_edited(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $august = $this->setSalary($employee, 28000, '2026-08-01', [['name' => 'HRA', 'type' => 'earning', 'amount' => 2000]]);
        $this->finalizePayroll(2026, 9);

        $response = $this->actingAs($this->adminOf($company))->put("/employees/{$employee->id}/salary/{$august->id}", [
            'effective_date' => '2026-08-01',
            'reason' => 'Promotion',
            'notes' => 'Approved by the director',
            'components' => [
                ['name' => 'Basic', 'type' => 'earning', 'amount' => '28000.00'],
                ['name' => 'HRA', 'type' => 'earning', 'amount' => 2000],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $august->refresh();
        $this->assertSame('Promotion', $august->reason);
        $this->assertSame('Approved by the director', $august->notes);
        $this->assertSame(30000.0, $august->new_gross);
    }

    public function test_a_revision_paid_by_a_finalized_final_settlement_cannot_be_edited(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $this->finalizePayroll(2026, 8);
        $september = $this->setSalary($employee, 90000, '2026-09-01');
        app(EmployeeService::class)->exit($employee, [
            'exit_date' => '2026-09-20', 'last_working_date' => '2026-09-20', 'exit_type' => 'resignation',
        ]);
        $settlements = app(FinalSettlementService::class);
        $settlement = $settlements->finalize($settlements->prepare($employee->refresh()));

        $response = $this->actingAs($admin = $this->adminOf($company))->put("/employees/{$employee->id}/salary/{$september->id}", [
            'effective_date' => '2026-09-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 120000]],
        ]);

        $response->assertSessionHasErrors([
            'revision' => 'The final settlement for September 2026 is finalized and used this salary. This revision can no longer be changed.',
        ]);
        $this->assertSame(90000.0, $september->refresh()->new_gross);

        $settlements->markPaid($settlement);

        $this->actingAs($admin)->get("/employees/{$employee->id}/salary")
            ->assertInertia(fn (Assert $page) => $page
                ->where('revisions.0.is_locked', true)
                ->where('revisions.0.locked_reason', 'The final settlement for September 2026 is paid and used this salary. This revision can no longer be changed.')
                ->where('revisions.1.locked_reason', 'Payroll for August 2026 is finalized and used this salary. Reopen it before editing this revision.'));
    }

    public function test_a_new_revision_inside_a_finalized_payroll_period_is_refused(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $this->finalizePayroll(2026, 9);

        $refused = $this->actingAs($admin = $this->adminOf($company))->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2026-09-15',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 30000]],
        ]);
        $refused->assertSessionHasErrors([
            'effective_date' => 'Payroll for September 2026 is finalized and covers this date. Reopen it before adding a revision on this date.',
        ]);
        $allowed = $this->actingAs($admin)->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2026-10-01',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 30000]],
        ]);

        $allowed->assertSessionHasNoErrors();
        $this->assertSame(
            ['2026-01-01', '2026-10-01'],
            EmployeeSalaryRevision::query()->orderBy('effective_date')->pluck('effective_date')->map->toDateString()->all(),
        );
    }

    public function test_a_new_revision_moves_open_payrolls_it_affects_back_to_draft(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $payrolls = app(PayrollService::class);
        $august = $payrolls->calculate($payrolls->create(2026, 8));
        $september = $payrolls->calculate($payrolls->create(2026, 9));

        $response = $this->actingAs($this->adminOf($company))->post("/employees/{$employee->id}/salary", [
            'effective_date' => '2026-09-10',
            'components' => [['name' => 'Basic', 'type' => 'earning', 'amount' => 30000]],
        ]);

        $response->assertInertiaFlash('toast', [
            'type' => 'warning',
            'message' => 'Salary revision saved. The payroll for September 2026 was moved back to draft; recalculate it.',
        ]);
        $this->assertSame(PayrollStatus::Calculated, $august->refresh()->status);
        $this->assertSame(PayrollStatus::Draft, $september->refresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'payroll.reset_to_draft')->count());
    }

    public function test_a_payroll_moved_back_to_draft_cannot_be_adjusted_until_recalculated(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->calculate($payrolls->create(2026, 9));
        $adjustment = $payrolls->addAdjustment(PayrollItem::query()->sole(), PayrollBucket::Bonus, 500, 'Festival');

        $this->setSalary($employee, 52000, '2026-09-01');

        $this->assertSame(PayrollStatus::Draft, $payroll->refresh()->status);
        $this->assertNull($payroll->calculated_at);
        $this->assertPayrollError('Calculate the payroll before adjusting it.', fn () => $payrolls->addAdjustment(PayrollItem::query()->sole(), PayrollBucket::Bonus, 100, 'More'));
        $this->assertPayrollError('Calculate the payroll before adjusting it.', fn () => $payrolls->removeAdjustment($adjustment));
        $this->assertPayrollError('Calculate the payroll before continuing.', fn () => $payrolls->finalize($payroll->refresh()));

        $payrolls->calculate($payroll);
        $this->assertSame(PayrollStatus::Adjusted, $payroll->status);
        $payrolls->addAdjustment(PayrollItem::query()->sole(), PayrollBucket::Bonus, 100, 'More');
        $payrolls->finalize($payroll->refresh());

        $item = PayrollItem::query()->sole();
        $this->assertSame(PayrollStatus::Finalized, $payroll->refresh()->status);
        $this->assertSame(52000.0, $item->gross_salary);
        $this->assertSame(600.0, $item->bonus_amount);
    }

    public function test_a_salary_change_during_calculation_leaves_the_payroll_in_draft(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $this->createEmployee(['joining_date' => '2026-01-01'], 30000);
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->create(2026, 9);

        // The salary changes once the first item of this run has been written.
        $changed = false;
        PayrollItem::saved(function () use (&$changed, $employee): void {
            if (! $changed) {
                $changed = true;
                $this->setSalary($employee, 52000, '2026-09-01');
            }
        });

        $payrolls->calculate($payroll);

        $payroll->refresh();
        $this->assertTrue($changed);
        $this->assertSame(PayrollStatus::Draft, $payroll->status);
        $this->assertNull($payroll->calculation_started_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'payroll.calculation_discarded')->count());
        $this->assertPayrollError('Calculate the payroll before continuing.', fn () => $payrolls->finalize($payroll));

        $payrolls->calculate($payroll);

        $this->assertSame(PayrollStatus::Calculated, $payroll->refresh()->status);
        $this->assertSame(52000.0, PayrollItem::query()->where('employee_id', $employee->id)->sole()->gross_salary);
    }

    public function test_a_settlement_is_not_finalized_with_figures_from_an_older_salary(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        app(EmployeeService::class)->exit($employee, [
            'exit_date' => '2026-09-15', 'last_working_date' => '2026-09-15', 'exit_type' => 'resignation',
        ]);
        $settlements = app(FinalSettlementService::class);
        $draft = $settlements->prepare($employee->refresh());
        $this->setSalary($employee, 52000, '2026-09-01');

        try {
            $settlements->finalize($draft);
            $this->fail('A settlement was finalized with figures from the old salary.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('settlement', $exception->errors());
        }

        $this->assertSame('draft', $draft->refresh()->status);

        $finalized = $settlements->finalize($settlements->prepare($employee));

        $this->assertSame('finalized', $finalized->status);
        $this->assertSame(26000.0, $finalized->last_salary);
    }

    public function test_a_revision_added_before_an_existing_one_updates_its_previous_salary(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee(['joining_date' => '2026-01-01'], 26000);
        $november = $this->setSalary($employee, 30000, '2026-11-01');

        $this->setSalary($employee, 28000, '2026-10-15');

        $this->assertSame(28000.0, $november->refresh()->previous_gross);
    }

    private function assertPayrollError(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected the payroll error: {$message}");
        } catch (ValidationException $exception) {
            $this->assertSame(['payroll' => [$message]], $exception->errors());
        }
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
