<?php

namespace Tests\Feature\Http;

use App\Enums\BorrowStatus;
use App\Enums\EmployeeStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\FinalSettlement;
use App\Services\BorrowCalculationService;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * September 2026 has 26 working days with Sunday off. An employee on 26,000
 * whose last working day is 15 September earned 13 days: 13,000.
 */
class SettlementEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_opening_the_settlement_of_a_past_employee_prepares_a_draft(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->employeeWhoLeft();

        $response = $this->actingAs($this->adminOf($company))->get("/final-settlements/{$employee->id}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('employee.last_working_date', '2026-09-15')
            ->where('settlement.status', 'draft')
            ->where('settlement.is_locked', false)
            ->where('settlement.last_salary', 13000)
            ->where('settlement.outstanding_borrow', 6000)
            ->where('settlement.net_amount', 7000)
            ->where('settlement.period_start', '2026-09-01')
            ->where('settlement.period_end', '2026-09-15')
            ->where('settlement.salary_already_paid', false)
            ->has('lines', 3)
            ->where('lines.2.note', 'Full outstanding balance recovered in the final settlement'));
        $this->assertSame(1, FinalSettlement::query()->count());
    }

    public function test_settlement_page_is_not_found_for_a_current_employee(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);

        $response = $this->actingAs($this->adminOf($company))->get("/final-settlements/{$employee->id}");

        $response->assertNotFound();
        $this->assertSame(0, FinalSettlement::query()->count());
    }

    public function test_adjustment_needs_a_reason_unless_it_is_zero(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->employeeWhoLeft();
        $this->actingAs($this->adminOf($company));
        $this->get("/final-settlements/{$employee->id}");

        $this->put("/final-settlements/{$employee->id}", ['adjustment_amount' => 500])
            ->assertSessionHasErrors(['adjustment_reason' => 'Give a reason for the adjustment.']);
        $this->put("/final-settlements/{$employee->id}", ['adjustment_amount' => 0])
            ->assertSessionHasNoErrors();
        $this->put("/final-settlements/{$employee->id}", ['adjustment_amount' => -500, 'adjustment_reason' => 'Unreturned laptop charger'])
            ->assertSessionHasNoErrors();

        $settlement = FinalSettlement::query()->sole();
        $this->assertSame(-500.0, $settlement->adjustment_amount);
        $this->assertSame(6500.0, $settlement->net_amount);
    }

    public function test_finalizing_and_paying_the_settlement_through_the_endpoints(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->employeeWhoLeft();
        $admin = $this->adminOf($company);
        $this->actingAs($admin);
        $this->get("/final-settlements/{$employee->id}");

        $this->post("/final-settlements/{$employee->id}/finalize")->assertSessionHasNoErrors();

        $settlement = FinalSettlement::query()->sole();
        $this->assertSame(FinalSettlement::STATUS_FINALIZED, $settlement->status);
        $this->assertSame($admin->id, $settlement->finalized_by);
        $this->assertSame(BorrowStatus::Recovered, EmployeeBorrow::query()->sole()->status);

        $this->put("/final-settlements/{$employee->id}", ['adjustment_amount' => 100, 'adjustment_reason' => 'Late change'])
            ->assertSessionHasErrors(['settlement' => 'This final settlement is finalized and can no longer be changed.']);
        $this->post("/final-settlements/{$employee->id}")
            ->assertSessionHasErrors(['settlement' => 'This final settlement is finalized and can no longer be recalculated.']);

        $this->post("/final-settlements/{$employee->id}/paid")->assertSessionHasNoErrors();
        $this->assertSame(FinalSettlement::STATUS_PAID, $settlement->refresh()->status);
        $this->assertSame(7000.0, $settlement->net_amount);

        $this->get("/final-settlements/{$employee->id}")->assertInertia(fn (Assert $page) => $page
            ->where('settlement.status', 'paid')
            ->where('settlement.is_locked', true)
            ->where('settlement.net_amount', 7000));
        $this->assertTrue(AuditLog::query()->where('action', 'settlement.finalized')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'settlement.paid')->exists());
    }

    public function test_settlement_list_shows_past_employees_with_their_status(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->employeeWhoLeft();
        $this->createEmployee([], 26000);
        $this->actingAs($this->adminOf($company));

        $this->get('/final-settlements')->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $employee->id)
            ->where('employees.data.0.outstanding_borrow', 6000)
            ->where('employees.data.0.settlement', null));
        $this->get('/final-settlements?status=draft')->assertInertia(fn (Assert $page) => $page->has('employees.data', 0));

        $this->get("/final-settlements/{$employee->id}");
        $this->get('/final-settlements?status=draft')->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.settlement.status', 'draft')
            ->where('employees.data.0.settlement.net_amount', 7000));
    }

    public function test_employee_with_a_finalized_settlement_cannot_be_reinstated(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->employeeWhoLeft();
        $this->actingAs($this->adminOf($company));
        $this->get("/final-settlements/{$employee->id}");
        $this->post("/final-settlements/{$employee->id}/finalize");

        $response = $this->delete("/employees/{$employee->id}/exit");

        $response->assertSessionHasErrors([
            'employee' => 'The final settlement is already finalized, so this employee cannot be reinstated.',
        ]);
        $this->assertSame(EmployeeStatus::Past, $employee->refresh()->status);
    }

    public function test_reinstating_an_employee_discards_the_draft_settlement(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->employeeWhoLeft();
        $this->actingAs($this->adminOf($company));
        $this->get("/final-settlements/{$employee->id}");

        $response = $this->delete("/employees/{$employee->id}/exit");

        $response->assertRedirect(route('employees.show', $employee));
        $this->assertSame(EmployeeStatus::Active, $employee->refresh()->status);
        $this->assertSame(0, FinalSettlement::query()->count());
    }

    public function test_viewer_role_cannot_open_final_settlements(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->employeeWhoLeft();
        $this->actingAs($this->userWithRole($company, 'viewer'));

        $this->get('/final-settlements')->assertForbidden();
        $this->get("/final-settlements/{$employee->id}")->assertForbidden();
        $this->post("/final-settlements/{$employee->id}/finalize")->assertForbidden();

        $this->assertSame(0, FinalSettlement::query()->count());
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);

        return $company;
    }

    private function employeeWhoLeft(): Employee
    {
        $employee = $this->createEmployee([], 26000);
        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-12-01',
        ]);
        app(EmployeeService::class)->exit($employee, [
            'exit_date' => '2026-09-15', 'last_working_date' => '2026-09-15', 'exit_type' => 'resignation',
        ]);

        return $employee;
    }
}
