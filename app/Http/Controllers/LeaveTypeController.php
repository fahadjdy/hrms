<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('leave/Types', [
            'leaveTypes' => LeaveType::query()
                ->withCount('leaves')
                ->orderBy('name')
                ->get()
                ->map(fn (LeaveType $type): array => [
                    ...$type->only(['id', 'name', 'code', 'is_paid', 'annual_allowance', 'is_active']),
                    'leaves_count' => $type->leaves_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LeaveType::query()->create($this->validated($request));

        $this->toast('Leave type added.');

        return back();
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($this->validated($request, $leaveType));

        $this->toast('Leave type updated.');

        return back();
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        if ($leaveType->leaves()->exists()) {
            throw ValidationException::withMessages([
                'leave_type' => 'Leave has been recorded under this type. Mark it inactive instead of deleting it.',
            ]);
        }

        $leaveType->delete();

        $this->toast('Leave type deleted.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?LeaveType $type = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_num', TenantRule::unique('leave_types', 'code')->ignore($type?->id)],
            'is_paid' => ['required', 'boolean'],
            'annual_allowance' => ['required', 'numeric', 'min:0', 'max:366'],
            'is_active' => ['boolean'],
        ]);
    }
}
