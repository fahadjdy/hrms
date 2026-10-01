<?php

namespace App\Http\Controllers;

use App\Enums\DesignationChangeType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDesignationChange;
use App\Services\DesignationChangeService;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DesignationChangeController extends Controller
{
    /**
     * Designation history across the company: every change, newest first.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'type', 'from', 'to']);

        $changes = EmployeeDesignationChange::query()
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('effective_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('effective_date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('employee', fn ($employee) => $employee->search($search)))
            ->with([
                'employee:id,first_name,last_name,employee_code,photo_path,status',
                'fromDesignation:id,name',
                'toDesignation:id,name',
                'changedBy:id,name',
            ])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EmployeeDesignationChange $change): array => [
                ...$change->toRow(),
                'employee' => [
                    'id' => $change->employee->id,
                    'name' => $change->employee->full_name,
                    'code' => $change->employee->employee_code,
                    'photo_url' => $change->employee->photoUrl(),
                    'is_past' => $change->employee->isPast(),
                ],
            ]);

        return Inertia::render('employees/DesignationHistory', [
            'changes' => $changes,
            'filters' => $filters,
            'types' => DesignationChangeType::options(),
        ]);
    }

    /**
     * Record a promotion, demotion or role change for an employee.
     */
    public function store(Request $request, Employee $employee, DesignationChangeService $changes): RedirectResponse
    {
        $validated = $request->validate([
            'designation_id' => ['required', 'integer', TenantRule::exists('designations')],
            'type' => ['required', Rule::in(DesignationChangeType::recordableValues())],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'designation_id' => 'designation',
        ]);

        $change = $changes->change(
            $employee,
            Designation::query()->findOrFail((int) $validated['designation_id']),
            CarbonImmutable::parse($validated['effective_date']),
            DesignationChangeType::from($validated['type']),
            $validated['reason'] ?? null,
            $request->user(),
        );

        $this->toast("{$employee->full_name} is now {$change->toDesignation->name}.");

        return back();
    }
}
