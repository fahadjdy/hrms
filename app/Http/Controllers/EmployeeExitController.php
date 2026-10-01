<?php

namespace App\Http\Controllers;

use App\Enums\ExitType;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeExitController extends Controller
{
    /**
     * "Leave Company": the employee becomes a past employee and keeps all history.
     */
    public function store(Request $request, Employee $employee, EmployeeService $employees): RedirectResponse
    {
        if ($employee->isPast()) {
            throw ValidationException::withMessages(['exit_date' => 'This employee has already left the company.']);
        }

        $validated = $request->validate([
            'exit_date' => ['required', 'date', 'after_or_equal:'.$employee->joining_date->toDateString()],
            'last_working_date' => ['required', 'date', 'after_or_equal:'.$employee->joining_date->toDateString()],
            'exit_type' => ['required', Rule::enum(ExitType::class)],
            'exit_reason' => ['nullable', 'string', 'max:255'],
            'exit_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $employees->exit($employee, $validated);

        $this->toast("{$employee->full_name} is now a past employee.");

        return to_route('employees.show', $employee);
    }

    /**
     * Reinstate a past employee whose exit was recorded by mistake.
     */
    public function destroy(Employee $employee, EmployeeService $employees): RedirectResponse
    {
        if (! $employee->isPast()) {
            throw ValidationException::withMessages(['employee' => 'This employee is not a past employee.']);
        }

        if ($employee->finalSettlement()->where('status', '!=', 'draft')->exists()) {
            throw ValidationException::withMessages([
                'employee' => 'The final settlement is already finalized, so this employee cannot be reinstated.',
            ]);
        }

        $employees->reinstate($employee);
        $employee->finalSettlement()->delete();

        $this->toast("{$employee->full_name} reinstated.");

        return to_route('employees.show', $employee);
    }
}
