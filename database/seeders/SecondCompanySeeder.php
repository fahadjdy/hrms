<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use App\Models\WeeklyHoliday;
use App\Services\AttendanceCalculationService;
use App\Services\BorrowCalculationService;
use App\Services\CompanyProvisioner;
use App\Services\EmployeeService;
use App\Services\PayrollService;
use App\Services\WorkingCalendarService;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\Concerns\SeedsTenantData;
use Illuminate\Database\Seeder;

/**
 * "Globex Industries": a small second tenant with a different currency,
 * timezone, date format and attendance mode. It exists so tenant isolation is
 * visible: nothing of Acme shows up here, and nothing of Globex shows up there.
 */
class SecondCompanySeeder extends Seeder
{
    use SeedsTenantData;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanyProvisioner $provisioner,
        private readonly EmployeeService $employeeService,
        private readonly BorrowCalculationService $borrowService,
        private readonly AttendanceCalculationService $attendance,
        private readonly PayrollService $payrolls,
        private readonly WorkingCalendarService $calendar,
    ) {}

    public function run(): void
    {
        $company = $this->provisioner->provision(
            [
                'name' => 'Globex Industries',
                'legal_name' => 'Globex Industries LLC',
                'email' => 'office@globex.test',
                'phone' => '+1 212 555 0148',
                'address' => '350 Hudson Street',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'United States',
                'postal_code' => '10014',
                'currency' => 'USD',
                'timezone' => 'America/New_York',
                'date_format' => 'm/d/Y',
            ],
            ['name' => 'Grace Miller', 'email' => 'admin@globex.test', 'password' => config('hrms.seed.demo_password')],
        );

        $this->tenant->run($company, function (): void {
            $admin = User::query()->where('email', 'admin@globex.test')->firstOrFail();
            auth()->setUser($admin);
            $this->realToday();

            WeeklyHoliday::query()->create(['day_of_week' => 6]);
            $this->calendar->flush();

            // Automatic mode: working days are present unless the admin records an exception.
            $this->tenant->settings()->update([
                'attendance_mode' => 'automatic',
                'salary_calculation_method' => 'calendar_days',
            ]);
            $this->tenant->flushSettings();

            $departments = collect(['Production', 'Warehouse', 'Administration'])
                ->mapWithKeys(fn (string $name): array => [$name => Department::query()->create(['name' => $name])->id]);
            $designations = collect(['Plant Supervisor', 'Machine Operator', 'Warehouse Associate', 'Office Administrator'])
                ->mapWithKeys(fn (string $name): array => [$name => Designation::query()->create(['name' => $name])->id]);

            // code, first, last, gender, department, designation, joining, monthly salary
            $definitions = [
                ['GLX-001', 'Daniel', 'Brooks', 'male', 'Production', 'Plant Supervisor', $this->month(38, 6), 6800],
                ['GLX-002', 'Maria', 'Alvarez', 'female', 'Production', 'Machine Operator', $this->month(21, 14), 4200],
                ['GLX-003', 'Owen', 'Carter', 'male', 'Production', 'Machine Operator', $this->month(9, 3), 4000],
                ['GLX-004', 'Hannah', 'Lee', 'female', 'Warehouse', 'Warehouse Associate', $this->month(14, 20), 3600],
                ['GLX-005', 'Marcus', 'Reed', 'male', 'Warehouse', 'Warehouse Associate', $this->month(4, 10), 3500],
                ['GLX-006', 'Emily', 'Stone', 'female', 'Administration', 'Office Administrator', $this->month(27, 1), 4800],
            ];

            /** @var array<string, Employee> $employees */
            $employees = [];

            foreach ($definitions as [$code, $first, $last, $gender, $department, $designation, $joining, $salary]) {
                $employees[$code] = $this->at($joining, fn (): Employee => $this->employeeService->create([
                    'employee_code' => $code,
                    'first_name' => $first,
                    'last_name' => $last,
                    'gender' => $gender,
                    'email' => strtolower("{$first}.{$last}@globex.test"),
                    'city' => 'New York',
                    'country' => 'United States',
                    'joining_date' => $joining->toDateString(),
                    'department_id' => $departments[$department],
                    'designation_id' => $designations[$designation],
                    'employment_type' => 'full_time',
                    'status' => 'active',
                    'salary_components' => [
                        ['name' => 'Base Pay', 'type' => 'earning', 'amount' => $salary],
                        ['name' => 'Health Plan', 'type' => 'deduction', 'amount' => 150],
                    ],
                ], null, $admin));
            }

            $today = $this->realToday();

            // Stored by the service itself: weekly offs plus automatic present days.
            $this->attendance->generateForRange($this->month(2), $today);

            // A few exceptions recorded by the admin.
            foreach ([
                ['GLX-003', $this->month(1, 9), ['status' => 'absent']],
                ['GLX-004', $this->month(1, 16), ['status' => 'present', 'check_in' => '09:40', 'check_out' => '18:05']],
                ['GLX-005', $this->month(1, 23), ['status' => 'present', 'check_in' => '09:00', 'check_out' => '16:30']],
                ['GLX-002', $this->month(1, 11), ['status' => 'present', 'check_in' => '08:55', 'check_out' => '20:10']],
            ] as [$code, $date, $input]) {
                $date = $this->workday($date);
                $this->at($date, fn () => $this->attendance->record($employees[$code], $date, $input, $admin), '19:00:00');
            }

            $borrowDate = $this->month(2, 18);
            $this->at($borrowDate, fn () => $this->borrowService->create($employees['GLX-005'], [
                'kind' => 'new',
                'amount' => 1500,
                'borrow_date' => $borrowDate->toDateString(),
                'reason' => 'Car repair',
                'monthly_deduction' => 300,
                'deduction_start_month' => $this->month(1)->toDateString(),
            ], $admin));

            $lastMonth = $this->month(1);
            $this->at($lastMonth->endOfMonth(), function () use ($lastMonth, $admin): void {
                $payroll = $this->payrolls->calculate($this->payrolls->create($lastMonth->year, $lastMonth->month, $admin));
                $this->payrolls->finalize($payroll, $admin);
            }, '18:00:00');

            auth()->forgetUser();
        });
    }
}
