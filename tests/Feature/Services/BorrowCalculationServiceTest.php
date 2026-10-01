<?php

namespace Tests\Feature\Services;

use App\Enums\BorrowStatus;
use App\Enums\BorrowTransactionType;
use App\Models\BorrowInstallment;
use App\Models\BorrowTransaction;
use App\Models\EmployeeBorrow;
use App\Services\BorrowCalculationService;
use App\Services\EmployeeService;
use App\Support\PayrollPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class BorrowCalculationServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_employee_created_with_an_existing_borrow_starts_with_that_outstanding_balance(): void
    {
        $this->useCompany($this->createCompany());

        $employee = app(EmployeeService::class)->create([
            'employee_code' => 'EMP-0001',
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'joining_date' => '2026-09-01',
            'existing_borrow' => [
                'amount' => 20000,
                'opening_balance' => 20000,
                'borrow_date' => '2026-06-15',
                'monthly_deduction' => 5000,
                'deduction_start_month' => '2026-09-01',
                'reason' => 'Advance from previous employer',
            ],
        ]);

        $borrow = EmployeeBorrow::query()->sole();
        $this->assertSame($employee->id, $borrow->employee_id);
        $this->assertSame(EmployeeBorrow::KIND_EXISTING, $borrow->kind);
        $this->assertSame(BorrowStatus::Active, $borrow->status);
        $this->assertSame(20000.0, $borrow->outstanding_amount);
        $this->assertSame(BorrowTransactionType::Opening, BorrowTransaction::query()->sole()->type);
        $this->assertSame(4, BorrowInstallment::query()->count());
    }

    public function test_new_borrow_is_recorded_with_a_monthly_installment_schedule(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $borrow = $this->service()->create($employee, [
            'amount' => 15000,
            'borrow_date' => '2026-10-15',
            'monthly_deduction' => 4000,
            'deduction_start_month' => '2026-11-01',
        ]);

        $this->assertSame(EmployeeBorrow::KIND_NEW, $borrow->kind);
        $this->assertSame(15000.0, $borrow->outstanding_amount);
        $this->assertSame(BorrowTransactionType::Disbursement, BorrowTransaction::query()->sole()->type);
        $this->assertSame(
            [['2026-11-01', 4000.0], ['2026-12-01', 4000.0], ['2027-01-01', 4000.0], ['2027-02-01', 3000.0]],
            $borrow->installments->map(fn (BorrowInstallment $i): array => [$i->due_month->toDateString(), $i->amount])->all(),
        );
    }

    public function test_multiple_borrows_keep_separate_balances(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $first = $this->service()->create($employee, ['amount' => 20000, 'borrow_date' => '2026-06-01', 'monthly_deduction' => 5000]);
        $second = $this->service()->create($employee, ['amount' => 15000, 'borrow_date' => '2026-10-15', 'monthly_deduction' => 3000]);

        $this->service()->recover($first, 15000, CarbonImmutable::parse('2026-10-20'));

        $this->assertSame(5000.0, $first->refresh()->outstanding_amount);
        $this->assertSame(15000.0, $second->refresh()->outstanding_amount);
        $this->assertSame(20000.0, $this->service()->outstandingFor($employee));
    }

    public function test_recovery_reduces_the_remaining_balance_and_reschedules_the_installments(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $borrow = $this->service()->create($employee, [
            'amount' => 10000,
            'borrow_date' => '2026-09-10',
            'monthly_deduction' => 2500,
            'deduction_start_month' => '2026-10-01',
        ]);

        $transaction = $this->service()->recover($borrow, 2500, CarbonImmutable::parse('2026-10-20'), installmentMonth: CarbonImmutable::parse('2026-10-01'));

        $borrow->refresh();
        $this->assertSame(2500.0, $borrow->recovered_amount);
        $this->assertSame(7500.0, $borrow->outstanding_amount);
        $this->assertSame(7500.0, $transaction->balance_after);
        $this->assertSame(
            ['2026-11-01', '2026-12-01', '2027-01-01'],
            $borrow->installments()->where('status', 'pending')->get()->map(fn (BorrowInstallment $i): string => $i->due_month->toDateString())->all(),
        );
    }

    public function test_recovering_the_full_balance_marks_the_borrow_as_recovered(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $borrow = $this->service()->create($employee, ['amount' => 5000, 'borrow_date' => '2026-09-10', 'monthly_deduction' => 5000]);

        $this->service()->recover($borrow, 5000, CarbonImmutable::parse('2026-10-20'));

        $borrow->refresh();
        $this->assertSame(BorrowStatus::Recovered, $borrow->status);
        $this->assertSame(0.0, $borrow->outstanding_amount);
        $this->assertSame(0, $borrow->installments()->where('status', 'pending')->count());
    }

    public function test_rejects_a_recovery_that_is_more_than_the_outstanding_balance(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $borrow = $this->service()->create($employee, ['amount' => 5000, 'borrow_date' => '2026-09-10', 'monthly_deduction' => 1000]);

        try {
            $this->service()->recover($borrow, 5000.01, CarbonImmutable::parse('2026-10-20'));
            $this->fail('The over-recovery was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('more than the outstanding balance', $exception->errors()['amount'][0]);
        }

        $this->assertSame(5000.0, $borrow->refresh()->outstanding_amount);
        $this->assertSame(1, BorrowTransaction::query()->count());
    }

    public function test_scheduled_recovery_for_a_period_is_capped_at_the_outstanding_balance(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $borrow = $this->service()->create($employee, [
            'amount' => 6000,
            'borrow_date' => '2026-08-10',
            'monthly_deduction' => 5000,
            'deduction_start_month' => '2026-09-01',
        ]);
        $this->service()->recover($borrow, 5000, CarbonImmutable::parse('2026-09-30'));

        $due = $this->service()->dueForPeriod($employee, PayrollPeriod::forMonth(2026, 10));

        $this->assertSame(1000.0, $due[0]['amount']);
    }

    public function test_recovery_is_not_scheduled_before_the_deduction_start_month(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();
        $this->service()->create($employee, [
            'amount' => 6000,
            'borrow_date' => '2026-08-10',
            'monthly_deduction' => 2000,
            'deduction_start_month' => '2026-11-01',
        ]);

        $this->assertSame([], $this->service()->dueForPeriod($employee, PayrollPeriod::forMonth(2026, 10)));
        $this->assertCount(1, $this->service()->dueForPeriod($employee, PayrollPeriod::forMonth(2026, 11)));
    }

    public function test_borrow_to_be_given_with_salary_is_not_outstanding_until_it_is_paid_out(): void
    {
        $this->useCompany($this->createCompany());
        $employee = $this->createEmployee();

        $borrow = $this->service()->create($employee, [
            'amount' => 10000,
            'borrow_date' => '2026-09-20',
            'monthly_deduction' => 2000,
            'disbursement_method' => 'with_salary',
            'disburse_period' => '2026-09-01',
        ]);

        $this->assertSame(BorrowStatus::PendingDisbursement, $borrow->status);
        $this->assertSame(0.0, $this->service()->outstandingFor($employee));
        $this->assertSame(0, BorrowTransaction::query()->count());
        $this->assertSame(10000.0, $this->service()->givenWithPeriod($employee, PayrollPeriod::forMonth(2026, 9))[0]['amount']);
    }

    private function service(): BorrowCalculationService
    {
        return app(BorrowCalculationService::class);
    }
}
