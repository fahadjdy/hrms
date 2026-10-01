<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Services\AttendanceCalculationService;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceBulkController extends Controller
{
    /**
     * Mark several employees with the same status for one date.
     */
    public function store(Request $request, AttendanceCalculationService $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->tenant()->today()->toDateString()],
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct', TenantRule::exists('employees')],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in' => ['nullable', 'date_format:H:i', 'required_with:check_out'],
            'check_out' => ['nullable', 'date_format:H:i', 'required_with:check_in'],
        ], [
            'date.before_or_equal' => 'Attendance cannot be marked for a future date.',
        ]);

        $date = CarbonImmutable::parse($validated['date']);
        $marked = 0;

        DB::transaction(function () use ($validated, $date, $attendance, $request, &$marked): void {
            $employees = Employee::query()
                ->whereKey($validated['employee_ids'])
                ->with('shiftAssignments')
                ->get();

            foreach ($employees as $employee) {
                if (! $employee->isEmployedOn($date)) {
                    continue;
                }

                $attendance->record($employee, $date, [
                    'status' => $validated['status'],
                    'check_in' => $validated['check_in'] ?? null,
                    'check_out' => $validated['check_out'] ?? null,
                    'reason' => 'Bulk update',
                ], $request->user());

                $marked++;
            }
        });

        $this->toast("Attendance saved for {$marked} employee(s).");

        return back();
    }
}
