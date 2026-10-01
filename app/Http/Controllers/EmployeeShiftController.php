<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeService;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeShiftController extends Controller
{
    /**
     * Set or remove the employee-specific work shift. Without one, the
     * gender-based or company default shift applies.
     */
    public function update(Request $request, Employee $employee, EmployeeService $employees): RedirectResponse
    {
        $validated = $request->validate([
            'work_shift_id' => ['nullable', 'integer', TenantRule::exists('work_shifts')],
            'effective_from' => ['required', 'date'],
        ]);

        $employees->assignShift(
            $employee,
            $validated['work_shift_id'] !== null ? (int) $validated['work_shift_id'] : null,
            CarbonImmutable::parse($validated['effective_from']),
            $request->user(),
        );

        $this->toast('Work timing updated.');

        return back();
    }
}
