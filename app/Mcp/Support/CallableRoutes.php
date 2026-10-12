<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * The application routes an MCP client may reach: every named route served by
 * an App\Http\Controllers controller, minus the ones that only make sense in a
 * browser session or must never be driven by an agent.
 */
final class CallableRoutes
{
    /**
     * Route names (or patterns with "*") that MCP never exposes.
     *
     * @var list<string>
     */
    public const EXCLUDED = [
        'deploy.*',                    // server maintenance URLs
        'admin.companies.impersonate', // switches the browser session to another user
        'impersonation.leave',
        'security.edit',               // needs a fresh password confirmation in the browser
        'mcp-access.*',                // issuing MCP credentials stays in the web app
    ];

    /**
     * @return list<Route>
     */
    public static function all(): array
    {
        return array_values(array_filter(
            app(Router::class)->getRoutes()->getRoutes(),
            fn (Route $route) => self::isCallable($route),
        ));
    }

    /**
     * @return list<Route>
     */
    public static function forUser(User $user): array
    {
        return array_values(array_filter(self::all(), fn (Route $route) => RouteAccess::allowsRoute($user, $route)));
    }

    public static function isCallable(Route $route): bool
    {
        $name = $route->getName();

        if ($name === null || Str::is(self::EXCLUDED, $name)) {
            return false;
        }

        return str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\')
            && ! str_starts_with($route->getActionName(), 'App\\Http\\Controllers\\DeployController');
    }
}
