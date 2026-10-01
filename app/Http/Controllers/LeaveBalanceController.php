<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    public function index(Request $request, LeaveService $leaves): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'search' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer'],
        ]);

        $year = (int) ($validated['year'] ?? $this->tenant()->today()->year);

        $employees = Employee::query()
            ->current()
            ->search($validated['search'] ?? null)
            ->when($validated['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->with(['department:id,name', 'designation:id,name'])
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                ...$employee->toBrief(),
                'balances' => $leaves->balances($employee, $year),
            ]);

        return Inertia::render('leave/Balances', [
            'employees' => $employees,
            'year' => $year,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'department_id' => $validated['department_id'] ?? null,
            ],
            'leaveTypes' => LeaveType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Set the allocation and manual adjustment of one leave type for an employee and year.
     */
    public function update(Request $request, LeaveService $leaves): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'leave_type_id' => ['required', 'integer', TenantRule::exists('leave_types')],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'allocated' => ['required', 'numeric', 'min:0', 'max:366'],
            'adjustment' => ['required', 'numeric', 'between:-366,366'],
        ]);

        $leaves->setBalance(
            Employee::query()->findOrFail((int) $validated['employee_id']),
            LeaveType::query()->findOrFail((int) $validated['leave_type_id']),
            (int) $validated['year'],
            (float) $validated['allocated'],
            (float) $validated['adjustment'],
        );

        $this->toast('Leave balance updated.');

        return back();
    }
}
