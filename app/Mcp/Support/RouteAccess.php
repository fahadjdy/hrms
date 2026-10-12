<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Predicts from a route's middleware whether a user may call it, so the MCP
 * server only lists the tools that user can actually use. This is a filter
 * for the tool list only: the real check is the route's own middleware, which
 * RouteBridge always runs.
 */
final class RouteAccess
{
    public static function allows(User $user, string $routeName): bool
    {
        $route = app(Router::class)->getRoutes()->getByName($routeName);

        return $route instanceof Route && self::allowsRoute($user, $route);
    }

    public static function allowsRoute(User $user, Route $route): bool
    {
        if (! $user->is_active) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $allowed = match (true) {
                $middleware === 'tenant' => self::isCompanyUser($user),
                $middleware === 'super-admin' => $user->is_super_admin,
                str_starts_with($middleware, 'can:') => $user->can(substr($middleware, 4)),
                default => true,
            };

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }

    public static function isCompanyUser(User $user): bool
    {
        if ($user->is_super_admin || $user->company_id === null) {
            return false;
        }

        $company = $user->company;

        return $company !== null && $company->is_active;
    }

    /**
     * The permission named by the route's `can:` middleware, if any.
     */
    public static function permission(Route $route): ?string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                return substr($middleware, 4);
            }
        }

        return null;
    }
}
