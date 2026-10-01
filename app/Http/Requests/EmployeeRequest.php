<?php

namespace App\Http\Requests;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Models\Employee;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->route('employee');
        $employeeId = $employee instanceof Employee ? $employee->id : null;

        $rules = [
            'employee_code' => ['required', 'string', 'max:50', TenantRule::unique('employees', 'employee_code')->ignore($employeeId)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('hrms.photo_max_kilobytes')],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'joining_date' => ['required', 'date'],
            'department_id' => ['nullable', 'integer', TenantRule::exists('departments')],
            'designation_id' => ['nullable', 'integer', TenantRule::exists('designations')],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'reporting_manager_id' => [
                'nullable', 'integer', TenantRule::exists('employees'),
                Rule::notIn(array_filter([$employeeId])),
            ],
            // A past employee's status only changes through the exit screen.
            'status' => $employee instanceof Employee && $employee->isPast()
                ? ['nullable']
                : ['required', Rule::in(EmployeeStatus::currentValues())],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        // Shift, salary and an existing borrow can be set up while adding the employee.
        if ($employeeId === null) {
            $rules += [
                'work_shift_id' => ['nullable', 'integer', TenantRule::exists('work_shifts')],
                'salary_components' => ['nullable', 'array', 'max:30'],
                'salary_components.*.name' => ['required', 'string', 'max:100'],
                'salary_components.*.type' => ['required', Rule::in(['earning', 'deduction'])],
                'salary_components.*.amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
                'existing_borrow' => ['nullable', 'array'],
                'existing_borrow.amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999'],
                'existing_borrow.opening_balance' => ['nullable', 'numeric', 'min:0.01', 'lte:existing_borrow.amount'],
                'existing_borrow.borrow_date' => ['nullable', 'date'],
                'existing_borrow.reason' => ['nullable', 'string', 'max:255'],
                'existing_borrow.monthly_deduction' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
                'existing_borrow.installments_count' => ['nullable', 'integer', 'min:1', 'max:600'],
                'existing_borrow.deduction_start_month' => ['nullable', 'date'],
                'existing_borrow.source_reference' => ['nullable', 'string', 'max:255'],
                'existing_borrow.notes' => ['nullable', 'string', 'max:2000'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_code' => 'employee ID',
            'department_id' => 'department',
            'designation_id' => 'designation',
            'reporting_manager_id' => 'reporting manager',
            'work_shift_id' => 'work shift',
            'salary_components.*.name' => 'component name',
            'salary_components.*.amount' => 'component amount',
            'existing_borrow.amount' => 'borrow amount',
            'existing_borrow.opening_balance' => 'outstanding balance',
            'existing_borrow.monthly_deduction' => 'monthly deduction',
            'existing_borrow.installments_count' => 'number of installments',
            'existing_borrow.deduction_start_month' => 'deduction start month',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reporting_manager_id.not_in' => 'An employee cannot report to themselves.',
        ];
    }
}
