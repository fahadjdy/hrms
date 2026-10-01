<?php

namespace App\Http\Controllers;

use App\Models\EmployeeLeave;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveDecisionController extends Controller
{
    /**
     * Approve, reject or cancel a leave.
     */
    public function update(Request $request, EmployeeLeave $leave, LeaveService $leaves): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'cancel'])],
        ]);

        $leave->load(['employee', 'leaveType']);

        $user = $request->user();

        // The decision is validated above, so anything other than approve or reject is a cancel.
        [$message] = match ($validated['decision']) {
            'approve' => ['Leave approved.', $leaves->approve($leave, $user)],
            'reject' => ['Leave rejected.', $leaves->reject($leave, $user)],
            default => ['Leave cancelled.', $leaves->cancel($leave, $user)],
        };

        $this->toast($message);

        return back();
    }
}
