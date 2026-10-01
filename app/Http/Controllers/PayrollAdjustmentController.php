<?php

namespace App\Http\Controllers;

use App\Enums\PayrollBucket;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollAdjustmentController extends Controller
{
    /**
     * Add a manual adjustment. An amount and a reason are always required.
     */
    public function store(Request $request, Payroll $payroll, PayrollItem $item, PayrollService $payrolls): RedirectResponse
    {
        $validated = $request->validate([
            'bucket' => ['required', Rule::in(array_map(fn (PayrollBucket $bucket): string => $bucket->value, PayrollBucket::adjustable()))],
            'amount' => ['required', 'numeric', 'not_in:0', 'between:-999999999,999999999'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $item->setRelation('payroll', $payroll);
        $item->load('employee');

        $payrolls->addAdjustment(
            $item,
            PayrollBucket::from($validated['bucket']),
            (float) $validated['amount'],
            $validated['reason'],
            $request->user(),
        );

        $this->toast('Adjustment added.');

        return back();
    }

    public function destroy(Payroll $payroll, PayrollItem $item, PayrollAdjustment $adjustment, PayrollService $payrolls): RedirectResponse
    {
        $item->setRelation('payroll', $payroll);
        $adjustment->setRelation('payrollItem', $item);

        $payrolls->removeAdjustment($adjustment);

        $this->toast('Adjustment removed.');

        return back();
    }
}
