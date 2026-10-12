<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Appends one JSON line per request that touches the MCP / OAuth surface to
 * storage/logs/mcp-access.log, so connector problems can be diagnosed on
 * shared hosting without access to the web-server logs. Read the tail with
 * GET /deploy/mcp-log (when deploy URLs are enabled). Requests dropped by a host firewall never reach PHP,
 * so their absence here is itself a diagnosis.
 */
class LogMcpTraffic
{
    public const LOG_FILE = 'logs/mcp-access.log';

    /** Size at which the log is rotated to mcp-access.log.1. */
    public const MAX_BYTES = 5_000_000;

    protected ?string $location = null;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->watched($request)) {
            return $next($request);
        }

        $started = microtime(true);
        $status = null;

        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $this->location = $response->headers->get('Location');

            return $response;
        } catch (Throwable $e) {
            $status = 'EXC '.class_basename($e);
            throw $e;
        } finally {
            $this->write($request, $status, $started);
        }
    }

    protected function watched(Request $request): bool
    {
        return $request->is('mcp', 'mcp/*', 'oauth/*', '.well-known/*', 'authorize', 'token', 'register');
    }

    /** Redirect target without secrets: host + path + the names of the query parameters (never the code). */
    protected function describeRedirect(): ?string
    {
        if (! $this->location) {
            return null;
        }

        $parts = parse_url($this->location);
        parse_str((string) ($parts['query'] ?? ''), $query);

        return ($parts['host'] ?? '').($parts['path'] ?? '').' ['.implode(',', array_keys($query)).']';
    }

    protected function write(Request $request, mixed $status, float $started): void
    {
        $body = (string) $request->getContent();
        $method = null;
        if ($body !== '' && ($decoded = json_decode($body, true)) && is_array($decoded)) {
            $method = $decoded['method'] ?? null;
        }

        $line = json_encode([
            't' => now()->toDateTimeString(),
            'ip' => $request->ip(),
            'm' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'rpc' => $method,
            'status' => $status,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'auth' => $request->headers->has('Authorization') ? substr((string) $request->headers->get('Authorization'), 0, 12).'…' : null,
            'ua' => mb_substr((string) $request->userAgent(), 0, 90),
            'accept' => mb_substr((string) $request->header('Accept'), 0, 60),
            'proto' => $request->header('MCP-Protocol-Version'),
            'redirect' => $this->describeRedirect(),
        ], JSON_UNESCAPED_SLASHES);

        $file = storage_path(self::LOG_FILE);

        if (@filesize($file) > self::MAX_BYTES) {
            @rename($file, $file.'.1');
        }

        @file_put_contents($file, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
