<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * The OAuth consent screen of the MCP server (/oauth/authorize) is a plain
     * Blade page. When signing in returns there, an Inertia visit would load it
     * inside a modal, so redirects to it become a full page visit.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($request->header('X-Inertia') && $response->isRedirect()) {
            $target = (string) $response->headers->get('Location');
            $host = parse_url($target, PHP_URL_HOST);

            if (str_starts_with((string) parse_url($target, PHP_URL_PATH), '/oauth/') && ($host === null || $host === $request->getHost())) {
                return Inertia::location($target);
            }
        }

        return $response;
    }

    /**
     * A file download (report export, employee document) has no buffered content,
     * which Inertia would otherwise treat as an empty page and redirect back.
     * This matters for Inertia-style requests made by the MCP bridge.
     */
    public function onEmptyResponse(Request $request, Response $response): Response
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        return parent::onEmptyResponse($request, $response);
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        // Personal pages such as the profile sit outside the company routes, so
        // the tenant is not set there; the user's own company still applies.
        $company = app(TenantContext::class)->company() ?? $user?->company;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
                    'avatar' => $user->avatarUrl(),
                    'is_super_admin' => $user->is_super_admin,
                    'role' => $user->company_id !== null ? $user->role?->name : null,
                    'permissions' => $user->permissionList(),
                ],
                'impersonating' => $request->hasSession() && $request->session()->has('impersonator_id'),
            ],
            'company' => $company === null ? null : [
                'id' => $company->id,
                'name' => $company->name,
                'logo_url' => $company->logoUrl(),
                'currency' => $company->currency,
                'timezone' => $company->timezone,
                'date_format' => $company->date_format,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
