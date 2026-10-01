<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSalaryRevision;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryRevisionController extends Controller
{
    /**
     * Salary history across the company: every revision, newest first.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'from', 'to']);

        $revisions = EmployeeSalaryRevision::query()
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('effective_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('effective_date', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('employee', fn ($employee) => $employee->search($search)))
            ->with(['employee:id,first_name,last_name,employee_code', 'creator:id,name'])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EmployeeSalaryRevision $revision): array => [
                'id' => $revision->id,
                'employee' => [
                    'id' => $revision->employee->id,
                    'name' => $revision->employee->full_name,
                    'code' => $revision->employee->employee_code,
                ],
                'effective_date' => $revision->effective_date->toDateString(),
                'previous_gross' => $revision->previous_gross,
                'new_gross' => $revision->new_gross,
                'change' => round($revision->new_gross - $revision->previous_gross, 2),
                'reason' => $revision->reason,
                'changed_by' => $revision->creator?->name,
                'created_at' => $revision->created_at?->toIso8601String(),
            ]);

        return Inertia::render('payroll/SalaryRevisions', [
            'revisions' => $revisions,
            'filters' => $filters,
        ]);
    }
}
