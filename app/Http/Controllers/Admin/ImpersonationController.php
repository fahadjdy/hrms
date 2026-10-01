<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lets the super admin sign in as a user of a company to help with setup or
 * support, and return to their own account afterwards. Every use is written
 * to that company's audit log.
 */
class ImpersonationController extends Controller
{
    /**
     * Sign in as the given user of the company, or as its Company Admin when
     * no user is named.
     */
    public function store(Request $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['user_id' => ['nullable', 'integer']]);
        $superAdmin = $request->user();

        if (! $company->is_active) {
            $this->toast('Activate this company before signing in as one of its users.', 'error');

            return back();
        }

        // The user must belong to this company; another company's user id finds nothing.
        $users = User::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('is_super_admin', false);

        $user = isset($validated['user_id'])
            ? $users->whereKey($validated['user_id'])->first()
            : $users
                ->whereIn('role_id', Role::withoutTenancy()
                    ->where('company_id', $company->id)
                    ->where('slug', Role::ADMIN_SLUG)
                    ->select('id'))
                ->oldest('id')
                ->first();

        if ($user === null) {
            $this->toast(
                isset($validated['user_id'])
                    ? 'That user is not an active user of this company.'
                    : 'This company has no active Company Admin to sign in as.',
                'error',
            );

            return back();
        }

        $this->tenant()->run($company, fn () => $audit->log(
            'company.impersonated',
            $company,
            null,
            ['super_admin_id' => $superAdmin->id, 'as_user_id' => $user->id],
            "Super admin signed in as {$user->name}",
        ));

        Auth::guard('web')->login($user);
        $request->session()->put('impersonator_id', $superAdmin->id);

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');

        abort_if($impersonatorId === null, 403);

        $superAdmin = User::query()
            ->whereKey($impersonatorId)
            ->where('is_super_admin', true)
            ->firstOrFail();

        Auth::guard('web')->login($superAdmin);

        return to_route('admin.companies.index');
    }
}
