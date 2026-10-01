<?php

namespace Tests\Feature\Services;

use App\Enums\BorrowStatus;
use App\Enums\BorrowTransactionType;
use App\Enums\OvertimeStatus;
use App\Enums\PayrollBucket;
use App\Enums\PayrollStatus;
use App\Jobs\GenerateSalarySlips;
use App\Models\AuditLog;
use App\Models\BorrowTransaction;
use App\Models\Employee;
use App\Models\Overtime;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalarySlip;
use App\Services\BorrowCalculationService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_calculating_creates_one_item_per_current_employee_and_skips_past_employees(): void
    {
        $this->prepareCompany();
        $this->createEmployee([], 26000);
        $this->createEmployee([], 52000);
        Employee::factory()->past('2026-08-31')->create();

        $payroll = $this->service()->calculate($this->service()->create(2026, 9));

        $this->assertSame(PayrollStatus::Calculated, $payroll->status);
        $this->assertSame(2, $payroll->employee_count);
        $this->assertSame(78000.0, $payroll->total_gross);
        $this->assertSame(78000.0, $payroll->total_net_payable);
    }

    public function test_a_second_payroll_for_the_same_month_is_rejected(): void
    {
        $this->prepareCompany();
        $this->service()->create(2026, 9);

        $this->expectException(ValidationException::class);

        $this->service()->create(2026, 9);
    }

    public function test_manual_adjustment_keeps_the_system_amount_and_changes_the_final_amount(): void
    {
        $this->prepareCompany();
        $this->createEmployee([], 26000);
        $payroll = $this->service()->calculate($this->service()->create(2026, 9));
        $item = PayrollItem::query()->sole();

        $this->service()->addAdjustment($item, PayrollBucket::Bonus, 1500, 'Performance bonus');

        $item->refresh();
        // System calculated / admin adjustment / final amount.
        $bucket = array_map(floatval(...), array_diff_key($item->breakdown['buckets']['bonus_amount'], ['label' => null]));
        $this->assertSame(['system' => 0.0, 'adjustment' => 1500.0, 'final' => 1500.0], $bucket);
        $this->assertSame(1500.0, $item->bonus_amount);
        $this->assertSame(27500.0, $item->net_payable);
        $this->assertTrue($item->is_adjusted);
        $this->assertSame(PayrollStatus::Adjusted, $payroll->refresh()->status);
        $this->assertSame(27500.0, $payroll->total_net_payable);
        $this->assertTrue(AuditLog::query()->where('action', 'payroll.adjusted')->exists());
    }

    public function test_adjustments_survive_a_recalculation(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $payroll = $this->service()->calculate($this->service()->create(2026, 9));
        $this->service()->addAdjustment(PayrollItem::query()->sole(), PayrollBucket::OtherDeductions, 400, 'Damaged equipment');
        $this->markAttendance($employee, '2026-09-03', 'absent');

        $this->service()->calculate($payroll);

        $item = PayrollItem::query()->sole();
        $this->assertSame(1000.0, $item->attendance_deduction);
        $this->assertSame(400.0, $item->other_deductions);
        $this->assertSame(24600.0, $item->net_payable);
    }

    public function test_rejects_an_adjustment_that_recovers_more_borrow_than_is_outstanding(): void
    {
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        app(BorrowCalculationService::class)->create($employee, [
            'amount' => 3000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 1000, 'deduction_start_month' => '2026-09-01',
        ]);
        $this->service()->calculate($this->service()->create(2026, 9));
        $item = PayrollItem::query()->sole();

        try {
            $this->service()->addAdjustment($item, PayrollBucket::BorrowRecovery, 2500, 'Recover faster');
            $this->fail('The over-recovery was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('more than the outstanding borrow', $exception->errors()['amount'][0]);
        }

        $this->assertSame(1000.0, $item->refresh()->borrow_recovery);
    }

    public function test_finalizing_posts_borrow_recovery_and_new_borrow_and_marks_overtime_as_paid(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 40000);
        $borrows = app(BorrowCalculationService::class);
        $existing = $borrows->create($employee, ['amount' => 8000, 'borrow_date' => '2026-05-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-06-01']);
        $new = $borrows->create($employee, [
            'amount' => 10000, 'borrow_date' => '2026-09-25', 'monthly_deduction' => 2500,
            'disbursement_method' => 'with_salary', 'disburse_period' => '2026-09-01',
        ]);
        $overtime = Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-10', 'calculation_type' => 'fixed', 'amount' => 1500, 'status' => 'approved',
        ]);
        $payroll = $this->service()->calculate($this->service()->create(2026, 9));

        $this->service()->finalize($payroll);

        $item = PayrollItem::query()->sole();
        $this->assertSame(PayrollStatus::Finalized, $payroll->refresh()->status);
        $this->assertSame(49500.0, $item->net_payable);

        $this->assertSame(6000.0, $existing->refresh()->outstanding_amount);
        $this->assertSame(BorrowStatus::Active, $new->refresh()->status);
        $this->assertSame(10000.0, $new->outstanding_amount);
        $this->assertSame(
            [BorrowTransactionType::Disbursement, BorrowTransactionType::Recovery],
            BorrowTransaction::query()->where('payroll_item_id', $item->id)->orderBy('id')->get()->map->type->all(),
        );

        $overtime->refresh();
        $this->assertSame(OvertimeStatus::Paid, $overtime->status);
        $this->assertSame($item->id, $overtime->payroll_item_id);

        $this->assertSame('SLIP-202609-'.$employee->employee_code, SalarySlip::query()->sole()->slip_number);
        Queue::assertPushed(GenerateSalarySlips::class, fn (GenerateSalarySlips $job): bool => $job->payrollId === $payroll->id);
        $this->assertTrue(AuditLog::query()->where('action', 'payroll.finalized')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'borrow.given_with_payroll')->exists());
    }

    public function test_finalized_payroll_cannot_be_recalculated_or_adjusted(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $this->createEmployee([], 26000);
        $payroll = $this->service()->calculate($this->service()->create(2026, 9));
        $this->service()->finalize($payroll);
        $item = PayrollItem::query()->sole();

        $attempts = [
            'recalculate' => fn () => $this->service()->calculate($payroll),
            'adjust' => fn () => $this->service()->addAdjustment($item, PayrollBucket::Bonus, 500, 'Late bonus'),
            'delete' => fn () => $this->service()->delete($payroll),
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();
                $this->fail("A finalized payroll allowed: {$name}.");
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('finalized and locked', $exception->errors()['payroll'][0]);
            }
        }

        $this->assertSame(26000.0, $item->refresh()->net_payable);
        $this->assertModelExists($payroll);
    }

    public function test_changes_to_attendance_after_finalizing_do_not_change_the_finalized_amounts(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $payroll = $this->service()->calculate($this->service()->create(2026, 9));
        $this->service()->finalize($payroll);

        $this->markAttendance($employee, '2026-09-03', 'absent');

        $this->assertSame(26000.0, PayrollItem::query()->sole()->net_payable);
        $this->assertSame(26000.0, $payroll->refresh()->total_net_payable);
    }

    public function test_reopening_reverses_the_borrow_postings_and_is_audited(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = app(BorrowCalculationService::class)->create($employee, [
            'amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01',
        ]);
        $payroll = $this->service()->calculate($this->service()->create(2026, 9));
        $this->service()->finalize($payroll);
        $this->assertSame(4000.0, $borrow->refresh()->outstanding_amount);

        $this->service()->reopen($payroll, 'Attendance correction');

        $payroll->refresh();
        $this->assertSame(PayrollStatus::UnderReview, $payroll->status);
        $this->assertSame('Attendance correction', $payroll->reopen_reason);
        $this->assertSame(6000.0, $borrow->refresh()->outstanding_amount);
        $this->assertSame(0.0, $borrow->recovered_amount);
        $this->assertTrue(BorrowTransaction::query()->where('type', BorrowTransactionType::Reversal->value)->exists());
        $this->assertSame(0, SalarySlip::query()->count());
        $audit = AuditLog::query()->where('action', 'payroll.reopened')->sole();
        $this->assertSame('Attendance correction', $audit->new_values['reason']);
    }

    public function test_reopened_payroll_can_be_finalized_again_without_recovering_twice(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->prepareCompany();
        $employee = $this->createEmployee([], 26000);
        $borrow = app(BorrowCalculationService::class)->create($employee, [
            'amount' => 6000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01',
        ]);
        $payroll = $this->service()->calculate($this->service()->create(2026, 9));
        $this->service()->finalize($payroll);
        $this->service()->reopen($payroll, 'Correction');

        $this->service()->finalize($this->service()->calculate($payroll));

        $this->assertSame(4000.0, $borrow->refresh()->outstanding_amount);
        $this->assertSame(2000.0, $borrow->recovered_amount);
    }

    public function test_only_a_calculated_payroll_can_be_finalized(): void
    {
        $this->prepareCompany();
        $payroll = $this->service()->create(2026, 9);

        try {
            $this->service()->finalize($payroll);
            $this->fail('A draft payroll was finalized.');
        } catch (ValidationException $exception) {
            $this->assertSame('Calculate the payroll before continuing.', $exception->errors()['payroll'][0]);
        }

        $this->assertSame(PayrollStatus::Draft, Payroll::query()->sole()->status);
    }

    private function prepareCompany(): void
    {
        $this->travelTo('2026-10-01 10:00:00');
        $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);
    }

    private function service(): PayrollService
    {
        return app(PayrollService::class);
    }
}
