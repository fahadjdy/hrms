<?php

namespace Tests\Feature\Http;

use App\Enums\BorrowStatus;
use App\Enums\BorrowTransactionType;
use App\Models\AuditLog;
use App\Models\BorrowTransaction;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Services\BorrowCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class BorrowEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_storing_a_new_borrow_given_directly_makes_it_active_and_outstanding(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post('/borrows', $this->payload($employee->id));

        $borrow = EmployeeBorrow::query()->sole();
        $response->assertRedirect(route('borrows.show', $borrow));
        $this->assertSame(EmployeeBorrow::KIND_NEW, $borrow->kind);
        $this->assertSame(BorrowStatus::Active, $borrow->status);
        $this->assertSame(15000.0, $borrow->outstanding_amount);
        $this->assertSame(3000.0, $borrow->monthly_deduction);
        $this->assertSame('2026-11-01', $borrow->deduction_start_month->toDateString());
        $this->assertSame($admin->id, $borrow->created_by);
        $this->assertSame(BorrowTransactionType::Disbursement, BorrowTransaction::query()->sole()->type);
        $this->assertTrue(AuditLog::query()->where('action', 'borrow.created')->where('employee_id', $employee->id)->exists());
    }

    public function test_storing_an_existing_borrow_starts_from_its_outstanding_balance(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/borrows', $this->payload($employee->id, [
            'kind' => 'existing',
            'amount' => 20000,
            'opening_balance' => 12000,
            'borrow_date' => '2026-03-10',
            'source_reference' => 'Previous employer',
        ]));

        $response->assertSessionHasNoErrors();
        $borrow = EmployeeBorrow::query()->sole();
        $this->assertSame(EmployeeBorrow::KIND_EXISTING, $borrow->kind);
        $this->assertSame(20000.0, $borrow->amount);
        $this->assertSame(12000.0, $borrow->opening_balance);
        $this->assertSame(12000.0, $borrow->outstanding_amount);
        $this->assertSame('Previous employer', $borrow->source_reference);
        $this->assertSame(BorrowTransactionType::Opening, BorrowTransaction::query()->sole()->type);
    }

    public function test_storing_a_borrow_to_be_paid_with_salary_waits_for_that_payroll(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/borrows', $this->payload($employee->id, [
            'disbursement_method' => 'with_salary',
            'disburse_period' => '2026-10',
        ]));

        $response->assertSessionHasNoErrors();
        $borrow = EmployeeBorrow::query()->sole();
        $this->assertSame(BorrowStatus::PendingDisbursement, $borrow->status);
        $this->assertSame('2026-10-01', $borrow->disburse_period->toDateString());
        $this->assertNull($borrow->disbursed_at);
        $this->assertSame(0, BorrowTransaction::query()->count());
    }

    public function test_borrow_paid_with_salary_requires_the_salary_month(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/borrows', $this->payload($employee->id, [
            'disbursement_method' => 'with_salary',
        ]));

        $response->assertSessionHasErrors(['disburse_period' => 'Choose the salary month this borrow is paid with.']);
        $this->assertSame(0, EmployeeBorrow::query()->count());
    }

    public function test_outstanding_balance_cannot_be_more_than_the_borrowed_amount(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();

        $response = $this->actingAs($this->adminOf($company))->post('/borrows', $this->payload($employee->id, [
            'kind' => 'existing', 'amount' => 10000, 'opening_balance' => 12000,
        ]));

        $response->assertSessionHasErrors([
            'opening_balance' => 'The outstanding balance cannot be more than the original borrow amount.',
        ]);
        $this->assertSame(0, EmployeeBorrow::query()->count());
    }

    public function test_a_borrow_needs_a_monthly_deduction_or_a_number_of_installments(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $this->actingAs($this->adminOf($company));

        $this->post('/borrows', $this->payload($employee->id, ['monthly_deduction' => null]))
            ->assertSessionHasErrors(['monthly_deduction' => 'Enter a monthly deduction or the number of installments.']);

        $this->post('/borrows', $this->payload($employee->id, ['monthly_deduction' => null, 'installments_count' => 4]))
            ->assertSessionHasNoErrors();

        // 15,000 over 4 installments.
        $this->assertSame(3750.0, EmployeeBorrow::query()->sole()->monthly_deduction);
    }

    public function test_manual_recovery_reduces_the_outstanding_balance(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $borrow = $this->borrowFor($employee, 5000);
        $admin = $this->adminOf($company);

        $response = $this->actingAs($admin)->post("/borrows/{$borrow->id}/recoveries", [
            'amount' => 1200.50, 'date' => '2026-09-30', 'notes' => 'Cash returned',
        ]);

        $response->assertSessionHasNoErrors();
        $borrow->refresh();
        $this->assertSame(3799.5, $borrow->outstanding_amount);
        $this->assertSame(1200.5, $borrow->recovered_amount);
        $transaction = BorrowTransaction::query()->where('type', BorrowTransactionType::Recovery->value)->sole();
        $this->assertSame(3799.5, $transaction->balance_after);
        $this->assertSame('Cash returned', $transaction->notes);
        $this->assertSame($admin->id, $transaction->user_id);
        $this->assertNull($transaction->payroll_item_id);
    }

    public function test_recovery_of_more_than_the_outstanding_balance_is_rejected(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $borrow = $this->borrowFor($employee, 5000);

        $response = $this->actingAs($this->adminOf($company))->post("/borrows/{$borrow->id}/recoveries", [
            'amount' => 5000.01, 'date' => '2026-09-30',
        ]);

        $response->assertSessionHasErrors([
            'amount' => 'The recovery cannot be more than the outstanding balance of 5000.',
        ]);
        $this->assertSame(5000.0, $borrow->refresh()->outstanding_amount);
        $this->assertSame(0, BorrowTransaction::query()->where('type', BorrowTransactionType::Recovery->value)->count());
    }

    public function test_recovery_cannot_be_dated_in_the_future(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $borrow = $this->borrowFor($employee, 5000);

        $response = $this->actingAs($this->adminOf($company))->post("/borrows/{$borrow->id}/recoveries", [
            'amount' => 1000, 'date' => '2026-10-02',
        ]);

        $response->assertSessionHasErrors('date');
        $this->assertSame(5000.0, $borrow->refresh()->outstanding_amount);
    }

    public function test_borrow_without_recoveries_can_be_cancelled(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $borrow = $this->borrowFor($employee, 5000);

        $response = $this->actingAs($this->adminOf($company))->delete("/borrows/{$borrow->id}");

        $response->assertRedirect(route('borrows.index'));
        $borrow->refresh();
        $this->assertSame(BorrowStatus::Cancelled, $borrow->status);
        $this->assertSame(0.0, $borrow->outstanding_amount);
        $this->assertSame(0, $borrow->installments()->where('status', 'pending')->count());
        $this->assertTrue(AuditLog::query()->where('action', 'borrow.cancelled')->exists());
    }

    public function test_borrow_with_recoveries_cannot_be_cancelled(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $borrow = $this->borrowFor($employee, 5000);
        app(BorrowCalculationService::class)->recover($borrow, 1000, CarbonImmutable::parse('2026-09-30'));

        $response = $this->actingAs($this->adminOf($company))->delete("/borrows/{$borrow->id}");

        $response->assertSessionHasErrors(['borrow' => 'This borrow already has recoveries, so it cannot be cancelled.']);
        $this->assertSame(BorrowStatus::Active, $borrow->refresh()->status);
        $this->assertSame(4000.0, $borrow->outstanding_amount);
    }

    public function test_viewer_role_can_see_borrows_but_not_record_them(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $borrow = $this->borrowFor($employee, 5000);
        $this->actingAs($this->userWithRole($company, 'viewer'));

        $this->get('/borrows')->assertOk();
        $this->get('/borrows/create')->assertForbidden();
        $this->post('/borrows', $this->payload($employee->id))->assertForbidden();
        $this->post("/borrows/{$borrow->id}/recoveries", ['amount' => 100, 'date' => '2026-09-30'])->assertForbidden();
        $this->delete("/borrows/{$borrow->id}")->assertForbidden();

        $this->assertSame(1, EmployeeBorrow::query()->count());
        $this->assertSame(5000.0, $borrow->refresh()->outstanding_amount);
    }

    public function test_borrow_dashboard_totals_keep_borrowed_recovered_and_outstanding_apart(): void
    {
        $company = $this->prepareCompany();
        $first = $this->createEmployee();
        $second = $this->createEmployee();
        $service = app(BorrowCalculationService::class);
        $recovered = $this->borrowFor($first, 20000);
        $service->recover($recovered, 15000, CarbonImmutable::parse('2026-09-20'));
        $this->borrowFor($first, 15000);
        $service->recover($this->borrowFor($second, 4000), 4000, CarbonImmutable::parse('2026-09-25'));
        $service->create($second, [
            'amount' => 9000, 'borrow_date' => '2026-09-28', 'monthly_deduction' => 3000,
            'disbursement_method' => 'with_salary', 'disburse_period' => '2026-10-01',
        ]);

        $response = $this->actingAs($this->adminOf($company))->get('/borrows');

        // The borrow still waiting to be paid out with salary is not in any total.
        $response->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_borrowed', 39000)
            ->where('stats.total_recovered', 19000)
            ->where('stats.total_outstanding', 20000)
            ->where('stats.employees_with_borrow', 1)
            ->where('stats.active', 2)
            ->where('stats.fully_recovered', 1)
            ->where('stats.pending_disbursement', 1)
            ->has('borrows.data', 4)
            ->has('highestOutstanding', 1)
            ->where('highestOutstanding.0.employee_id', $first->id)
            ->where('highestOutstanding.0.outstanding', 20000)
            ->where('highestOutstanding.0.borrows', 2));
    }

    public function test_borrow_page_shows_the_schedule_and_the_ledger(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee();
        $borrow = $this->borrowFor($employee, 5000);
        app(BorrowCalculationService::class)->recover($borrow, 1000, CarbonImmutable::parse('2026-09-30'));

        $response = $this->actingAs($this->adminOf($company))->get("/borrows/{$borrow->id}");

        $response->assertInertia(fn (Assert $page) => $page
            ->where('borrow.reference_no', $borrow->reference_no)
            ->where('borrow.amount', 5000)
            ->where('borrow.recovered', 1000)
            ->where('borrow.outstanding', 4000)
            ->where('borrow.can_cancel', false)
            ->where('borrow.can_recover', true)
            ->has('transactions', 2)
            ->where('transactions.0.type', 'disbursement')
            ->where('transactions.1.type', 'recovery')
            ->where('transactions.1.balance_after', 4000)
            ->has('installments', 5)
            ->where('installments.0.status', 'paid'));
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');

        return $this->useCompany($this->createCompany());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(int $employeeId, array $overrides = []): array
    {
        return [
            'employee_id' => $employeeId,
            'kind' => 'new',
            'amount' => 15000,
            'borrow_date' => '2026-10-01',
            'reason' => 'Medical expense',
            'monthly_deduction' => 3000,
            'deduction_start_month' => '2026-11',
            'disbursement_method' => 'direct',
            ...$overrides,
        ];
    }

    private function borrowFor(Employee $employee, float $amount): EmployeeBorrow
    {
        return app(BorrowCalculationService::class)->create($employee, [
            'amount' => $amount,
            'borrow_date' => '2026-08-01',
            'monthly_deduction' => 1000,
            'deduction_start_month' => '2026-11-01',
        ]);
    }
}
