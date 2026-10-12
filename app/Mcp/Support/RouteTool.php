<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * One MCP tool per named web route, described by an entry in
 * app/Mcp/Definitions/*.php. The tool forwards its arguments to the route
 * through RouteBridge, so behaviour, permissions and validation always match
 * the web app.
 */
class RouteTool extends Tool
{
    /**
     * @param  array<string, mixed>  $definition  One entry of app/Mcp/Definitions/*.php.
     */
    public function __construct(protected array $definition)
    {
        $this->name = (string) $definition['name'];
        $this->title = (string) $definition['title'];
        $this->description = (string) $definition['description'];
    }

    public function route(): string
    {
        return (string) $this->definition['route'];
    }

    /**
     * Names of the route URI parameters (the definition's `params` keys).
     *
     * @return list<string>
     */
    protected function paramNames(): array
    {
        return array_map(strval(...), array_keys((array) ($this->definition['params'] ?? [])));
    }

    /**
     * Only list the tool to users whose role can reach the route.
     */
    public function shouldRegister(): bool
    {
        $user = Auth::user();

        return ! $user instanceof User || RouteAccess::allows($user, $this->route());
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated: connect with an HRMS MCP token (Settings → AI access).');
        }

        $route = app(Router::class)->getRoutes()->getByName($this->route());

        if (! $route instanceof Route || ! CallableRoutes::isCallable($route)) {
            return Response::error("The route behind [{$this->name()}] is not available over MCP.");
        }

        $args = $request->all();
        $paramNames = $this->paramNames();

        try {
            $result = RouteBridge::call($user, $this->route(), Arr::only($args, $paramNames), Arr::except($args, $paramNames));
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return Response::error('Server error: '.$e->getMessage());
        }

        return self::respond($result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function respond(array $result): Response
    {
        $json = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return ($result['ok'] ?? false) ? Response::text((string) $json) : Response::error((string) $json);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $array['inputSchema'] = self::schemaFor(array_merge($this->definition['params'] ?? [], $this->definition['fields'] ?? []), $this->paramNames());

        $readOnly = (bool) ($this->definition['read_only'] ?? false);
        $array['annotations'] = [
            'title' => $this->title,
            'readOnlyHint' => $readOnly,
            'destructiveHint' => ! $readOnly && (bool) ($this->definition['destructive'] ?? false),
            'idempotentHint' => $readOnly,
            'openWorldHint' => false,
        ];

        return $array;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  list<string>  $alwaysRequired  Route parameters are always required.
     * @return array<string, mixed>
     */
    public static function schemaFor(array $fields, array $alwaysRequired = []): array
    {
        $properties = [];
        $required = [];

        foreach ($fields as $name => $spec) {
            $properties[$name] = self::property($spec);

            if (($spec['required'] ?? false) || in_array($name, $alwaysRequired, true)) {
                $required[] = $name;
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties === [] ? (object) [] : $properties,
            'required' => $required,
        ], fn ($v) => $v !== []);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    protected static function property(array $spec): array
    {
        $type = $spec['type'] ?? 'string';

        if ($type === 'file') {
            return [
                'type' => 'object',
                'description' => trim(($spec['description'] ?? '').' Send the file as {"name": "file.pdf", "content_base64": "..."}.'),
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'File name with extension.'],
                    'content_base64' => ['type' => 'string', 'description' => 'File content, base64 encoded.'],
                    'mime_type' => ['type' => 'string', 'description' => 'Optional MIME type.'],
                ],
                'required' => ['name', 'content_base64'],
            ];
        }

        $property = ['type' => $type];

        if (isset($spec['description'])) {
            $property['description'] = $spec['description'];
        }

        if (isset($spec['enum'])) {
            $property['enum'] = array_values((array) $spec['enum']);
        }

        if ($type === 'array') {
            $property['items'] = isset($spec['items']) ? self::property($spec['items']) : (object) [];
        }

        if ($type === 'object' && isset($spec['properties'])) {
            $nested = self::schemaFor($spec['properties']);
            $property['properties'] = $nested['properties'];

            if (isset($nested['required'])) {
                $property['required'] = $nested['required'];
            }
        }

        return $property;
    }
}
