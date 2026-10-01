<?php

namespace Database\Seeders;

use App\Enums\DesignationChangeType;
use App\Enums\PayrollBucket;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Overtime;
use App\Models\PayrollItem;
use App\Models\Role;
use App\Models\SalaryBonus;
use App\Models\SalaryDeduction;
use App\Models\User;
use App\Models\WeeklyHoliday;
use App\Models\WorkShift;
use App\Services\BorrowCalculationService;
use App\Services\CompanyProvisioner;
use App\Services\DesignationChangeService;
use App\Services\EmployeeService;
use App\Services\FinalSettlementService;
use App\Services\LeaveService;
use App\Services\OvertimeCalculationService;
use App\Services\PayrollService;
use App\Services\SalaryRevisionService;
use App\Services\WorkingCalendarService;
use App\Services\WorkingHoursCalculationService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsTenantData;
use Illuminate\Database\Seeder;

/**
 * "Acme Technologies": a full demo company with four months of history.
 *
 * Everything goes through the application's own services, in the order it
 * would have happened, so balances, payroll and audit history are exactly what
 * the app itself would have produced. All dates are relative to today.
 */
class DemoCompanySeeder extends Seeder
{
    use SeedsTenantData;

    /** Months of attendance history before the current month. */
    private const int HISTORY_MONTHS = 4;

    /**
     * Salary history: the starting salary as a share of today's, then each
     * revision with its effective date (months back, day), share and reason.
     */
    private const array SALARY_HISTORY = [
        'EMP-0002' => ['initial' => 0.80, 'steps' => [[9, 1, 0.90, 'Annual increment'], [2, 1, 1.00, 'Promotion to Engineering Manager']]],
        'EMP-0006' => ['initial' => 0.88, 'steps' => [[6, 1, 1.00, 'Annual increment']]],
        'EMP-0009' => ['initial' => 0.85, 'steps' => [[12, 1, 0.93, 'Annual increment'], [1, 16, 1.00, 'Revised after sales target review']]],
        'EMP-0014' => ['initial' => 0.90, 'steps' => [[3, 1, 1.00, 'Annual increment']]],
        'EMP-0016' => ['initial' => 0.82, 'steps' => [[14, 1, 0.91, 'Annual increment'], [2, 1, 1.00, 'Annual increment']]],
        'EMP-0023' => ['initial' => 0.90, 'steps' => [[8, 1, 1.00, 'Annual increment']]],
        'EMP-0003' => ['initial' => 0.90, 'steps' => [[2, 1, 1.00, 'Performance increment']]],
        'EMP-0025' => ['initial' => 0.92, 'steps' => [[5, 1, 1.00, 'Annual increment']]],
    ];

    private User $admin;

    private CarbonImmutable $today;

    /** @var array<string, Employee> keyed by employee code */
    private array $employees = [];

    /** @var array<string, EmployeeBorrow> keyed by a short label */
    private array $borrows = [];

