<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 404 rather than 403: company users should not learn the platform area exists.
        abort_unless($user !== null && $user->is_super_admin && $user->is_active, 404);

        return $next($request);
    }
}
