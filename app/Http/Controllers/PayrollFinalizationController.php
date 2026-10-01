<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayrollFinalizationController extends Controller
{
    /**
     * Finalize: lock the payroll, post borrows and create salary slips.
     */
    public function store(Request $request, Payroll $payroll, PayrollService $payrolls): RedirectResponse
    {
        $payrolls->finalize($payroll, $request->user());

        $this->toast("Payroll for {$payroll->label()} finalized. Salary slips are available.");

        return back();
    }

    /**
     * Reopen a finalized payroll. A reason is required and the action is audited.
     */
    public function destroy(Request $request, Payroll $payroll, PayrollService $payrolls): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $payrolls->reopen($payroll, $validated['reason'], $request->user());

        $this->toast("Payroll for {$payroll->label()} reopened.", 'warning');

        return back();
    }
}
