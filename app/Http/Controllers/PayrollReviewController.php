<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;

class PayrollReviewController extends Controller
{
    /**
     * Move a calculated payroll into admin review.
     */
    public function store(Payroll $payroll, PayrollService $payrolls): RedirectResponse
    {
        $payrolls->markUnderReview($payroll);

        $this->toast('Payroll is now in admin review.');

        return back();
    }
}
