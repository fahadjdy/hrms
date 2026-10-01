<?php

namespace Tests\Feature\Http;

use App\Jobs\GenerateSalarySlips;
use App\Models\Company;
use App\Models\Department;
use App\Services\BorrowCalculationService;
use App\Services\PayrollService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

class ReportEndpointsTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    public function test_attendance_report_shows_each_employees_summary_for_the_month(): void
    {
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao']);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        $this->markAttendance($employee, '2026-09-04', 'present', '09:00', '17:00');

        $response = $this->actingAs($this->adminOf($company))->get('/reports?report=attendance&month=2026-09');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('report', 'attendance')
            ->where('month', '2026-09')
            ->where('monthLabel', 'September 2026')
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Asha Rao')
            ->where('employees.data.0.summary.working_days', 26)
            ->where('employees.data.0.summary.present', 25)
            ->where('employees.data.0.summary.absent', 1)
            ->where('employees.data.0.summary.short_minutes', 60)
            ->where('employees.data.0.summary.attendance_rate', 96.2));
    }

    public function test_attendance_export_has_one_row_per_current_employee(): void
    {
        $company = $this->prepareCompany();
        $sales = Department::factory()->create(['name' => 'Sales']);
        $employee = $this->createEmployee(['employee_code' => 'EMP-0001', 'first_name' => 'Asha', 'last_name' => 'Rao', 'department_id' => $sales->id]);
        $this->createEmployee(['employee_code' => 'EMP-0002', 'first_name' => 'Bina', 'last_name' => 'Shah']);
        $this->markAttendance($employee, '2026-09-03', 'absent');

        $response = $this->actingAs($this->adminOf($company))->get('/reports/export/attendance?month=2026-09');

        $response->assertDownload('attendance-2026-09.csv');
        $rows = array_map(fn (string $line): array => str_getcsv($line, escape: '\\'), explode("\n", trim($response->streamedContent())));
        $this->assertCount(3, $rows);
        $this->assertSame(['Employee ID', 'Name', 'Department', 'Working Days', 'Present', 'Absent'], array_slice($rows[0], 0, 6));
        $this->assertSame(['EMP-0001', 'Asha Rao', 'Sales', '26', '25', '1'], array_slice($rows[1], 0, 6));
        $this->assertSame(['EMP-0002', 'Bina Shah', '', '26', '26', '0'], array_slice($rows[2], 0, 6));
    }

    public function test_attendance_export_can_be_limited_to_a_department(): void
    {
        $company = $this->prepareCompany();
        $sales = Department::factory()->create(['name' => 'Sales']);
        $this->createEmployee(['employee_code' => 'EMP-0001', 'department_id' => $sales->id]);
        $this->createEmployee(['employee_code' => 'EMP-0002']);

        $response = $this->actingAs($this->adminOf($company))
            ->get('/reports/export/attendance?month=2026-09&department_id='.$sales->id);

        $lines = explode("\n", trim($response->streamedContent()));
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('EMP-0001,', $lines[1]);
    }

    public function test_borrow_report_totals_each_employee_and_leaves_out_employees_without_borrows(): void
    {
        $company = $this->prepareCompany();
        $withBorrows = $this->createEmployee(['first_name' => 'Asha', 'last_name' => 'Rao']);
        $this->createEmployee();
        $borrows = app(BorrowCalculationService::class);
        $first = $borrows->create($withBorrows, ['amount' => 20000, 'borrow_date' => '2026-06-01', 'monthly_deduction' => 5000]);
        $borrows->recover($first, 15000, CarbonImmutable::parse('2026-09-20'));
        $borrows->create($withBorrows, ['amount' => 15000, 'borrow_date' => '2026-09-15', 'monthly_deduction' => 3000]);

        $response = $this->actingAs($this->adminOf($company))->get('/reports?report=borrow');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('report', 'borrow')
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Asha Rao')
            ->where('employees.data.0.borrow_count', 2)
            ->where('employees.data.0.total_borrowed', 35000)
            ->where('employees.data.0.total_recovered', 15000)
            ->where('employees.data.0.total_outstanding', 20000));
    }

    public function test_payroll_export_lists_the_amounts_of_every_employee_in_the_payroll(): void
    {
        Queue::fake([GenerateSalarySlips::class]);
        $company = $this->prepareCompany();
        $employee = $this->createEmployee(['employee_code' => 'EMP-0001', 'first_name' => 'Asha', 'last_name' => 'Rao'], 26000);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->calculate($payrolls->create(2026, 9));

        $response = $this->actingAs($this->adminOf($company))->get("/reports/export/payroll?payroll_id={$payroll->id}");

        $response->assertDownload('payroll-2026-09.csv');
        $rows = array_map(fn (string $line): array => str_getcsv($line, escape: '\\'), explode("\n", trim($response->streamedContent())));
        $record = array_combine($rows[0], $rows[1]);
        $this->assertSame('EMP-0001', $record['Employee ID']);
        $this->assertSame('Asha Rao', $record['Name']);
        $this->assertEquals(26000, $record['Gross Salary']);
        $this->assertEquals(1000, $record['Attendance Deduction']);
        $this->assertEquals(0, $record['New Borrow / Advance']);
        $this->assertEquals(25000, $record['Net Payable']);
    }

    public function test_payroll_export_requires_a_payroll(): void
    {
        $company = $this->prepareCompany();

        $response = $this->actingAs($this->adminOf($company))->get('/reports/export/payroll');

        $response->assertSessionHasErrors('payroll_id');
    }

    public function test_unknown_report_export_is_not_found(): void
    {
        $company = $this->prepareCompany();

        $response = $this->actingAs($this->adminOf($company))->get('/reports/export/salaries');

        $response->assertNotFound();
    }

    public function test_payroll_reports_break_the_selected_payroll_down_by_department(): void
    {
        $company = $this->prepareCompany();
        $sales = Department::factory()->create(['name' => 'Sales']);
        $this->createEmployee(['department_id' => $sales->id], 26000);
        $this->createEmployee(['department_id' => $sales->id], 30000);
        $this->createEmployee([], 20000);
        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->calculate($payrolls->create(2026, 9));

        $response = $this->actingAs($this->adminOf($company))->get('/payroll/reports');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('payrolls', 1)
            ->where('payrolls.0.label', 'September 2026')
            ->where('payrolls.0.total_net_payable', 76000)
            ->where('selectedPayrollId', $payroll->id)
            ->has('departments', 2)
            ->where('departments.0.department', 'No department')
            ->where('departments.0.net_payable', 20000)
            ->where('departments.1.department', 'Sales')
            ->where('departments.1.employees', 2)
            ->where('departments.1.gross', 56000)
            ->where('departments.1.net_payable', 56000));
    }

    private function prepareCompany(): Company
    {
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany());
        $this->configure(['attendance_mode' => 'automatic']);

        return $company;
    }
}
