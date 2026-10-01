<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'action', 'user_id', 'from', 'to']);

        $logs = AuditLog::query()
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', "{$action}%"))
            ->when($filters['user_id'] ?? null, fn ($query, $id) => $query->where('user_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('description', 'like', "%{$search}%"))
            ->with(['user:id,name', 'employee:id,first_name,last_name,employee_code'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'description' => $log->description,
                'user' => $log->user?->name,
                'employee' => $log->employee === null ? null : ['id' => $log->employee->id, 'name' => $log->employee->full_name],
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('audit/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::query()->where('company_id', $this->company()->id)->orderBy('name')->get(['id', 'name']),
            'areas' => [
                ['value' => 'employee', 'label' => 'Employees'],
                ['value' => 'attendance', 'label' => 'Attendance'],
                ['value' => 'salary', 'label' => 'Salary'],
                ['value' => 'payroll', 'label' => 'Payroll'],
                ['value' => 'borrow', 'label' => 'Borrow'],
                ['value' => 'overtime', 'label' => 'Overtime'],
                ['value' => 'short_hours', 'label' => 'Short hours'],
                ['value' => 'leave', 'label' => 'Leave'],
                ['value' => 'settlement', 'label' => 'Final settlement'],
                ['value' => 'work_shift', 'label' => 'Work shifts'],
                ['value' => 'settings', 'label' => 'Settings'],
            ],
        ]);
    }
}
