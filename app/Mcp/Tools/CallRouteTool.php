<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\CallableRoutes;
use App\Mcp\Support\RouteBridge;
use App\Mcp\Support\RouteTool;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Name('call_route')]
#[Title('Call any HRMS action')]
#[Description('Full-access fallback: runs any route from list_routes as you, through the same permission checks and validation as the web app. Prefer the dedicated tools (their inputs are documented); use this for anything else. GET returns the page data; other methods save and return the redirect and flash message. Files go in `data` as {"name": "...", "content_base64": "..."}. Write actions change real company data, so confirm with the user first.')]
class CallRouteTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'route' => $schema->string()->description('Route name from list_routes, e.g. "employees.index".')->required(),
            'params' => $schema->object()->description('URL parameters by name, e.g. {"employee": 12, "date": "2026-10-01"}.'),
            'data' => $schema->object()->description('Query filters (GET) or form fields (POST/PUT/PATCH/DELETE).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $name = (string) $request->get('route');
        $route = app(Router::class)->getRoutes()->getByName($name);

        if (! $route instanceof Route || ! CallableRoutes::isCallable($route)) {
            return Response::error("Unknown or unavailable route [{$name}]. Call list_routes to see what you can use.");
        }

        try {
            $result = RouteBridge::call($user, $name, (array) ($request->get('params') ?? []), (array) ($request->get('data') ?? []));
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return Response::error('Server error: '.$e->getMessage());
        }

        return RouteTool::respond($result);
    }
}
