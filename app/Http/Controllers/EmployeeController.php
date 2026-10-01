<?php

namespace App\Http\Controllers;

use App\Enums\BorrowStatus;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\ExitType;
use App\Enums\Gender;
use App\Http\Requests\EmployeeRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use App\Models\WorkShift;
use App\Services\AttendanceCalculationService;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Services\SalaryRevisionService;
use App\Services\WorkingHoursCalculationService;
use App\Support\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'department_id', 'designation_id', 'status', 'employment_type']);

        $employees = Employee::query()
            ->current()
            ->search($filters['search'] ?? null)
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['designation_id'] ?? null, fn ($query, $id) => $query->where('designation_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['employment_type'] ?? null, fn ($query, $type) => $query->where('employment_type', $type))
            ->with(['department:id,name', 'designation:id,name'])
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                ...$employee->toBrief(),
                'status_label' => $employee->status->label(),
                'employment_type' => $employee->employment_type->label(),
                'joining_date' => $employee->joining_date->toDateString(),
                'phone' => $employee->phone,
                'email' => $employee->email,
            ]);

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => EmployeeStatus::currentOptions(),
            'employmentTypes' => EmploymentType::options(),
        ]);
    }

    public function create(EmployeeService $employees): Response
    {
        return Inertia::render('employees/Create', [
            ...$this->formOptions(),
            'nextCode' => $employees->nextCode(),
            'today' => $this->tenant()->today()->toDateString(),
        ]);
    }

    public function store(EmployeeRequest $request, EmployeeService $employees): RedirectResponse
    {
        $employee = $employees->create($request->validated(), $request->file('photo'), $request->user());

        $this->toast("{$employee->full_name} added.");

        return to_route('employees.show', $employee);
    }

    public function show(
        Employee $employee,
        WorkingHoursCalculationService $hours,
        SalaryRevisionService $salaries,
        AttendanceCalculationService $attendance,
        LeaveService $leaves,
    ): Response {
        $employee->load(['department', 'designation', 'reportingManager', 'finalSettlement']);
        $today = $this->tenant()->today();
        $referenceDate = $employee->last_working_date !== null && $employee->last_working_date->lt($today)
            ? $employee->last_working_date
            : $today;

        $shift = $hours->shiftFor($employee, $referenceDate);
        $revision = $salaries->revisionOn($employee, $referenceDate);
        $period = PayrollPeriod::containing($referenceDate, $this->tenant()->settings()->payroll_period_start_day);

        $borrows = EmployeeBorrow::query()->where('employee_id', $employee->id)->latest('borrow_date')->latest('id')->get();
        $issued = $borrows->where('disbursed_at', '!=', null);

        return Inertia::render('employees/Show', [
            'employee' => [
                ...$employee->toBrief(),
                ...$employee->only([
                    'first_name', 'last_name', 'phone', 'email', 'address', 'city', 'state', 'country',
                    'postal_code', 'notes', 'exit_reason', 'exit_notes',
                ]),
                'status_label' => $employee->status->label(),
                'is_past' => $employee->isPast(),
                'gender' => $employee->gender?->label(),
                'employment_type' => $employee->employment_type->label(),
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
                'joining_date' => $employee->joining_date->toDateString(),
                'probation_end_date' => $employee->probation_end_date?->toDateString(),
                'reporting_manager' => $employee->reportingManager?->full_name,
                'exit_date' => $employee->exit_date?->toDateString(),
                'last_working_date' => $employee->last_working_date?->toDateString(),
                'exit_type' => $employee->exit_type?->label(),
                'settlement_status' => $employee->finalSettlement?->status,
            ],
            'shift' => $shift === null ? null : [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
                'required_minutes' => $shift->required_minutes,
                'source' => $hours->shiftSourceFor($employee, $referenceDate),
            ],
            'shifts' => WorkShift::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'start_time', 'end_time', 'required_minutes']),
            'salary' => $revision === null ? null : [
                'gross' => $revision->new_gross,
                'effective_date' => $revision->effective_date->toDateString(),
                'components' => $revision->components->map->only(['name', 'type', 'amount']),
                'revisions_count' => $salaries->revisions($employee)->count(),
            ],
            'attendance' => [
                'period' => $period->toArray(),
                'summary' => $attendance->summarize($attendance->resolveDays($employee, $period->start, $period->end)),
            ],
            'borrows' => [
                'total_borrowed' => round((float) $issued->sum('opening_balance'), 2),
                'total_recovered' => round((float) $issued->sum('recovered_amount'), 2),
                'total_outstanding' => round((float) $borrows
                    ->filter(fn (EmployeeBorrow $borrow): bool => $borrow->status === BorrowStatus::Active)
                    ->sum('outstanding_amount'), 2),
                'items' => $borrows->map(fn (EmployeeBorrow $borrow): array => [
                    'id' => $borrow->id,
                    'reference_no' => $borrow->reference_no,
                    'kind' => $borrow->kind,
                    'amount' => $borrow->opening_balance,
                    'outstanding' => $borrow->outstanding_amount,
                    'monthly_deduction' => $borrow->monthly_deduction,
                    'borrow_date' => $borrow->borrow_date->toDateString(),
                    'status' => $borrow->status->value,
                    'status_label' => $borrow->status->label(),
                ])->values(),
            ],
            'leaveBalances' => $leaves->balances($employee, $today->year),
            'activity' => AuditLog::query()
                ->where('employee_id', $employee->id)
                ->with('user:id,name')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'exitTypes' => ExitType::options(),
            'today' => $today->toDateString(),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('employees/Edit', [
            ...$this->formOptions($employee),
            'employee' => [
                ...$employee->only([
                    'id', 'employee_code', 'first_name', 'last_name', 'phone', 'email', 'address', 'city', 'state',
                    'country', 'postal_code', 'department_id', 'designation_id', 'reporting_manager_id', 'notes',
                ]),
                'photo_url' => $employee->photoUrl(),
                'gender' => $employee->gender?->value,
                'employment_type' => $employee->employment_type->value,
                'status' => $employee->status->value,
                'is_past' => $employee->isPast(),
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
                'joining_date' => $employee->joining_date->toDateString(),
                'probation_end_date' => $employee->probation_end_date?->toDateString(),
            ],
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeService $employees): RedirectResponse
    {
        // A past employee keeps that status; it only changes through the exit screen.
        $data = $employee->isPast()
            ? [...$request->validated(), 'status' => EmployeeStatus::Past->value]
            : $request->validated();

        $employees->update($employee, $data, $request->file('photo'));

        $this->toast('Employee updated.');

        return to_route('employees.show', $employee);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Employee $employee = null): array
    {
        return [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'shifts' => WorkShift::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'start_time', 'end_time', 'required_minutes']),
            'managers' => Employee::query()
                ->current()
                ->when($employee, fn ($query) => $query->whereKeyNot($employee->id))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code'])
                ->map(fn (Employee $manager): array => ['id' => $manager->id, 'name' => "{$manager->full_name} ({$manager->employee_code})"]),
            'genders' => Gender::options(),
            'statuses' => EmployeeStatus::currentOptions(),
            'employmentTypes' => EmploymentType::options(),
        ];
    }
}
