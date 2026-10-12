<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Mcp\Servers\HrmsServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Passport\Http\Controllers\AccessTokenController;

/*
|--------------------------------------------------------------------------
| MCP server
|--------------------------------------------------------------------------
|
| HTTP (streamable) transport: Claude (claude.ai, the Claude apps, Claude
| Code), ChatGPT and other MCP clients connect to https://<host>/mcp.
| Auth: OAuth 2.1 (the client runs the login + consent flow itself) or a
| personal bearer token from Settings -> AI access / `php artisan mcp:token`.
| Every tool acts as the signed-in user with that user's permissions.
|
| Stdio transport, local use from this checkout:
|   HRMS_MCP_TOKEN=<token> php artisan mcp:start hrms
|
*/

Mcp::oauthRoutes();

// Dynamic client registration needs no login, so cap how fast clients can be
// created (same URI, so this replaces the route registered above).
Route::post('/oauth/register', OAuthRegisterController::class)->middleware('throttle:10,1');

// Mcp::oauthRoutes answers protected-resource metadata for ANY path, so a
// connector pointed at a web page (e.g. /employees instead of /mcp) gets a
// working OAuth flow, finds no MCP server there and re-authorizes forever:
// the consent popup appears to hang. Only the MCP endpoints get metadata.
Route::get('/.well-known/oauth-protected-resource/{path}', function (string $path) {
    if (! in_array(trim($path, '/'), ['mcp', 'mcp/hrms'], true)) {
        return response()->json([
            'error' => 'not_found',
            'error_description' => 'No MCP server at /'.$path.'. Use '.url('/mcp').' as the connector URL.',
        ], 404);
    }

    return response()->json([
        'resource' => url('/'.trim($path, '/')),
        'authorization_servers' => [url('/')],
        'scopes_supported' => ['mcp:use'],
    ]);
})->where('path', '.*')->name('mcp.oauth.protected-resource.nested');

Mcp::web('/mcp', HrmsServer::class)
    ->middleware(AuthenticateMcp::class)
    ->name('mcp.hrms');

// Second URL for the same server, for when a client has cached a failed
// attempt against /mcp and needs a fresh connector URL.
Mcp::web('/mcp/hrms', HrmsServer::class)
    ->middleware(AuthenticateMcp::class)
    ->name('mcp.hrms.alias');

Mcp::local('hrms', HrmsServer::class);

/*
| Fallback OAuth endpoints at the site root. The MCP authorization spec tells
| clients that cannot fetch the authorization-server metadata to assume the
| default paths /authorize, /token and /register. /register is left out on
| purpose: HRMS has no sign-up, so /register stays a 404 (registration of
| OAuth clients is served at /oauth/register, as the metadata advertises).
*/
Route::get('/authorize', fn (Request $request) => redirect()->to('/oauth/authorize'.($request->getQueryString() ? '?'.$request->getQueryString() : '')))
    ->name('mcp.oauth.fallback.authorize');

Route::post('/token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle')
    ->name('mcp.oauth.fallback.token');
