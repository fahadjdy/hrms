<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\CallableRoutes;
use App\Mcp\Support\RouteAccess;
use App\Mcp\Support\RouteTool;
use App\Mcp\Support\ToolDefinitions;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Routing\Route;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_routes')]
#[Title('List every action you can call')]
#[Description('Every HRMS route your account can call: HTTP method, URL pattern, parameters, required permission and the dedicated tool for it. Use with call_route for anything that has no dedicated tool. Optional `search` filters by route name or URL.')]
#[IsReadOnly]
class ListRoutesTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Part of a route name or URL, e.g. "payroll" or "leave".'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $search = mb_strtolower(trim((string) $request->get('search', '')));
        $tools = ToolDefinitions::toolNamesByRoute();

        $routes = collect(CallableRoutes::forUser($user))
            ->filter(fn (Route $route) => $search === '' || str_contains(mb_strtolower($route->getName().' '.$route->uri()), $search))
            ->map(fn (Route $route) => array_filter([
                'route' => $route->getName(),
                'method' => collect($route->methods())->first(fn (string $m) => $m !== 'HEAD'),
                'uri' => '/'.ltrim($route->uri(), '/'),
                'params' => $route->parameterNames() ?: null,
                'permission' => RouteAccess::permission($route),
                'tool' => $tools[$route->getName()] ?? null,
            ]))
            ->values()
            ->all();

        return RouteTool::respond(['ok' => true, 'count' => count($routes), 'routes' => $routes]);
    }
}
