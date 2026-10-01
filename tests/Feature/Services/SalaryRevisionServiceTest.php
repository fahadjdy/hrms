<?php

namespace Tests\Feature\Services;

use App\Models\AuditLog;
use App\Models\EmployeeSalaryComponent;
use App\Models\EmployeeSalaryRevision;
use App\Services\SalaryRevisionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class SalaryRevisionServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_a_new_revision_leaves_the_earlier_revision_and_its_components_untouched(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $first = $this->setSalary($employee, 30000, '2026-01-01', [['name' => 'HRA', 'type' => 'earning', 'amount' => 5000]]);

        $this->setSalary($employee, 40000, '2026-07-01', reason: 'Annual Increment');

        $first->refresh();
        $this->assertSame(2, EmployeeSalaryRevision::query()->count());
        $this->assertSame(35000.0, $first->new_gross);
        $this->assertSame('2026-01-01', $first->effective_date->toDateString());
        $this->assertSame(
            ['basic' => 30000.0, 'hra' => 5000.0],
            $first->components()->pluck('amount', 'code')->all(),
        );
    }

    public function test_a_revision_records_the_previous_salary_and_components(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->setSalary($employee, 30000, '2026-01-01', [['name' => 'HRA', 'type' => 'earning', 'amount' => 5000]]);

        $revision = $this->setSalary($employee, 40000, '2026-07-01', reason: 'Promotion');

        $this->assertSame(35000.0, $revision->previous_gross);
        $this->assertSame(40000.0, $revision->new_gross);
        $this->assertSame('Promotion', $revision->reason);
        $this->assertSame(
            [
                ['code' => 'basic', 'name' => 'Basic', 'type' => 'earning', 'amount' => 30000],
                ['code' => 'hra', 'name' => 'HRA', 'type' => 'earning', 'amount' => 5000],
            ],
            $revision->refresh()->previous_components,
        );
    }

    public function test_the_first_revision_has_no_previous_salary(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $revision = $this->setSalary($employee, 30000, '2026-01-01');

        $this->assertSame(0.0, $revision->refresh()->previous_gross);
        $this->assertNull($revision->previous_components);
    }

    public function test_rejects_a_second_revision_on_the_same_effective_date(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->setSalary($employee, 30000, '2026-01-01');

        try {
            $this->setSalary($employee, 32000, '2026-01-01');
            $this->fail('A second revision on the same date was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'A salary revision already exists for this date. Choose a different effective date.',
                $exception->errors()['effective_date'][0],
            );
        }

        $this->assertSame(30000.0, EmployeeSalaryRevision::query()->sole()->new_gross);
    }

    public function test_the_revision_in_effect_is_chosen_by_effective_date(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->setSalary($employee, 30000, '2026-01-01');
        $this->setSalary($employee, 35000, '2026-07-01');
        $this->setSalary($employee, 40000, '2026-10-01');
        $service = app(SalaryRevisionService::class);

        $this->assertNull($service->revisionOn($employee, CarbonImmutable::parse('2025-12-31')));
        $this->assertSame(30000.0, $service->revisionOn($employee, CarbonImmutable::parse('2026-06-30'))->new_gross);
        $this->assertSame(35000.0, $service->revisionOn($employee, CarbonImmutable::parse('2026-07-01'))->new_gross);
        $this->assertSame(35000.0, $service->revisionOn($employee, CarbonImmutable::parse('2026-09-30'))->new_gross);
        $this->assertSame(40000.0, $service->revisionOn($employee, CarbonImmutable::parse('2026-10-01'))->new_gross);
    }

    public function test_a_revision_added_out_of_order_takes_its_place_by_effective_date(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->setSalary($employee, 40000, '2026-07-01');

        $earlier = $this->setSalary($employee, 30000, '2026-01-01');

        $service = app(SalaryRevisionService::class);
        $this->assertSame(0.0, $earlier->previous_gross);
        $this->assertSame(30000.0, $service->revisionOn($employee, CarbonImmutable::parse('2026-03-01'))->new_gross);
        $this->assertSame(40000.0, $service->revisionOn($employee, CarbonImmutable::parse('2026-08-01'))->new_gross);
    }

    public function test_deduction_components_do_not_count_towards_the_gross(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $revision = $this->setSalary($employee, 30000, '2026-01-01', [
            ['name' => 'Professional Tax', 'type' => 'deduction', 'amount' => 200],
            ['name' => 'Unused Allowance', 'type' => 'earning', 'amount' => 0],
        ]);

        $this->assertSame(30000.0, $revision->new_gross);
        $this->assertSame(
            ['basic' => 'earning', 'professional_tax' => 'deduction'],
            EmployeeSalaryComponent::query()->orderBy('sort_order')->pluck('type', 'code')->all(),
        );
    }

    public function test_rejects_a_salary_without_any_earning(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        try {
            app(SalaryRevisionService::class)->revise($employee, [
                'effective_date' => '2026-01-01',
                'components' => [['name' => 'Professional Tax', 'type' => 'deduction', 'amount' => 200]],
            ]);
            $this->fail('A salary with no earning was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Add at least one earning with an amount greater than zero.',
                $exception->errors()['components'][0],
            );
        }

        $this->assertSame(0, EmployeeSalaryRevision::query()->count());
    }

    public function test_salary_changes_are_audited(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $this->setSalary($employee, 30000, '2026-01-01');
        $this->setSalary($employee, 35000, '2026-07-01', reason: 'Annual Increment');

        $created = AuditLog::query()->where('action', 'salary.created')->sole();
        $revised = AuditLog::query()->where('action', 'salary.revised')->sole();
        $this->assertSame($employee->id, $created->employee_id);
        $this->assertNull($created->old_values);
        $this->assertEquals(30000, $revised->old_values['gross']);
        $this->assertEquals(35000, $revised->new_values['gross']);
        $this->assertSame('Annual Increment', $revised->new_values['reason']);
    }
}
