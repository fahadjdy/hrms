<?php

namespace App\Http\Middleware;

use App\Mcp\Support\RouteAccess;
use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token authentication for the MCP endpoint (Passport "api" guard),
 * with one exception: the JSON-RPC `server/discover` probe of MCP 2026-07-28
 * is answered without a token. Dual-era clients (claude.ai, the Claude app)
 * send it before anything else to learn the supported protocol versions; it
 * exposes no application data. Answering it with 401 makes those clients
 * treat the server as legacy and abandon the OAuth flow. Every other method
 * still gets the 401 + WWW-Authenticate challenge that starts OAuth discovery.
 */
class AuthenticateMcp
{
    public const PUBLIC_METHODS = ['server/discover', 'ping'];

    public function __construct(protected Authenticate $authenticate) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($this->rpcMethod($request), self::PUBLIC_METHODS, true)) {
            return $next($request);
        }

        return $this->authenticate->handle($request, function (Request $request) use ($next) {
            // A token outlives the account: refuse it once the user or their
            // company has been deactivated, like the web login does.
            $user = $request->user();

            if (! $user instanceof User || ! $user->is_active || (! $user->is_super_admin && ! RouteAccess::isCompanyUser($user))) {
                return response()->json(['error' => 'account_deactivated', 'error_description' => 'This account or its company has been deactivated.'], 403);
            }

            return $next($request);
        }, 'api');
    }

    protected function rpcMethod(Request $request): ?string
    {
        $body = json_decode((string) $request->getContent(), true);

        return is_array($body) && is_string($body['method'] ?? null) ? $body['method'] : null;
    }
}
