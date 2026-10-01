<?php

namespace App\Http\Controllers;

use App\Jobs\CalculatePayroll;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PayrollCalculationController extends Controller
{
    /**
     * Calculate or recalculate a payroll. Large companies are processed on the queue.
     */
    public function store(Payroll $payroll, PayrollService $payrolls): RedirectResponse
    {
        if ($payroll->isLocked()) {
            throw ValidationException::withMessages([
                'payroll' => "The payroll for {$payroll->label()} is finalized and locked. Reopen it to make changes.",
            ]);
        }

        if (Employee::query()->current()->count() > config('hrms.sync_payroll_employee_limit')) {
            CalculatePayroll::dispatch($payroll->company_id, $payroll->id);

            $this->toast('Payroll calculation has been queued. Refresh this page in a moment to see the results.', 'info');

            return back();
        }

        $payrolls->calculate($payroll);

        $this->toast("Payroll calculated for {$payroll->employee_count} employee(s).");

        return back();
    }
}