    /** @var array<string, array{0: float|int, 1: float|int, 2: float|int, 3: bool}> today's basic, HRA, allowance and professional tax by employee code */
    private array $currentSalary = [];

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanyProvisioner $provisioner,
        private readonly EmployeeService $employeeService,
        private readonly DesignationChangeService $designationChanges,
        private readonly SalaryRevisionService $salaries,
        private readonly BorrowCalculationService $borrowService,
        private readonly LeaveService $leaves,
        private readonly OvertimeCalculationService $overtime,
        private readonly PayrollService $payrolls,
        private readonly FinalSettlementService $settlements,
        private readonly WorkingCalendarService $calendar,
        private readonly WorkingHoursCalculationService $hours,
    ) {}

    public function run(): void
    {
        $company = $this->provisioner->provision(
            [
                'name' => 'Acme Technologies',
                'legal_name' => 'Acme Technologies Private Limited',
                'email' => 'contact@acme.test',
                'phone' => '+91 20 4000 1200',
                'address' => '4th Floor, Orchid Business Park, Baner Road',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'country' => 'India',
                'postal_code' => '411045',
                'tax_id' => '27AAECA1234F1Z5',
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'date_format' => 'd M Y',
            ],
            ['name' => 'Aarav Shah', 'email' => 'admin@acme.test', 'password' => config('hrms.seed.demo_password')],
        );

        $this->tenant->run($company, function () use ($company): void {
            $this->admin = User::query()->where('email', 'admin@acme.test')->firstOrFail();
            $this->today = $this->realToday();

            // Audit entries and "changed by" fields carry the Company Admin.
            auth()->setUser($this->admin);

            $this->configureCompany($company);
            $this->createEmployees();
            $this->promoteEmployees();
            $this->reviseSalaries();
            $this->seedAttendanceHistory();
            $this->seedLeaves();
            $this->seedBorrows();
            $this->seedFinanceEntries();
            $this->runPayrollHistory();
            $this->seedCurrentMonth();

            auth()->forgetUser();
        });
    }

    /**
     * Users, weekly offs, shifts, departments, designations, holidays and settings.
     */
    private function configureCompany(Company $company): void
    {
        $roles = Role::query()->pluck('id', 'slug');

        foreach ([
            ['Meera Joshi', 'hr@acme.test', 'hr-manager'],
            ['Vivek Anand', 'viewer@acme.test', 'viewer'],
        ] as [$name, $email, $slug]) {
            $this->provisioner->createAdmin(
                $company,
                Role::query()->findOrFail((int) $roles[$slug]),
                ['name' => $name, 'email' => $email, 'password' => config('hrms.seed.demo_password')],
            );
        }

        // Sunday is provisioned by default; add Saturday.
        WeeklyHoliday::query()->create(['day_of_week' => 6]);

        $womens = WorkShift::query()->create([
            'name' => "Women's Shift", 'start_time' => '09:30', 'end_time' => '17:30',
            'required_minutes' => 420, 'break_minutes' => 60,
        ]);
        WorkShift::query()->create([
            'name' => 'Half Day Shift', 'start_time' => '09:00', 'end_time' => '13:00',
            'required_minutes' => 240, 'break_minutes' => 0,
        ]);

        $this->tenant->settings()->update([
            'attendance_mode' => 'manual',
            'female_work_shift_id' => $womens->id,
            'grace_minutes' => 10,
            'salary_calculation_method' => 'working_days',
            'short_hours_mode' => 'deduct',
        ]);
        $this->tenant->flushSettings();

        foreach ([
            'Engineering' => 'Product development and quality',
            'Sales' => 'Business development and accounts',
            'Human Resources' => 'People operations',
            'Finance' => 'Accounts and payroll',
            'Customer Support' => 'Helpdesk and customer success',
            'Operations' => 'Office and logistics',
        ] as $name => $description) {
            Department::query()->create(['name' => $name, 'description' => $description]);
        }

        foreach ([
            'Software Engineer', 'Senior Software Engineer', 'Engineering Manager', 'QA Engineer', 'Sales Executive',
            'Sales Manager', 'HR Executive', 'Accountant', 'Support Executive', 'Operations Executive',
        ] as $name) {
            Designation::query()->create(['name' => $name]);
        }

        $year = $this->today->year;

        foreach ([
            ['01-26', 'Republic Day', 'public'],
            ['03-04', 'Holi', 'public'],
            ['04-03', 'Good Friday', 'optional'],
            ['05-01', 'Maharashtra Day', 'public'],
            ['07-17', 'Founders Day', 'company'],
            ['08-15', 'Independence Day', 'public'],
            ['08-28', 'Raksha Bandhan', 'optional'],
            ['10-02', 'Gandhi Jayanti', 'public'],
            ['10-20', 'Dussehra', 'public'],
            ['11-09', 'Diwali', 'public'],
            ['12-25', 'Christmas', 'public'],
        ] as [$monthDay, $name, $type]) {
            Holiday::query()->create(['name' => $name, 'date' => "{$year}-{$monthDay}", 'type' => $type]);
        }

        $this->calendar->flush();
        $this->hours->flush();
    }

    /**
     * 28 current employees plus two who leave during the story. Managers
     * come first so reporting lines can be set at creation.
     */
    private function createEmployees(): void
    {
        $departments = Department::query()->pluck('id', 'name');
        $designations = Designation::query()->pluck('id', 'name');
        $shifts = WorkShift::query()->pluck('id', 'name');
        $joiningThisMonth = $this->today->day <= 2 ? $this->today : $this->month(0, 2);

        // code, first, last, gender, department, designation, type, status, joining, basic, HRA, allowance, professional tax, manager
        $definitions = [
            ['EMP-0002', 'Priya', 'Nair', 'female', 'Engineering', 'Engineering Manager', 'full_time', 'active', $this->month(52, 4), 78000, 22000, 10000, true, null],
            ['EMP-0009', 'Vikram', 'Singh', 'male', 'Sales', 'Sales Executive', 'full_time', 'active', $this->month(44, 12), 62000, 18000, 8000, true, null],
            ['EMP-0014', 'Meera', 'Joshi', 'female', 'Human Resources', 'HR Executive', 'full_time', 'active', $this->month(36, 1), 38000, 10000, 4000, false, null],
            ['EMP-0016', 'Lakshmi', 'Rao', 'female', 'Finance', 'Accountant', 'full_time', 'active', $this->month(48, 15), 45000, 12000, 5000, true, null],
            ['EMP-0022', 'Ritu', 'Agarwal', 'female', 'Customer Support', 'Support Executive', 'full_time', 'active', $this->month(30, 8), 26000, 7000, 2500, false, null],
            ['EMP-0023', 'Sanjay', 'Patil', 'male', 'Operations', 'Operations Executive', 'full_time', 'active', $this->month(40, 20), 34000, 9000, 3500, false, null],

            ['EMP-0001', 'Rahul', 'Sharma', 'male', 'Engineering', 'Senior Software Engineer', 'full_time', 'active', $this->month(4, 15), 35000, 5000, 2000, false, 'EMP-0002'],
            ['EMP-0003', 'Amit', 'Verma', 'male', 'Engineering', 'Software Engineer', 'full_time', 'active', $this->month(26, 3), 42000, 12000, 4000, false, 'EMP-0002'],
            ['EMP-0004', 'Sneha', 'Iyer', 'female', 'Engineering', 'QA Engineer', 'full_time', 'active', $this->month(19, 10), 36000, 10000, 3000, false, 'EMP-0002'],
            ['EMP-0005', 'Karthik', 'Reddy', 'male', 'Engineering', 'Software Engineer', 'full_time', 'active', $this->month(9, 2), 40000, 11000, 3000, false, 'EMP-0002'],
            ['EMP-0006', 'Neha', 'Gupta', 'female', 'Engineering', 'Software Engineer', 'full_time', 'active', $this->month(33, 18), 58000, 16000, 6000, true, 'EMP-0002'],
            ['EMP-0007', 'Arjun', 'Mehta', 'male', 'Engineering', 'Software Engineer', 'full_time', 'probation', $this->month(3, 6), 30000, 8000, 2000, false, 'EMP-0002'],
            ['EMP-0008', 'Divya', 'Menon', 'female', 'Engineering', 'QA Engineer', 'full_time', 'probation', $this->month(2, 3), 28000, 7000, 2000, false, 'EMP-0002'],
            ['EMP-0010', 'Pooja', 'Desai', 'female', 'Sales', 'Sales Executive', 'full_time', 'active', $this->month(15, 5), 30000, 8000, 3000, false, 'EMP-0009'],
            ['EMP-0011', 'Rohan', 'Kapoor', 'male', 'Sales', 'Sales Executive', 'full_time', 'active', $this->month(7, 11), 28000, 7500, 2500, false, 'EMP-0009'],
            ['EMP-0012', 'Ananya', 'Bose', 'female', 'Sales', 'Sales Executive', 'full_time', 'active', $this->month(1, 1), 27000, 7000, 2000, false, 'EMP-0009'],
            ['EMP-0013', 'Suresh', 'Pillai', 'male', 'Sales', 'Sales Executive', 'full_time', 'notice', $this->month(28, 22), 32000, 8500, 3000, false, 'EMP-0009'],
            ['EMP-0015', 'Farhan', 'Khan', 'male', 'Human Resources', 'Support Executive', 'full_time', 'active', $this->month(11, 7), 33000, 9000, 3000, false, 'EMP-0014'],
            ['EMP-0017', 'Manoj', 'Tiwari', 'male', 'Finance', 'Accountant', 'full_time', 'active', $this->month(22, 14), 36000, 9500, 3500, false, 'EMP-0016'],
            ['EMP-0018', 'Kavita', 'Chawla', 'female', 'Finance', 'Accountant', 'contract', 'active', $this->month(5, 9), 31000, 8000, 2500, false, 'EMP-0016'],
            ['EMP-0019', 'Imran', 'Sheikh', 'male', 'Customer Support', 'Support Executive', 'part_time', 'active', $this->month(17, 2), 14000, 3000, 1000, false, 'EMP-0022'],
            ['EMP-0020', 'Deepa', 'Krishnan', 'female', 'Customer Support', 'Support Executive', 'full_time', 'active', $this->month(13, 19), 23000, 6000, 2000, false, 'EMP-0022'],
            ['EMP-0021', 'Nikhil', 'Jain', 'male', 'Customer Support', 'Support Executive', 'full_time', 'active', $this->month(6, 16), 22000, 5500, 1500, false, 'EMP-0022'],
            ['EMP-0024', 'Harpreet', 'Kaur', 'female', 'Operations', 'Operations Executive', 'full_time', 'active', $this->month(10, 6), 29000, 7500, 2500, false, 'EMP-0023'],
            ['EMP-0025', 'Gaurav', 'Malhotra', 'male', 'Operations', 'Operations Executive', 'full_time', 'active', $this->month(24, 1), 31000, 8000, 3000, false, 'EMP-0023'],
            ['EMP-0026', 'Aisha', 'Siddiqui', 'female', 'Engineering', 'Software Engineer', 'full_time', 'active', $joiningThisMonth, 38000, 10000, 3000, false, 'EMP-0002'],
            ['EMP-0027', 'Tarun', 'Bhatt', 'male', 'Sales', 'Sales Executive', 'full_time', 'active', $joiningThisMonth, 27000, 7000, 2000, false, 'EMP-0009'],
            ['EMP-0028', 'Shreya', 'Kulkarni', 'female', 'Human Resources', 'HR Executive', 'intern', 'active', $this->month(2, 17), 15000, 0, 1000, false, 'EMP-0014'],
            ['EMP-0029', 'Rajesh', 'Kumar', 'male', 'Operations', 'Operations Executive', 'full_time', 'active', $this->month(31, 9), 30000, 8000, 2500, false, 'EMP-0023'],
            ['EMP-0030', 'Nisha', 'Pandey', 'female', 'Customer Support', 'Support Executive', 'full_time', 'active', $this->month(14, 4), 25000, 6500, 2000, false, 'EMP-0022'],
        ];

        $probationEnds = ['EMP-0007' => $this->today->addDays(12), 'EMP-0008' => $this->today->addDays(26)];

        // Employee-specific timing takes priority over the gender and company defaults.
        $ownShifts = ['EMP-0019' => $shifts['Half Day Shift'], 'EMP-0018' => $shifts['General Shift']];

        // Borrows the employee already had when joining the company.
        $existingBorrows = [
            'EMP-0001' => [
                'amount' => 20000, 'opening_balance' => 20000, 'monthly_deduction' => 5000,
                'deduction_start_month' => $this->month(3)->toDateString(),
                'reason' => 'Personal advance carried over at joining', 'source_reference' => 'Joining letter annexure B',
            ],
            'EMP-0011' => [
                'amount' => 30000, 'opening_balance' => 18000, 'monthly_deduction' => 3000,
                'borrow_date' => $this->month(10, 5)->toDateString(),
                'deduction_start_month' => $this->month(3)->toDateString(),
                'reason' => 'Advance from previous employer taken over', 'source_reference' => 'Relieving letter - Orbit Sales',
            ],
            'EMP-0012' => [
                'amount' => 10000, 'opening_balance' => 10000, 'monthly_deduction' => 2500,
                'deduction_start_month' => $this->month(1)->toDateString(),
                'reason' => 'Relocation advance agreed at offer', 'source_reference' => 'Offer letter clause 7',
            ],
        ];

        $cities = [['Pune', 'Maharashtra'], ['Mumbai', 'Maharashtra'], ['Bengaluru', 'Karnataka'], ['Hyderabad', 'Telangana'], ['Nashik', 'Maharashtra']];

        foreach ($definitions as $index => [$code, $first, $last, $gender, $department, $designation, $type, $status, $joining, $basic, $hra, $allowance, $tax, $manager]) {
            [$city, $state] = $cities[$index % count($cities)];
            $factor = self::SALARY_HISTORY[$code]['initial'] ?? 1.0;
            $this->currentSalary[$code] = [$basic, $hra, $allowance, $tax];

            $this->employees[$code] = $this->at($joining, fn (): Employee => $this->employeeService->create([
                'employee_code' => $code,
                'first_name' => $first,
                'last_name' => $last,
                'gender' => $gender,
                'date_of_birth' => CarbonImmutable::create(1982 + ($index * 7) % 19, 1 + ($index * 5) % 12, 1 + ($index * 11) % 27)->toDateString(),
                'phone' => '9'.str_pad((string) mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT),
                'email' => strtolower("{$first}.{$last}@acme.test"),
                'address' => (12 + $index * 3).', '.['MG Road', 'Lake View Residency', 'Green Park Society', 'Station Road'][$index % 4],
                'city' => $city,
                'state' => $state,
                'country' => 'India',
                'postal_code' => (string) (411001 + $index * 7),
                'joining_date' => $joining->toDateString(),
                'department_id' => $departments[$department],
                'designation_id' => $designations[$designation],
                'employment_type' => $type,
                'reporting_manager_id' => $manager !== null ? $this->employees[$manager]->id : null,
                'status' => $status,
                'probation_end_date' => isset($probationEnds[$code]) ? $probationEnds[$code]->toDateString() : null,
                'work_shift_id' => $ownShifts[$code] ?? null,
                'salary_components' => $this->components($basic, $hra, $allowance, $tax, $factor),
                'existing_borrow' => $existingBorrows[$code] ?? null,
            ], null, $this->admin));
        }
    }

    /**
     * Designation changes over the years: two promotions and one move
     * between departments' roles. The edit-form path is covered elsewhere.
     */
    private function promoteEmployees(): void
    {
        $designations = Designation::query()->pluck('id', 'name');

        // employee, new designation, type, months back, day, reason
        $changes = [
            ['EMP-0009', 'Sales Manager', DesignationChangeType::Promotion, 20, 1, 'Took over the sales team after the regional restructure'],
            ['EMP-0006', 'Senior Software Engineer', DesignationChangeType::Promotion, 14, 1, 'Annual review: led the billing platform rewrite'],
            ['EMP-0015', 'HR Executive', DesignationChangeType::Change, 5, 1, 'Moved from customer support to the HR team'],
        ];

        foreach ($changes as [$code, $designation, $type, $monthsBack, $day, $reason]) {
            $effective = $this->month($monthsBack, $day);

            $this->at($effective, fn () => $this->designationChanges->change(
                $this->employees[$code],
                Designation::query()->findOrFail((int) $designations[$designation]),
                $effective,
                $type,
                $reason,
                $this->admin,
            ));
        }
    }

    private function reviseSalaries(): void
    {
        foreach (self::SALARY_HISTORY as $code => $history) {
            $employee = $this->employees[$code];
            [$basic, $hra, $allowance, $tax] = $this->currentSalary[$code];

            foreach ($history['steps'] as [$monthsBack, $day, $factor, $reason]) {
                $effective = $this->month($monthsBack, $day);

                // Revisions are decided a few days before they take effect.
                $this->at($effective->subDays(4), fn () => $this->salaries->revise($employee, [
                    'effective_date' => $effective->toDateString(),
                    'reason' => $reason,
                    'components' => $this->components($basic, $hra, $allowance, $tax, $factor),
                ], $this->admin));
            }
        }

        // An approved raise that has not taken effect yet.
        $this->salaries->revise($this->employees['EMP-0001'], [
            'effective_date' => $this->today->startOfMonth()->addMonthNoOverflow()->toDateString(),
            'reason' => 'Promotion to Tech Lead',
            'notes' => 'Approved in the quarterly review; effective from next month.',
            'components' => $this->components(40000, 6000, 3000, false),
        ], $this->admin);
    }

    /**
     * Attendance from the start of the history window up to yesterday.
     */
    private function seedAttendanceHistory(): void
    {
        $from = $this->month(self::HISTORY_MONTHS);
        $yesterday = $this->today->subDay();

        $profiles = [
            'EMP-0002' => 'steady', 'EMP-0016' => 'steady', 'EMP-0006' => 'steady', 'EMP-0014' => 'steady',
            'EMP-0005' => 'overtime', 'EMP-0001' => 'overtime', 'EMP-0025' => 'overtime',
            'EMP-0021' => 'irregular', 'EMP-0013' => 'irregular', 'EMP-0011' => 'irregular',
            'EMP-0004' => 'remote', 'EMP-0003' => 'remote',
        ];

        $rows = [];

        foreach ($this->employees as $code => $employee) {
            $employee->load('shiftAssignments');

            // Rajesh leaves last month; nothing is recorded after his last working day.
            $to = $code === 'EMP-0029' ? $this->rajeshLastDay() : $yesterday;

            array_push($rows, ...$this->attendanceRows($employee, $from, $to, $profiles[$code] ?? 'regular', $this->admin->id));
        }

        $this->insertAttendance($rows);
    }

    /**
     * Leave over the history window, written into attendance by LeaveService.
     */
    private function seedLeaves(): void
    {
        $types = LeaveType::query()->pluck('id', 'code');

        // employee, type, start, extra days, half day, reason
        $approved = [
            ['EMP-0004', 'CL', $this->month(4, 9), 1, false, 'Family function'],
            ['EMP-0009', 'SL', $this->month(4, 17), 0, false, 'Viral fever'],
            ['EMP-0014', 'PL', $this->month(3, 20), 4, false, 'Annual vacation'],
            ['EMP-0021', 'UL', $this->month(3, 13), 1, false, 'Personal work, leave balance exhausted'],
            ['EMP-0022', 'SL', $this->month(3, 7), 0, true, 'Doctor appointment'],
            ['EMP-0005', 'CL', $this->month(2, 10), 1, false, 'Travel to home town'],
            ['EMP-0020', 'UL', $this->month(2, 20), 0, false, 'Urgent personal work'],
            ['EMP-0016', 'PL', $this->month(2, 24), 2, false, 'Family trip'],
            ['EMP-0019', 'UL', $this->month(2, 5), 0, true, 'Bank work'],
            ['EMP-0003', 'SL', $this->month(1, 8), 1, false, 'Flu'],
            ['EMP-0010', 'CL', $this->month(1, 22), 0, false, 'Personal errand'],
            ['EMP-0013', 'UL', $this->month(1, 14), 1, false, 'Extended personal leave'],
            ['EMP-0015', 'PL', $this->month(1, 24), 2, false, 'Wedding in the family'],
        ];

        foreach ($approved as [$code, $type, $start, $extraDays, $half, $reason]) {
            $start = $this->workday($start);

            $this->at($start->subDays(3), fn () => $this->leaves->create($this->employees[$code], [
                'leave_type_id' => $types[$type],
                'start_date' => $start->toDateString(),
                'end_date' => $start->addDays($extraDays)->toDateString(),
                'is_half_day' => $half,
                'reason' => $reason,
                'status' => 'approved',
            ], $this->admin));
        }

        // A request that was turned down.
        $rejectedStart = $this->workday($this->month(1, 10));
        $this->at($rejectedStart->subDays(5), function () use ($types, $rejectedStart): void {
            $leave = $this->leaves->create($this->employees['EMP-0025'], [
                'leave_type_id' => $types['PL'],
                'start_date' => $rejectedStart->toDateString(),
                'end_date' => $rejectedStart->addDays(4)->toDateString(),
                'reason' => 'Vacation during month-end closing',
            ], $this->admin);

            $this->leaves->reject($leave, $this->admin);
        });

        $this->leaves->setBalance($this->employees['EMP-0014'], LeaveType::query()->findOrFail((int) $types['PL']), $this->today->year, 18, 2);
    }

    /**
     * New borrows over the past months. Existing borrows were recorded at joining.
     */
    private function seedBorrows(): void
    {
        // label, employee, date, amount, monthly deduction, first deduction month, reason, salary month when paid with salary
        /** @var list<array{string, string, CarbonImmutable, int, int, CarbonImmutable|null, string, CarbonImmutable|null}> $definitions */
        $definitions = [
            ['gaurav-1', 'EMP-0025', $this->month(4, 10), 24000, 4000, $this->month(3), 'Medical expenses for a family member', null],
            ['imran', 'EMP-0019', $this->month(4, 20), 6000, 0, null, 'Short-term help, to be repaid in cash', null],
            ['rajesh', 'EMP-0029', $this->month(3, 8), 12000, 3000, $this->month(2), 'Two-wheeler repair', null],
            ['deepa', 'EMP-0020', $this->month(3, 15), 8000, 2000, $this->month(2), 'School fees', null],
            ['nisha', 'EMP-0030', $this->month(2, 5), 18000, 3000, $this->month(1), 'House deposit', null],
            ['gaurav-2', 'EMP-0025', $this->month(2, 12), 10000, 2000, $this->month(1), 'Festival advance', null],
            ['karthik', 'EMP-0005', $this->month(1, 6), 5000, 2500, $this->month(0), 'Laptop for personal study', null],
            ['rahul-2', 'EMP-0001', $this->month(1, 20), 15000, 3000, $this->month(0), 'Home renovation', null],
            ['manoj', 'EMP-0017', $this->month(1, 22), 12000, 3000, $this->month(0), 'Advance requested with salary', $this->month(1)],
        ];

        foreach ($definitions as [$label, $code, $date, $amount, $monthly, $startMonth, $reason, $salaryMonth]) {
            $this->borrows[$label] = $this->at($date, fn (): EmployeeBorrow => $this->borrowService->create($this->employees[$code], [
                'kind' => 'new',
                'amount' => $amount,
                'borrow_date' => $date->toDateString(),
                'reason' => $reason,
                'monthly_deduction' => $monthly,
                'deduction_start_month' => $startMonth?->toDateString(),
                'disbursement_method' => $salaryMonth !== null ? 'with_salary' : 'direct',
                'disburse_period' => $salaryMonth?->toDateString(),
            ], $this->admin));
        }
    }

    /**
     * Overtime, bonuses, other earnings and one-off deductions for the payroll months.
     */
    private function seedFinanceEntries(): void
    {
        // employee, date, hours, rate, fixed amount, status, reason
        $overtime = [
            ['EMP-0005', $this->month(3, 9), 5, 200, null, 'approved', 'Release weekend support'],
            ['EMP-0003', $this->month(3, 18), null, null, 1500, 'approved', 'Server migration, fixed amount'],
            ['EMP-0019', $this->month(3, 22), 3, 150, null, 'approved', 'Covered the evening helpdesk'],
            ['EMP-0023', $this->month(3, 25), 4, 220, null, 'rejected', 'Not approved in advance'],
            ['EMP-0001', $this->month(2, 8), 6, 260, null, 'approved', 'Production incident'],
            ['EMP-0020', $this->month(2, 19), 2.5, 160, null, 'approved', 'Customer escalation'],
            ['EMP-0021', $this->month(2, 27), null, null, 1000, 'approved', 'Stock audit support, fixed amount'],
            ['EMP-0005', $this->month(1, 12), 4, 200, null, 'approved', 'Sprint deadline'],
            ['EMP-0025', $this->month(1, 23), 5, 190, null, 'approved', 'Office relocation'],
            ['EMP-0001', $this->month(1, 26), 3, 260, null, 'approved', 'Deployment window'],
            ['EMP-0017', $this->month(1, 29), null, null, 1200, 'approved', 'Quarter-end closing, fixed amount'],
        ];

        foreach ($overtime as [$code, $date, $hours, $rate, $fixed, $status, $reason]) {
            $this->at($date, fn () => $this->addOvertime($code, $date, $hours, $rate, $fixed, $status, $reason));
        }

        // employee, date, type, title, amount, reason
        $bonuses = [
            ['EMP-0009', $this->month(3, 28), 'bonus', 'Quarterly sales incentive', 8000, 'Team exceeded the quarterly target'],
            ['EMP-0010', $this->month(2, 14), 'bonus', 'Referral bonus', 5000, 'Referred a new hire who completed 90 days'],
            ['EMP-0014', $this->month(2, 21), 'other_earning', 'Travel reimbursement', 1800, 'Campus hiring visit'],
            ['EMP-0011', $this->month(1, 25), 'bonus', 'Sales target bonus', 4000, 'Closed the Meridian account'],
            ['EMP-0019', $this->month(1, 18), 'other_earning', 'Mobile reimbursement', 600, 'Support line usage'],
        ];

        foreach (['EMP-0003', 'EMP-0004', 'EMP-0020', 'EMP-0022', 'EMP-0024'] as $code) {
            $bonuses[] = [$code, $this->month(1, 15), 'bonus', 'Festival bonus', 2000, 'Festival bonus for the season'];
        }

        foreach ($bonuses as [$code, $date, $type, $title, $amount, $reason]) {
            $this->at($date, fn () => SalaryBonus::query()->create([
                'employee_id' => $this->employees[$code]->id,
                'date' => $date->toDateString(),
                'type' => $type,
                'title' => $title,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => $this->admin->id,
            ]));
        }

        // employee, date, title, amount, reason
        $deductions = [
            ['EMP-0021', $this->month(3, 11), 'Uniform', 500, 'Second uniform set'],
            ['EMP-0013', $this->month(2, 18), 'Damaged laptop charger', 1200, 'Replacement cost'],
            ['EMP-0022', $this->month(1, 9), 'ID card replacement', 300, 'Lost access card'],
        ];

        foreach ($deductions as [$code, $date, $title, $amount, $reason]) {
            $this->at($date, fn () => SalaryDeduction::query()->create([
                'employee_id' => $this->employees[$code]->id,
                'date' => $date->toDateString(),
                'title' => $title,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => $this->admin->id,
            ]));
        }
    }

    /**
     * The three previous months, in order: recoveries outside payroll, an
     * employee exit with its settlement, then each month's payroll run and
     * finalized at month end so borrow recoveries post month by month.
     */
    private function runPayrollHistory(): void
    {
        foreach ([3, 2, 1] as $monthsBack) {
            $monthStart = $this->month($monthsBack);

            if ($monthsBack === 3) {
                $this->at($this->month(3, 10), fn () => $this->borrowService->recover(
                    $this->borrows['imran'], 3000, $this->month(3, 10), notes: 'Cash returned by the employee', user: $this->admin,
                ));
            }

            if ($monthsBack === 2) {
                $this->at($this->month(2, 12), fn () => $this->borrowService->recover(
                    $this->borrows['imran']->refresh(), 3000, $this->month(2, 12), notes: 'Final cash repayment', user: $this->admin,
                ));
            }

            if ($monthsBack === 1) {
                $this->exitRajesh();
            }

            $this->at($monthStart->endOfMonth(), function () use ($monthStart, $monthsBack): void {
                $payroll = $this->payrolls->calculate(
                    $this->payrolls->create($monthStart->year, $monthStart->month, $this->admin),
                );

                if ($monthsBack === 2) {
                    $this->payrolls->markUnderReview($payroll);
                    $this->adjust($payroll->id, 'EMP-0006', PayrollBucket::Bonus, 2500, 'Spot award approved by the engineering manager');
                    $this->adjust($payroll->id, 'EMP-0024', PayrollBucket::OtherDeductions, 300, 'Courier charges paid on behalf of the employee');
                }

                if ($monthsBack === 1) {
                    $this->payrolls->markUnderReview($payroll);
                    $this->adjust($payroll->id, 'EMP-0025', PayrollBucket::BorrowRecovery, -1000, 'Employee requested a lower deduction this month');
                }

                $this->payrolls->finalize($payroll, $this->admin);
            }, '18:00:00');
        }
    }

    /**
     * Rajesh resigns last month; his settlement is prepared, adjusted, finalized and paid.
     */
    private function exitRajesh(): void
    {
        $lastDay = $this->rajeshLastDay();
        $employee = $this->employees['EMP-0029'];

        $this->at($lastDay, fn () => $this->employeeService->exit($employee, [
            'exit_date' => $lastDay->toDateString(),
            'last_working_date' => $lastDay->toDateString(),
            'exit_type' => 'resignation',
            'exit_reason' => 'Relocating to another city',
            'exit_notes' => 'Served the full notice period. Assets returned.',
        ]));

        $this->at($lastDay->addDays(4), function () use ($employee): void {
            $settlement = $this->settlements->prepare($employee, $this->admin);
            $settlement = $this->settlements->adjust($settlement, 1500, 'Encashment of unused paid leave', 'Three days of paid leave encashed.');
            $this->settlements->finalize($settlement, $this->admin);
        });

        $this->at($lastDay->addDays(7), fn () => $this->settlements->markPaid($employee->finalSettlement()->firstOrFail()));
    }

    /**
     * The current month: today's attendance, open requests, an exit with a
     * draft settlement and a calculated (not finalized) payroll.
     */
    private function seedCurrentMonth(): void
    {
        $types = LeaveType::query()->pluck('id', 'code');
        $today = $this->today;

        // On leave today.
        $leaveStart = $this->workday($today);
        $this->leaves->create($this->employees['EMP-0023'], [
            'leave_type_id' => $types['PL'],
            'start_date' => $leaveStart->toDateString(),
            'end_date' => $leaveStart->addDays(2)->toDateString(),
            'reason' => 'Family event',
            'status' => 'approved',
        ], $this->admin);

        // Requests still waiting for a decision.
        foreach ([
            ['EMP-0024', 'CL', 6, 1, 'Personal work'],
            ['EMP-0006', 'PL', 12, 4, 'Planned vacation'],
        ] as [$code, $type, $inDays, $extraDays, $reason]) {
            $start = $this->workday($today->addDays($inDays));

            $this->leaves->create($this->employees[$code], [
                'leave_type_id' => $types[$type],
                'start_date' => $start->toDateString(),
                'end_date' => $start->addDays($extraDays)->toDateString(),
                'reason' => $reason,
            ], $this->admin);
        }

        // Nisha's last day is today; her settlement stays a draft with borrow still outstanding.
        $nisha = $this->employees['EMP-0030'];
        $this->markToday(['EMP-0030' => 'present']);
        $this->employeeService->exit($nisha, [
            'exit_date' => $today->toDateString(),
            'last_working_date' => $today->toDateString(),
            'exit_type' => 'resignation',
            'exit_reason' => 'Pursuing higher studies',
        ]);
        $this->settlements->prepare($nisha, $this->admin);

        // A new borrow to be paid out with this month's salary.
        $this->borrowService->create($this->employees['EMP-0024'], [
            'kind' => 'new',
            'amount' => 10000,
            'borrow_date' => $today->toDateString(),
            'reason' => 'Advance for a medical procedure',
            'monthly_deduction' => 2500,
            'deduction_start_month' => $today->startOfMonth()->addMonthNoOverflow()->toDateString(),
            'disbursement_method' => 'with_salary',
            'disburse_period' => $today->startOfMonth()->toDateString(),
        ], $this->admin);

        $this->addOvertime('EMP-0004', $today, null, null, 800, 'approved', 'Regression run after hours, fixed amount');
        $this->addOvertime('EMP-0003', $today, 2, 250, null, 'pending', 'Hotfix deployment');
        $this->addOvertime('EMP-0010', $today, 3, 180, null, 'pending', 'Client demo preparation');

        SalaryBonus::query()->create([
            'employee_id' => $this->employees['EMP-0006']->id, 'date' => $today->toDateString(), 'type' => 'bonus',
            'title' => 'Spot award', 'amount' => 3000, 'reason' => 'Mentoring the new joiners', 'created_by' => $this->admin->id,
        ]);
        SalaryBonus::query()->create([
            'employee_id' => $this->employees['EMP-0015']->id, 'date' => $today->toDateString(), 'type' => 'other_earning',
            'title' => 'Internet reimbursement', 'amount' => 1000, 'reason' => 'Work-from-home broadband', 'created_by' => $this->admin->id,
        ]);
        SalaryDeduction::query()->create([
            'employee_id' => $this->employees['EMP-0007']->id, 'date' => $today->toDateString(),
            'title' => 'Canteen dues', 'amount' => 450, 'reason' => 'Last month canteen bill', 'created_by' => $this->admin->id,
        ]);

        // Part of the staff is marked for today; the rest are still unmarked.
        $this->markToday([
            'EMP-0002' => 'present', 'EMP-0001' => 'present', 'EMP-0003' => 'wfh', 'EMP-0004' => 'present',
            'EMP-0005' => 'late', 'EMP-0006' => 'present', 'EMP-0007' => 'present', 'EMP-0009' => 'present',
            'EMP-0010' => 'present', 'EMP-0011' => 'absent', 'EMP-0012' => 'present', 'EMP-0014' => 'present',
            'EMP-0015' => 'wfh', 'EMP-0016' => 'present', 'EMP-0017' => 'present', 'EMP-0019' => 'present',
            'EMP-0020' => 'late', 'EMP-0021' => 'absent', 'EMP-0022' => 'present', 'EMP-0025' => 'present',
            'EMP-0026' => 'present', 'EMP-0027' => 'present',
        ]);

        $this->payrolls->calculate(
            $this->payrolls->create($today->year, $today->month, $this->admin, 'Running payroll for the current month.'),
        );
    }

    /**
     * Mark today's attendance for the given employees, when today is a working day.
     *
     * @param  array<string, string>  $kinds  employee code => present, late, wfh or absent
     */
    private function markToday(array $kinds): void
    {
        if (! $this->calendar->isWorkingDay($this->today)) {
            return;
        }

        $rows = [];

        foreach ($kinds as $code => $kind) {
            $employee = $this->employees[$code];

            if (! $employee->isEmployedOn($this->today)) {
                continue;
            }

            $values = $this->workingDayValues($this->hours->shiftFor($employee, $this->today), $kind);

            // Nobody has checked out yet today, so no check-out or totals from times.
            if (isset($values['check_out'])) {
                $late = $values['late_minutes'] > 0;
                $values = [
                    'status' => $late ? 'late' : 'present',
                    'required_minutes' => $values['required_minutes'],
                    'worked_minutes' => $values['required_minutes'],
                    'late_minutes' => 0,
                    'work_shift_id' => $values['work_shift_id'],
                ];
            }

            $rows[] = $this->attendanceRow($employee->company_id, $employee->id, $this->today, $values, 'manual', $this->admin->id);
        }

        $this->insertAttendance($rows);
    }

    private function adjust(int $payrollId, string $code, PayrollBucket $bucket, float $amount, string $reason): void
    {
        $item = PayrollItem::query()
            ->where('payroll_id', $payrollId)
            ->where('employee_id', $this->employees[$code]->id)
            ->with(['payroll', 'employee'])
            ->firstOrFail();

        $this->payrolls->addAdjustment($item, $bucket, $amount, $reason, $this->admin);
    }

    private function addOvertime(string $code, CarbonImmutable $date, int|float|null $hours, ?int $rate, ?int $fixed, string $status, string $reason): void
    {
        $type = $fixed !== null ? Overtime::TYPE_FIXED : Overtime::TYPE_HOURLY;

        Overtime::query()->create([
            'employee_id' => $this->employees[$code]->id,
            'date' => $date->toDateString(),
            'calculation_type' => $type,
            'hours' => $hours,
            'rate' => $rate,
            'amount' => $this->overtime->entryAmount($type, $hours, $rate, $fixed),
            'reason' => $reason,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * @return list<array{name: string, type: string, amount: float}>
     */
    private function components(float $basic, float $hra, float $allowance, bool $professionalTax, float $factor = 1.0): array
    {
        return array_filter([
            ['name' => 'Basic', 'type' => 'earning', 'amount' => $this->roundSalary($basic * $factor)],
            ['name' => 'HRA', 'type' => 'earning', 'amount' => $this->roundSalary($hra * $factor)],
            ['name' => 'Other Allowance', 'type' => 'earning', 'amount' => $this->roundSalary($allowance * $factor)],
            $professionalTax ? ['name' => 'Professional Tax', 'type' => 'deduction', 'amount' => 200.0] : null,
        ]);
    }

    private function rajeshLastDay(): CarbonImmutable
    {
        return $this->workday($this->month(1, 18));
    }
}
