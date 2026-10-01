<?php

namespace Tests\Feature\Services;

use App\Enums\BorrowStatus;
use App\Enums\BorrowTransactionType;
use App\Enums\PayrollBucket;
use App\Enums\PayrollStatus;
use App\Jobs\GenerateSalarySlips;
use App\Models\BorrowInstallment;
use App\Models\BorrowTransaction;
use App\Models\EmployeeBorrow;
use App\Models\PayrollItem;
use App\Models\SalarySlip;
use App\Services\BorrowCalculationService;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * What finalizing a payroll writes to the borrow ledger, and what it refuses to write.
 */
class PayrollBorrowPostingTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_adjusted_borrow_recovery_is_spread_over_the_borrows_oldest_first(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $older = $this->borrows()->create($employee, ['amount' => 3000, 'borrow_date' => '2026-06-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-09-01']);
        $newer = $this->borrows()->create($employee, ['amount' => 5000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-09-01']);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $item = PayrollItem::query()->sole();

        // Scheduled 1,000 + 1,000; the admin recovers 3,000 more.
        $this->payrolls()->addAdjustment($item, PayrollBucket::BorrowRecovery, 3000, 'Employee asked to repay faster');
        $this->payrolls()->finalize($payroll);

        $this->assertSame(5000.0, $item->refresh()->borrow_recovery);
        $this->assertSame(21000.0, $item->net_payable);
        $older->refresh();
        $newer->refresh();
        $this->assertSame(BorrowStatus::Recovered, $older->status);
        $this->assertSame(0.0, $older->outstanding_amount);
        $this->assertSame(3000.0, $newer->outstanding_amount);
        $this->assertSame(
            [3000.0, 2000.0],
            BorrowTransaction::query()->where('payroll_item_id', $item->id)->orderBy('id')->pluck('amount')->all(),
        );
    }

    public function test_reduced_borrow_recovery_still_pays_each_borrow_up_to_its_scheduled_share(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $older = $this->borrows()->create($employee, ['amount' => 16000, 'borrow_date' => '2026-06-01', 'monthly_deduction' => 4000, 'deduction_start_month' => '2026-09-01']);
        $newer = $this->borrows()->create($employee, ['amount' => 10000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01']);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $item = PayrollItem::query()->sole();

        // Scheduled 4,000 + 2,000; the admin lowers the total by 1,000.
        $this->payrolls()->addAdjustment($item, PayrollBucket::BorrowRecovery, -1000, 'Employee asked for a lower deduction');
        $this->payrolls()->finalize($payroll);

        $this->assertSame(12000.0, $older->refresh()->outstanding_amount);
        $this->assertSame(9000.0, $newer->refresh()->outstanding_amount);
        $this->assertSame(
            ['2026-10-01', '2026-10-01'],
            [$this->nextDueMonth($older), $this->nextDueMonth($newer)],
        );
    }

    public function test_borrow_left_out_by_an_adjustment_moves_its_plan_to_the_next_month(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = $this->borrows()->create($employee, ['amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01']);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));

        $this->payrolls()->addAdjustment(PayrollItem::query()->sole(), PayrollBucket::BorrowRecovery, -2000, 'No deduction this month');
        $this->payrolls()->finalize($payroll);

        $this->assertSame(6000.0, $borrow->refresh()->outstanding_amount);
        $this->assertSame(
            ['2026-10-01', '2026-11-01', '2026-12-01'],
            $borrow->installments()->get()->map(fn (BorrowInstallment $i): string => $i->due_month->toDateString())->all(),
        );
    }

    public function test_recovery_posted_by_payroll_is_recorded_as_the_installment_of_that_month(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = $this->borrows()->create($employee, ['amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01']);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));

        $this->payrolls()->finalize($payroll);

        $installments = $borrow->installments()->get();
        $this->assertSame(
            [['2026-09-01', 'paid', 2000.0], ['2026-10-01', 'pending', 2000.0], ['2026-11-01', 'pending', 2000.0]],
            $installments->map(fn (BorrowInstallment $i): array => [$i->due_month->toDateString(), $i->status, $i->amount])->all(),
        );
        $this->assertSame(PayrollItem::query()->sole()->id, $installments[0]->payroll_item_id);
    }

    public function test_early_recovery_does_not_pull_the_schedule_ahead_of_the_deduction_start_month(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        // Deductions are agreed to start in December, but the admin recovers 1,000 early.
        $borrow = $this->borrows()->create($employee, ['amount' => 4000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1500, 'deduction_start_month' => '2026-12-01']);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $this->payrolls()->addAdjustment(PayrollItem::query()->sole(), PayrollBucket::BorrowRecovery, 1000, 'Employee asked to start early');

        $this->payrolls()->finalize($payroll);

        $this->assertSame(3000.0, $borrow->refresh()->outstanding_amount);
        $this->assertSame(
            [['2026-12-01', 1500.0], ['2027-01-01', 1500.0]],
            $borrow->installments()->where('status', 'pending')->get()
                ->map(fn (BorrowInstallment $i): array => [$i->due_month->toDateString(), $i->amount])->all(),
        );
    }

    public function test_finalizing_is_refused_when_a_borrow_was_repaid_after_the_payroll_was_calculated(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = $this->borrows()->create($employee, ['amount' => 2000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01']);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        // The employee repays in cash before the payroll is finalized.
        $this->borrows()->recover($borrow, 1500, CarbonImmutable::parse('2026-09-30'));

        try {
            $this->payrolls()->finalize($payroll);
            $this->fail('A payroll that would over-recover a borrow was finalized.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['payroll'][0];
            $this->assertStringContainsString('more than the outstanding balance', $message);
            $this->assertStringContainsString('Recalculate the payroll', $message);
        }

        $this->assertSame(PayrollStatus::Calculated, $payroll->refresh()->status);
        $this->assertSame(500.0, $borrow->refresh()->outstanding_amount);
        $this->assertSame(0, SalarySlip::query()->count());
        $this->assertSame(0, BorrowTransaction::query()->whereNotNull('payroll_item_id')->count());
        Queue::assertNotPushed(GenerateSalarySlips::class);
    }

    public function test_recalculating_after_the_refusal_recovers_only_what_is_still_owed(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = $this->borrows()->create($employee, ['amount' => 2000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01']);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $this->borrows()->recover($borrow, 1500, CarbonImmutable::parse('2026-09-30'));

        $this->payrolls()->finalize($this->payrolls()->calculate($payroll));

        $this->assertSame(500.0, PayrollItem::query()->sole()->borrow_recovery);
        $this->assertSame(BorrowStatus::Recovered, $borrow->refresh()->status);
        $this->assertSame(2000.0, $borrow->recovered_amount);
    }

    public function test_finalizing_is_refused_when_a_borrow_to_be_given_was_cancelled_after_calculation(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = $this->borrows()->create($employee, [
            'amount' => 10000, 'borrow_date' => '2026-09-25', 'monthly_deduction' => 2500,
            'disbursement_method' => 'with_salary', 'disburse_period' => '2026-09-01',
        ]);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $this->borrows()->cancel($borrow);

        try {
            $this->payrolls()->finalize($payroll);
            $this->fail('A payroll paid out a cancelled borrow.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Recalculate the payroll', $exception->errors()['payroll'][0]);
        }

        $this->assertSame(PayrollStatus::Calculated, $payroll->refresh()->status);
        $this->assertSame(BorrowStatus::Cancelled, $borrow->refresh()->status);
        $this->assertSame(0, BorrowTransaction::query()->count());
    }

    public function test_reopening_takes_back_a_borrow_given_with_salary_and_finalizing_again_gives_it_once(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = $this->borrows()->create($employee, [
            'amount' => 10000, 'borrow_date' => '2026-09-25', 'monthly_deduction' => 2500,
            'disbursement_method' => 'with_salary', 'disburse_period' => '2026-09-01',
        ]);
        $payroll = $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $this->payrolls()->finalize($payroll);
        $this->assertSame(BorrowStatus::Active, $borrow->refresh()->status);

        $this->payrolls()->reopen($payroll, 'Wrong advance amount');

        $this->assertSame(BorrowStatus::PendingDisbursement, $borrow->refresh()->status);
        $this->assertNull($borrow->disbursed_at);
        $this->assertSame(0.0, $this->borrows()->outstandingFor($employee));

        $this->payrolls()->finalize($this->payrolls()->calculate($payroll));

        $this->assertSame(BorrowStatus::Active, $borrow->refresh()->status);
        $this->assertSame(10000.0, $this->borrows()->outstandingFor($employee));
        $this->assertSame(36000.0, PayrollItem::query()->sole()->net_payable);
        $this->assertSame(
            [BorrowTransactionType::Disbursement, BorrowTransactionType::Reversal, BorrowTransactionType::Disbursement],
            BorrowTransaction::query()->orderBy('id')->get()->map->type->all(),
        );
    }

    public function test_adjustment_cannot_reduce_a_total_below_zero(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $item = PayrollItem::query()->sole();

        try {
            $this->payrolls()->addAdjustment($item, PayrollBucket::AttendanceDeduction, -1500, 'Waive the absence');
            $this->fail('An adjustment made a deduction negative.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('would make Attendance Deduction negative', $exception->errors()['amount'][0]);
        }

        $this->assertSame(1000.0, $item->refresh()->attendance_deduction);
    }

    public function test_negative_adjustment_waives_part_of_a_deduction(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        $this->payrolls()->calculate($this->payrolls()->create(2026, 9));
        $item = PayrollItem::query()->sole();

        $this->payrolls()->addAdjustment($item, PayrollBucket::AttendanceDeduction, -1000, 'Absence approved afterwards');

        $item->refresh();
        $this->assertSame(0.0, $item->attendance_deduction);
        $this->assertSame(26000.0, $item->net_payable);
        $this->assertEquals(1000, $item->breakdown['buckets']['attendance_deduction']['system']);
        $this->assertEquals(-1000, $item->breakdown['buckets']['attendance_deduction']['adjustment']);
    }

    private function prepareCompany(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
    }

    /**
     * The month of the borrow's first installment that is still to be recovered.
     */
    private function nextDueMonth(EmployeeBorrow $borrow): ?string
    {
        return $borrow->installments()
            ->where('status', BorrowInstallment::STATUS_PENDING)
            ->first()
            ?->due_month
            ->toDateString();
    }

    private function borrows(): BorrowCalculationService
    {
        return app(BorrowCalculationService::class);
    }

    private function payrolls(): PayrollService
    {
        return app(PayrollService::class);
    }
}
