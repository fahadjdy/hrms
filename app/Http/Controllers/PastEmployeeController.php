<?php

namespace App\Http\Controllers;

use App\Enums\BorrowStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBorrow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PastEmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'department_id']);

        $employees = Employee::query()
            ->past()
            ->search($filters['search'] ?? null)
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->with(['department:id,name', 'designation:id,name', 'finalSettlement:id,employee_id,status,net_amount'])
            ->withSum(
                ['borrows as outstanding_borrow' => fn ($query) => $query->where('status', BorrowStatus::Active->value)],
                'outstanding_amount',
            )
            ->orderByDesc('last_working_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee): array => [
                ...$employee->toBrief(),
                'joining_date' => $employee->joining_date->toDateString(),
                'last_working_date' => $employee->last_working_date?->toDateString(),
                'exit_type' => $employee->exit_type?->label(),
                'exit_reason' => $employee->exit_reason,
                'outstanding_borrow' => round((float) $employee->getAttribute('outstanding_borrow'), 2),
                'settlement_status' => $employee->finalSettlement?->status,
            ]);

        return Inertia::render('employees/Past', [
            'employees' => $employees,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'totals' => [
                'past_employees' => Employee::query()->past()->count(),
                'outstanding_borrow' => round((float) EmployeeBorrow::query()
                    ->outstanding()
                    ->whereIn('employee_id', Employee::query()->past()->select('id'))
                    ->sum('outstanding_amount'), 2),
            ],
        ]);
    }
}
