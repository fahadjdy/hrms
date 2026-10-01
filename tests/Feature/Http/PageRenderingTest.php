<?php

namespace Tests\Feature\Http;

use App\Enums\PayrollBucket;
use App\Jobs\GenerateSalarySlips;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeDocument;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Overtime;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalaryBonus;
use App\Models\SalaryDeduction;
use App\Models\User;
use App\Services\BorrowCalculationService;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Services\PayrollService;
use App\Services\ShortHoursCalculationService;
use App\Support\PayrollPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * Every page of a company with realistic data renders without an error.
 * Lazy loading is forbidden in tests, so this also catches N+1 queries.
 */
class PageRenderingTest extends TestCase
{
    use InteractsWithCompanies, RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function companyPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'dashboard for the previous month' => ['/dashboard?preset=previous_month'],
            'employees' => ['/employees'],
            'employees searched and filtered' => ['/employees?search=ra&status=active&employment_type=full_time&department_id={department}'],
            'past employees' => ['/employees/past'],
            'add employee' => ['/employees/create'],
            'employee profile' => ['/employees/{employee}'],
            'past employee profile' => ['/employees/{pastEmployee}'],
            'edit employee' => ['/employees/{employee}/edit'],
            'departments' => ['/departments'],
            'designations' => ['/designations'],
            'documents' => ['/documents'],
            'documents filtered' => ['/documents?search=contract&employee_id={employee}'],
            'daily attendance' => ['/attendance?date=2026-09-10'],
            'daily attendance today' => ['/attendance'],
            'attendance calendar picker' => ['/attendance/calendar?search=ra'],
            'employee attendance calendar' => ['/employees/{employee}/attendance?month=2026-09'],
            'past employee attendance calendar' => ['/employees/{pastEmployee}/attendance?month=2026-09'],
            'work shifts' => ['/work-shifts'],
            'weekly holidays' => ['/weekly-holidays'],
            'holidays' => ['/holidays?year=2026'],
            'leave records' => ['/leaves'],
            'leave records filtered' => ['/leaves?status=approved&search=ra&from=2026-09-01&to=2026-09-30'],
            'leave types' => ['/leave-types'],
            'leave balances' => ['/leave-balances?year=2026'],
            'payroll list' => ['/payroll'],
            'payroll preview' => ['/payroll/{payroll}'],
            'payroll preview filtered' => ['/payroll/{payroll}?search=ra&status=adjusted&department_id={department}'],
            'payroll item' => ['/payroll/{payroll}/items/{item}'],
            'payroll reports' => ['/payroll/reports'],
            'payroll reports for one payroll' => ['/payroll/reports?payroll_id={payroll}'],
            'salary structure' => ['/salary-structure'],
            'employee salary' => ['/employees/{employee}/salary'],
            'salary revisions' => ['/salary-revisions?search=ra&from=2025-01-01&to=2026-12-31'],
            'salary slips' => ['/salary-slips?payroll_id={payroll}&search=ra'],
            'borrows' => ['/borrows'],
            'borrows filtered' => ['/borrows?search=BRW&status=active&kind=new'],
            'new borrow' => ['/borrows/create?employee_id={employee}'],
            'borrow' => ['/borrows/{borrow}'],
            'borrow recoveries' => ['/borrow-recoveries?type=recovery&search=BRW&from=2026-01-01&to=2026-12-31'],
            'overtime' => ['/overtime?month=2026-09&status=paid&search=ra'],
            'short hours' => ['/short-hours?month=2026-09'],
            'deductions' => ['/deductions?month=2026-09&search=ra'],
            'bonuses' => ['/bonuses?month=2026-09&type=bonus'],
            'final settlements' => ['/final-settlements'],
            'final settlements not started' => ['/final-settlements?status=not_started'],
            'final settlement' => ['/final-settlements/{pastEmployee}'],
            'attendance report' => ['/reports?report=attendance&month=2026-09'],
            'borrow report' => ['/reports?report=borrow'],
            'company settings' => ['/settings/company'],
            'attendance settings' => ['/settings/attendance'],
            'payroll settings' => ['/settings/payroll'],
            'roles and users' => ['/settings/roles'],
            'audit logs' => ['/audit-logs'],
            'audit logs filtered' => ['/audit-logs?action=payroll&search=payroll&from=2026-01-01&to=2026-12-31'],
            'profile settings' => ['/settings/profile'],
        ];
    }

    #[DataProvider('companyPages')]
    public function test_company_page_renders(string $url): void
    {
        [$company, $ids] = $this->companyWithData();

        $response = $this->actingAs($this->adminOf($company))->get(strtr($url, $ids));

        $response->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function exports(): array
    {
        return [
            'attendance' => ['/reports/export/attendance?month=2026-09'],
            'borrow' => ['/reports/export/borrow'],
            'payroll' => ['/reports/export/payroll?payroll_id={payroll}'],
        ];
    }

    #[DataProvider('exports')]
    public function test_report_export_downloads_a_csv_with_a_row_per_employee(string $url): void
    {
        [$company, $ids] = $this->companyWithData();

        $response = $this->actingAs($this->adminOf($company))->get(strtr($url, $ids));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertStringStartsWith('"Employee ID",Name,Department', $lines[0]);
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function platformPages(): array
    {
        return [
            'companies' => ['/admin/companies'],
            'companies searched' => ['/admin/companies?search=acme&status=active'],
            'new company' => ['/admin/companies/create'],
            'company' => ['/admin/companies/{company}'],
            'edit company' => ['/admin/companies/{company}/edit'],
            'platform settings' => ['/admin/settings'],
            'profile settings' => ['/settings/profile'],
        ];
    }

    #[DataProvider('platformPages')]
    public function test_platform_page_renders_for_the_super_admin(string $url): void
    {
        [, $ids] = $this->companyWithData();
        $this->tenant()->forget();

        $response = $this->actingAs(User::factory()->superAdmin()->create())->get(strtr($url, $ids));

        $response->assertOk();
    }

    /**
     * A company in October 2026 with a finalized September payroll and data in
     * every module.
     *
     * @return array{0: Company, 1: array<string, string>}
     */
    private function companyWithData(): array
    {
        Queue::fake([GenerateSalarySlips::class]);
        $this->travelTo('2026-10-01 10:00:00');
        $company = $this->useCompany($this->createCompany(['name' => 'Acme']));
        $this->configure(['attendance_mode' => 'automatic', 'short_hours_mode' => 'deduct']);

        $department = Department::factory()->create(['name' => 'Engineering']);
        $designation = Designation::factory()->create(['name' => 'Engineer']);
        $manager = $this->createEmployee(['first_name' => 'Meera', 'last_name' => 'Nair'], 60000);
        $employee = $this->createEmployee([
            'first_name' => 'Rahul', 'last_name' => 'Sharma', 'department_id' => $department->id,
            'designation_id' => $designation->id, 'reporting_manager_id' => $manager->id,
        ], 26000);
        $leaving = $this->createEmployee(['first_name' => 'Ravi', 'last_name' => 'Rao', 'department_id' => $department->id], 30000);

        Holiday::factory()->create(['name' => 'Founders Day', 'date' => '2026-09-16']);
        $this->markAttendance($employee, '2026-09-03', 'absent');
        $this->markAttendance($employee, '2026-09-10', 'present', '09:32', '17:41');
        $this->markAttendance($employee, '2026-09-11', 'present', '09:00', '20:00');
        app(LeaveService::class)->create($employee, [
            'leave_type_id' => LeaveType::query()->where('code', 'CL')->sole()->id,
            'start_date' => '2026-09-21', 'end_date' => '2026-09-22', 'status' => 'approved',
        ]);
        app(LeaveService::class)->create($manager, [
            'leave_type_id' => LeaveType::query()->where('code', 'SL')->sole()->id,
            'start_date' => '2026-10-05', 'end_date' => '2026-10-05',
        ]);
        app(ShortHoursCalculationService::class)->adjust($employee, PayrollPeriod::forMonth(2026, 9), 50, 'Agreed with the employee');

        Overtime::query()->create([
            'employee_id' => $employee->id, 'date' => '2026-09-11', 'calculation_type' => 'hourly',
            'hours' => 2, 'rate' => 200, 'amount' => 400, 'status' => 'approved',
        ]);
        SalaryBonus::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-15', 'type' => 'bonus', 'title' => 'Festival Bonus', 'amount' => 1000]);
        SalaryDeduction::query()->create(['employee_id' => $employee->id, 'date' => '2026-09-20', 'title' => 'Uniform', 'amount' => 500]);

        $borrows = app(BorrowCalculationService::class);
        $borrow = $borrows->create($employee, [
            'amount' => 12000, 'borrow_date' => '2026-08-01', 'monthly_deduction' => 2000, 'deduction_start_month' => '2026-09-01',
        ]);
        $borrows->create($leaving, ['kind' => 'existing', 'amount' => 8000, 'opening_balance' => 6000, 'borrow_date' => '2026-01-10', 'monthly_deduction' => 1000]);
        $borrows->create($manager, [
            'amount' => 10000, 'borrow_date' => '2026-09-25', 'monthly_deduction' => 2500,
            'disbursement_method' => 'with_salary', 'disburse_period' => '2026-09-01',
        ]);

        $this->setSalary($employee, 28000, '2026-10-01', reason: 'Annual Increment');

        $payrolls = app(PayrollService::class);
        $payroll = $payrolls->calculate($payrolls->create(2026, 9));
        $item = PayrollItem::query()->where('employee_id', $employee->id)->sole();
        $payrolls->addAdjustment($item, PayrollBucket::Bonus, 250, 'Spot award', $this->adminOf($company));
        $payrolls->finalize($payroll, $this->adminOf($company));
        $payrolls->create(2026, 10);

        app(EmployeeService::class)->exit($leaving, [
            'exit_date' => '2026-09-30', 'last_working_date' => '2026-09-30', 'exit_type' => 'resignation', 'exit_reason' => 'Relocation',
        ]);

        EmployeeDocument::query()->create([
            'employee_id' => $employee->id, 'title' => 'Employment contract', 'type' => 'Contract',
            'file_path' => 'employee-documents/contract.pdf', 'original_name' => 'contract.pdf', 'size' => 2048,
            'uploaded_by' => $this->adminOf($company)->id,
        ]);

        return [$company, [
            '{company}' => (string) $company->id,
            '{department}' => (string) $department->id,
            '{employee}' => (string) $employee->id,
            '{pastEmployee}' => (string) $leaving->id,
            '{payroll}' => (string) Payroll::query()->where('period_month', 9)->sole()->id,
            '{item}' => (string) $item->id,
            '{borrow}' => (string) $borrow->id,
        ]];
    }
}
