<?php

namespace Tests\Feature\Services;

use App\Models\AuditLog;
use App\Services\ShortHoursCalculationService;
use App\Support\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class ShortHoursCalculationServiceTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_one_short_hour_at_200_per_hour_is_a_200_adjustment(): void
    {
        $this->useCompany($this->createCompany());
        $this->configure(['short_hours_mode' => 'deduct']);
        $employee = $this->createEmployee();

        $result = $this->service()->forPeriod(
            $employee,
            PayrollPeriod::forMonth(2026, 9),
            ['required_minutes' => 480, 'worked_minutes' => 420, 'short_minutes' => 60],
            200.0,
        );

        $this->assertSame(480, $result['required_minutes']);
        $this->assertSame(420, $result['worked_minutes']);
        $this->assertSame(60, $result['short_minutes']);
        $this->assertSame(200.0, $result['hourly_rate']);
        $this->assertSame(200.0, $result['calculated_amount']);
        $this->assertNull($result['adjusted_amount']);
        $this->assertSame(200.0, $result['final_amount']);
    }

    #[TestWith(['record_only'])]
    #[TestWith(['manual'])]
    public function test_short_hours_are_calculated_but_not_deducted_unless_the_mode_is_deduct(string $mode): void
    {
        $this->useCompany($this->createCompany());
        $this->configure(['short_hours_mode' => $mode]);
        $employee = $this->createEmployee();

        $result = $this->service()->forPeriod(
            $employee,
            PayrollPeriod::forMonth(2026, 9),
            ['required_minutes' => 480, 'worked_minutes' => 420, 'short_minutes' => 60],
            200.0,
        );

        $this->assertSame(200.0, $result['calculated_amount']);
        $this->assertSame(0.0, $result['final_amount']);
    }

    public function test_admin_adjusted_amount_replaces_the_calculated_amount(): void
    {
        $this->useCompany($this->createCompany());
        $this->configure(['short_hours_mode' => 'manual']);
        $employee = $this->createEmployee();
        $period = PayrollPeriod::forMonth(2026, 9);

        $this->service()->adjust($employee, $period, 150, 'Agreed with the employee');

        $result = $this->service()->forPeriod($employee, $period, ['required_minutes' => 480, 'worked_minutes' => 420, 'short_minutes' => 60], 200.0);
        $this->assertSame(200.0, $result['calculated_amount']);
        $this->assertSame(150.0, $result['adjusted_amount']);
        $this->assertSame(150.0, $result['final_amount']);
        $this->assertTrue(AuditLog::query()->where('action', 'short_hours.adjusted')->where('employee_id', $employee->id)->exists());
    }

    public function test_company_fixed_rate_is_used_instead_of_the_salary_hourly_rate(): void
    {
        $this->useCompany($this->createCompany());
        $this->configure(['short_hours_mode' => 'deduct', 'short_hours_rate_type' => 'fixed', 'short_hours_fixed_rate' => 100]);
        $employee = $this->createEmployee();

        $result = $this->service()->forPeriod(
            $employee,
            PayrollPeriod::forMonth(2026, 9),
            ['required_minutes' => 480, 'worked_minutes' => 390, 'short_minutes' => 90],
            200.0,
        );

        $this->assertSame(100.0, $result['hourly_rate']);
        $this->assertSame(150.0, $result['final_amount']);
    }

    private function service(): ShortHoursCalculationService
    {
        return app(ShortHoursCalculationService::class);
    }
}
