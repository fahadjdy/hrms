<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | Only known AI clients are allowed, so nobody can register a look-alike
    | "Claude" client that sends the authorization code to their own site.
    | Localhost (any port) covers desktop clients such as Claude Code. Add
    | more with MCP_EXTRA_REDIRECT_DOMAINS (comma separated, scheme + host).
    |
    */

    'redirect_domains' => array_values(array_filter([
        'https://claude.ai',
        'https://claude.com',
        'https://chatgpt.com',
        'https://chat.openai.com',
        'http://localhost',
        'http://127.0.0.1',
        ...array_map('trim', explode(',', (string) env('MCP_EXTRA_REDIRECT_DOMAINS', ''))),
    ], fn (string $domain) => trim($domain) !== '')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients like Cursor and VS Code use private-use URI
    | schemes (RFC 8252) for redirect callbacks instead of standard schemes
    | like HTTPS. Here, you may list which custom schemes you will allow.
    |
    */

    'custom_schemes' => [
        // 'claude',
        // 'cursor',
        // 'vscode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | Here you may configure the OAuth authorization server issuer identifier
    | per RFC 8414. This value appears in your protected resource and auth
    | server metadata endpoints. When null, this defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

    /*
    |--------------------------------------------------------------------------
    | Tool Search
    |--------------------------------------------------------------------------
    |
    | Here you may configure the limits enforced during tool search. The max
    | number of tool calls limits how many tools search requests can call
    | while the maximum output bytes value will limit the result sizes.
    |
    */

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    /*
    |--------------------------------------------------------------------------
    | HRMS stdio authentication
    |--------------------------------------------------------------------------
    |
    | `php artisan mcp:start hrms` has no HTTP layer to carry a bearer token,
    | so the HRMS server authenticates from these values instead. A token
    | (from `php artisan mcp:token <login>`) works in every environment; the
    | plain login shortcut is honoured only when APP_ENV=local.
    |
    */

    'stdio' => [
        'token' => env('HRMS_MCP_TOKEN'),
        'user' => env('HRMS_MCP_USER'),
    ],

];
