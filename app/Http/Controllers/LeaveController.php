<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'leave_type_id', 'from', 'to']);

        $leaves = EmployeeLeave::query()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['leave_type_id'] ?? null, fn ($query, $id) => $query->where('leave_type_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('end_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('start_date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('employee', fn ($employee) => $employee->search($search)))
            ->with(['employee:id,first_name,last_name,employee_code', 'leaveType:id,name,code,is_paid'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EmployeeLeave $leave): array => [
                'id' => $leave->id,
                'employee' => ['id' => $leave->employee->id, 'name' => $leave->employee->full_name, 'code' => $leave->employee->employee_code],
                'leave_type_id' => $leave->leave_type_id,
                'leave_type' => $leave->leaveType->name,
                'is_paid' => $leave->leaveType->is_paid,
                'start_date' => $leave->start_date->toDateString(),
                'end_date' => $leave->end_date->toDateString(),
                'is_half_day' => $leave->is_half_day,
                'days' => $leave->days,
                'status' => $leave->status->value,
                'status_label' => $leave->status->label(),
                'reason' => $leave->reason,
                'notes' => $leave->notes,
            ]);

        return Inertia::render('leave/Index', [
            'leaves' => $leaves,
            'filters' => $filters,
            'leaveTypes' => LeaveType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'is_paid']),
            'statuses' => LeaveStatus::options(),
            'employees' => Employee::query()
                ->current()
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => "{$employee->full_name} ({$employee->employee_code})"]),
            'counts' => [
                'pending' => EmployeeLeave::query()->where('status', LeaveStatus::Pending->value)->count(),
            ],
        ]);
    }

    public function store(Request $request, LeaveService $leaves): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            ...$this->rules(),
            'status' => ['nullable', Rule::in([LeaveStatus::Pending->value, LeaveStatus::Approved->value])],
        ]);

        $leaves->create(Employee::query()->findOrFail((int) $validated['employee_id']), $validated, $request->user());

        $this->toast('Leave added.');

        return back();
    }

    public function update(Request $request, EmployeeLeave $leave, LeaveService $leaves): RedirectResponse
    {
        $leave->load(['employee', 'leaveType']);
        $leaves->update($leave, $request->validate($this->rules()), $request->user());

        $this->toast('Leave updated.');

        return back();
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', TenantRule::exists('leave_types')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_half_day' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
