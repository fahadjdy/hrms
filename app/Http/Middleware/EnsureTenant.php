<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for company routes from the authenticated user.
 *
 * The company never comes from the URL, query string or request body, so a
 * Company Admin has no way to point a request at another company.
 */
class EnsureTenant
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if ($user->is_super_admin || $user->company_id === null) {
            return redirect()->route('admin.companies.index');
        }

        $company = $user->company;

        if (! $user->is_active || $company === null || ! $company->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account or its company has been deactivated. Contact your administrator.',
            ]);
        }

        $this->tenant->set($company);

        return $next($request);
    }
}
