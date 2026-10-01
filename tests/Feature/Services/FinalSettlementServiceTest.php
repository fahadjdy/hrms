<?php

namespace Tests\Feature\Services;

use App\Enums\BorrowStatus;
use App\Enums\BorrowTransactionType;
use App\Enums\OvertimeStatus;
use App\Jobs\GenerateSalarySlips;
use App\Models\AuditLog;
use App\Models\BorrowTransaction;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\FinalSettlement;
use App\Models\Overtime;
use App\Models\SalaryBonus;
use App\Models\SalaryDeduction;
use App\Services\BorrowCalculationService;
use App\Services\EmployeeService;
use App\Services\FinalSettlementService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * September 2026 has 26 working days with Sunday off, so a salary of 26,000
 * is 1,000 per working day. An employee whose last working day is
 * 15 September worked 13 of them.
 */
class FinalSettlementServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_settlement_prorates_the_last_salary_and_deducts_the_outstanding_borrow_in_full(): void
    {
        $employee = $this->employeeWhoLeftMidSeptember();

        $settlement = $this->service()->prepare($employee);

        $this->assertSame('2026-09-01', $settlement->period_start->toDateString());
        $this->assertSame('2026-09-15', $settlement->period_end->toDateString());
        $this->assertSame(13000.0, $settlement->last_salary);
        $this->assertSame(1500.0, $settlement->overtime_amount);
        $this->assertSame(1000.0, $settlement->bonus_amount);
        $this->assertSame(0.0, $settlement->unpaid_leave_deduction);
        $this->assertSame(6000.0, $settlement->outstanding_borrow);
        $this->assertSame(500.0, $settlement->other_deductions);
        // 13,000 + 1,500 + 1,000 - 6,000 - 500.
        $this->assertSame(9000.0, $settlement->net_amount);
        $this->assertSame(FinalSettlement::STATUS_DRAFT, $settlement->status);
    }

    public function test_unpaid_leave_in_the_final_period_is_deducted(): void
    {
        $employee = $this->employeeWhoLeftMidSeptember();
        $this->markAttendance($employee, '2026-09-03', 'unpaid_leave');

        $settlement = $this->service()->prepare($employee);

        $this->assertSame(1000.0, $settlement->unpaid_leave_deduction);
        $this->assertSame(8000.0, $settlement->net_amount);
    }

    public function test_manual_adjustment_changes_the_net_amount_and_is_audited(): void
    {
        $employee = $this->employeeWhoLeftMidSeptember();
        $settlement = $this->service()->prepare($employee);

        $this->service()->adjust($settlement, 750, 'Notice period pay', 'Agreed with HR');

        $settlement->refresh();
        $this->assertSame(750.0, $settlement->adjustment_amount);
        $this->assertSame('Notice period pay', $settlement->adjustment_reason);
        $this->assertSame(9750.0, $settlement->net_amount);
        $this->assertTrue(AuditLog::query()->where('action', 'settlement.adjusted')->where('employee_id', $employee->id)->exists());
    }

    public function test_recalculating_a_draft_keeps_the_manual_adjustment(): void
    {
        $employee = $this->employeeWhoLeftMidSeptember();
        $this->service()->adjust($this->service()->prepare($employee), -200, 'Unreturned ID card');

        $settlement = $this->service()->prepare($employee);

        $this->assertSame(-200.0, $settlement->adjustment_amount);
        $this->assertSame(8800.0, $settlement->net_amount);
        $this->assertSame(1, FinalSettlement::query()->count());
    }

    public function test_finalizing_recovers_the_borrow_through_the_ledger_and_locks_the_settlement(): void
    {
        $employee = $this->employeeWhoLeftMidSeptember();
        $settlement = $this->service()->prepare($employee);

        $this->service()->finalize($settlement);

        $settlement->refresh();
        $this->assertSame(FinalSettlement::STATUS_FINALIZED, $settlement->status);
        $this->assertNotNull($settlement->finalized_at);

        $borrow = EmployeeBorrow::query()->sole();
        $this->assertSame(BorrowStatus::Recovered, $borrow->status);
        $this->assertSame(0.0, $borrow->outstanding_amount);
        $this->assertSame(6000.0, $borrow->recovered_amount);
        $transaction = BorrowTransaction::query()->latest('id')->first();
        $this->assertSame(BorrowTransactionType::Settlement, $transaction->type);
        $this->assertSame(6000.0, $transaction->amount);

        $this->assertSame(OvertimeStatus::Paid, Overtime::query()->sole()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'settlement.finalized')->where('employee_id', $employee->id)->exists());
    }

    public function test_finalized_settlement_cannot_be_adjusted_or_recalculated(): void
    {
        $employee = $this->employeeWhoLeftMidSeptember();
        $settlement = $this->service()->prepare($employee);
        $this->service()->finalize($settlement);

        $attempts = [
            'adjust' => [fn () => $this->service()->adjust($settlement, 100, 'Late change'), 'This final settlement is finalized and can no longer be changed.'],
            'finalize again' => [fn () => $this->service()->finalize($settlement), 'This final settlement is finalized and can no longer be changed.'],
            'recalculate' => [fn () => $this->service()->prepare($employee), 'This final settlement is finalized and can no longer be recalculated.'],
        ];

        foreach ($attempts as $name => [$attempt, $message]) {
            try {
                $attempt();
                $this->fail("A finalized settlement allowed: {$name}.");
            } catch (ValidationException $exception) {
                $this->assertSame($message, $exception->errors()['settlement'][0]);
            }
        }

        $this->assertSame(9000.0, $settlement->refresh()->net_amount);
        $this->assertSame(1, BorrowTransaction::query()->where('type', BorrowTransactionType::Settlement->value)->count());
    }

    public function test_settlement_is_marked_paid_only_after_it_is_finalized(): void
    {
        $employee = $this->employeeWhoLeftMidSeptember();
        $settlement = $this->service()->prepare($employee);

        try {
            $this->service()->markPaid($settlement);
            $this->fail('A draft settlement was marked as paid.');
        } catch (ValidationException $exception) {
            $this->assertSame('Finalize the settlement before marking it as paid.', $exception->errors()['settlement'][0]);
        }

        $this->service()->finalize($settlement);
        $this->service()->markPaid($settlement);

        $settlement->refresh();
        $this->assertSame(FinalSettlement::STATUS_PAID, $settlement->status);
        $this->assertNotNull($settlement->paid_at);
    }

    public function test_settlement_is_refused_for_an_employee_who_has_not_left(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);

        try {
            $this->service()->prepare($employee);
            $this->fail('A settlement was prepared for a current employee.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'A final settlement is prepared after the employee has left the company.',
                $exception->errors()['employee'][0],
            );
        }

        $this->assertSame(0, FinalSettlement::query()->count());
    }

    public function test_salary_already_paid_by_a_finalized_payroll_is_not_paid_again(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-09-01',
        ]);
        $payrolls = app(PayrollService::class);
        $payrolls->finalize($payrolls->calculate($payrolls->create(2026, 9)));
        app(EmployeeService::class)->exit($employee, [
            'exit_date' => '2026-09-30', 'last_working_date' => '2026-09-30', 'exit_type' => 'resignation',
        ]);

        $settlement = $this->service()->prepare($employee);

        // September's salary went out with the payroll, which also recovered 1,000.
        $this->assertSame(0.0, $settlement->last_salary);
        $this->assertSame(5000.0, $settlement->outstanding_borrow);
        $this->assertSame(-5000.0, $settlement->net_amount);
        $this->assertTrue($settlement->breakdown['salary_already_paid']);
    }

    private function prepareCompany(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
    }

    /**
     * An employee on 26,000 whose last working day was 15 September 2026, with
     * a 6,000 borrow outstanding and unpaid overtime, bonus and deduction.
     */
    private function employeeWhoLeftMidSeptember(): Employee
    {
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);

        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-12-01',
        ]);
        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed', 'amount' => 1500, 'status' => 'approved',
        ]);
        SalaryBonus::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-12', 'type' => 'bonus', 'title' => 'Festival Bonus', 'amount' => 1000]);
        SalaryDeduction::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-12', 'title' => 'Uniform', 'amount' => 500]);

        app(EmployeeService::class)->exit($employee, [
            'exit_date' => '2026-09-15', 'last_working_date' => '2026-09-15', 'exit_type' => 'resignation',
        ]);

        return $employee;
    }

    private function service(): FinalSettlementService
    {
        return app(FinalSettlementService::class);
    }
}
