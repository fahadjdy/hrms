<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Support\CallableRoutes;
use App\Mcp\Support\ToolDefinitions;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Tests\TestCase;

/**
 * Every action an MCP client can reach has a documented, dedicated tool, and
 * every tool definition matches the route it drives. Fails when a route is
 * added without a definition or a route changes under an existing one.
 */
class McpCoverageTest extends TestCase
{
    /**
     * Callable routes deliberately served only through call_route, each with
     * the reason. Keep this empty unless a route truly should have no tool.
     *
     * @var list<string>
     */
    private const CALL_ROUTE_ONLY = [];

    public function test_every_callable_route_has_a_tool_definition(): void
    {
        $covered = ToolDefinitions::toolNamesByRoute();

        $missing = collect(CallableRoutes::all())
            ->map(fn (Route $route) => $route->getName())
            ->reject(fn (string $name) => isset($covered[$name]) || in_array($name, self::CALL_ROUTE_ONLY, true))
            ->values()
            ->all();

        $this->assertSame([], $missing, 'Routes without an MCP tool definition in app/Mcp/Definitions.');
    }

    public function test_every_definition_points_at_a_callable_route_with_matching_parameters(): void
    {
        $routes = app(Router::class)->getRoutes();

        foreach (ToolDefinitions::all() as $definition) {
            $route = $routes->getByName($definition['route']);

            $this->assertInstanceOf(Route::class, $route, "Tool [{$definition['name']}] uses unknown route [{$definition['route']}].");
            $this->assertTrue(CallableRoutes::isCallable($route), "Tool [{$definition['name']}] uses route [{$definition['route']}], which MCP does not expose.");
            $this->assertEqualsCanonicalizing(
                $route->parameterNames(),
                array_keys($definition['params'] ?? []),
                "Tool [{$definition['name']}] params do not match the parameters of route [{$definition['route']}].",
            );
        }
    }

    public function test_tool_names_are_unique_snake_case_and_cover_each_route_once(): void
    {
        $definitions = ToolDefinitions::all();
        $names = array_column($definitions, 'name');

        $this->assertSame([], array_values(array_diff_assoc($names, array_unique($names))), 'Duplicate tool names.');
        $this->assertSame([], array_values(array_intersect($names, ['get_me', 'list_routes', 'call_route'])), 'A definition reuses a built-in tool name.');

        foreach ($names as $name) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name, "Tool name [{$name}] is not snake_case.");
            $this->assertLessThanOrEqual(64, strlen($name), "Tool name [{$name}] is too long for MCP clients.");
        }

        $routes = array_column($definitions, 'route');
        $this->assertSame([], array_values(array_diff_assoc($routes, array_unique($routes))), 'Several tools drive the same route.');
    }

    public function test_read_only_tools_are_exactly_the_get_routes(): void
    {
        $routes = app(Router::class)->getRoutes();

        foreach (ToolDefinitions::all() as $definition) {
            $route = $routes->getByName($definition['route']);
            $this->assertInstanceOf(Route::class, $route);

            $this->assertSame(
                in_array('GET', $route->methods(), true),
                (bool) ($definition['read_only'] ?? false),
                "Tool [{$definition['name']}]: read_only must be true exactly when route [{$definition['route']}] is GET.",
            );
        }
    }

    public function test_every_definition_is_documented(): void
    {
        foreach (ToolDefinitions::all() as $definition) {
            $this->assertNotSame('', trim((string) ($definition['title'] ?? '')), "Tool [{$definition['name']}] has no title.");
            $this->assertNotSame('', trim((string) ($definition['description'] ?? '')), "Tool [{$definition['name']}] has no description.");
        }
    }
}
