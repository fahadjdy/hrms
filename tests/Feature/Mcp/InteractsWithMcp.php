<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\HrmsServer;
use App\Mcp\Support\ToolDefinitions;
use App\Mcp\Tools\CallRouteTool;
use App\Mcp\Tools\GetMeTool;
use App\Mcp\Tools\ListRoutesTool;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Server\Testing\TestListResponse;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Passport\Passport;
use ReflectionProperty;

/**
 * Helpers for driving the HRMS MCP server in-process through the
 * laravel/mcp testing API.
 */
trait InteractsWithMcp
{
    /**
     * Passport signs tokens with storage/oauth-*.key; create them on a fresh
     * checkout (CI) where they do not exist yet.
     */
    protected function ensurePassportKeys(): void
    {
        if (! config('passport.private_key') && (! is_file(Passport::keyPath('oauth-private.key')) || ! is_file(Passport::keyPath('oauth-public.key')))) {
            Artisan::call('passport:keys', ['--force' => true]);
        }
    }

    /**
     * Headers an MCP client sends with every streamable-HTTP request.
     *
     * @return array<string, string>
     */
    protected function mcpHeaders(?string $token = null): array
    {
        return array_filter([
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
            'Authorization' => $token !== null ? 'Bearer '.$token : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function rpc(string $method, array $params = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params];
    }

    /**
     * Names of the tools `tools/list` returns for the user.
     *
     * @return list<string>
     */
    protected function toolNamesFor(User $user): array
    {
        $list = HrmsServer::actingAs($user)->tools();
        $items = (new ReflectionProperty(TestListResponse::class, 'items'))->getValue($list);

        return array_values(Arr::pluck($items, 'name'));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function callTool(User $user, string $name, array $arguments = []): TestResponse
    {
        return HrmsServer::actingAs($user)->tool($this->toolNamed($name), $arguments);
    }

    protected function toolNamed(string $name): Tool
    {
        $builtIn = [
            'get_me' => GetMeTool::class,
            'list_routes' => ListRoutesTool::class,
            'call_route' => CallRouteTool::class,
        ];

        if (isset($builtIn[$name])) {
            return app($builtIn[$name]);
        }

        foreach (ToolDefinitions::tools() as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        $this->fail("No MCP tool named [{$name}].");
    }

    /**
     * The JSON document in the tool result's text content (success or error).
     *
     * @return array<string, mixed>
     */
    protected function toolResult(TestResponse $response): array
    {
        $rpc = (new ReflectionProperty(TestResponse::class, 'response'))->getValue($response)->toArray();
        $text = $rpc['result']['content'][0]['text'] ?? null;

        $this->assertIsString($text, 'The tool returned no text content: '.json_encode($rpc));

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : ['text' => $text];
    }
}
