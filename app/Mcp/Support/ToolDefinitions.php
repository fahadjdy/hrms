<?php

namespace App\Mcp\Support;

/**
 * Loads the route-backed tool definitions from app/Mcp/Definitions/*.php.
 */
final class ToolDefinitions
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $definitions = [];

        foreach (glob(app_path('Mcp/Definitions/*.php')) ?: [] as $file) {
            foreach (require $file as $definition) {
                $definitions[] = $definition;
            }
        }

        return self::$definitions = $definitions;
    }

    /**
     * @return list<RouteTool>
     */
    public static function tools(): array
    {
        return array_map(fn (array $definition) => new RouteTool($definition), self::all());
    }

    /**
     * Tool name for each route name.
     *
     * @return array<string, string>
     */
    public static function toolNamesByRoute(): array
    {
        return array_column(self::all(), 'name', 'route');
    }
}
