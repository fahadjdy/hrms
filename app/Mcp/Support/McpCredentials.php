<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\PersonalAccessTokenResult;
use Laravel\Passport\Token;
use RuntimeException;

/**
 * Issues and inspects the Passport tokens that authenticate MCP clients.
 *
 * Two kinds of token reach `POST /mcp` (guard `auth:api`):
 *  - personal access tokens, issued here (Settings -> AI access) for Claude Code / Desktop /
 *    stdio use and pasted into a client config;
 *  - OAuth 2.1 authorization-code tokens, issued by Passport when a Claude
 *    connector (claude.ai, mobile app) completes the login + consent flow.
 */
final class McpCredentials
{
    public const PERSONAL_CLIENT_NAME = 'HRMS MCP (personal tokens)';

    /**
     * Issue a long-lived personal access token for the given user.
     *
     * @return PersonalAccessTokenResult<User>
     */
    public static function issue(User $user, string $label): PersonalAccessTokenResult
    {
        self::ensurePersonalAccessClient($user->getProviderName());

        return $user->createToken($label, [Registrar::OAUTH_SCOPE]);
    }

    /**
     * Passport needs one "personal access" client per user provider to mint
     * personal tokens. Create it on first use so no console step is required
     * on shared hosting.
     */
    public static function ensurePersonalAccessClient(string $provider = 'users'): Client
    {
        $clients = app(ClientRepository::class);

        try {
            return $clients->personalAccessClient($provider);
        } catch (RuntimeException) {
            return $clients->createPersonalAccessGrantClient(self::PERSONAL_CLIENT_NAME, $provider);
        }
    }

    /**
     * Resolve the user behind a bearer token outside the HTTP layer (stdio
     * transport). Uses the same Passport guard as `POST /mcp`, so revoked or
     * expired tokens are rejected identically.
     */
    public static function userFromBearerToken(string $token): ?User
    {
        $request = Request::create('/mcp', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.trim($token)]);

        $user = Auth::guard('api')->setRequest($request)->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Describe a token for the admin UI / CLI without exposing the secret.
     *
     * @return array<string, mixed>
     */
    public static function present(Token $token): array
    {
        $client = $token->client;
        $isPersonal = $client?->hasGrantType('personal_access') ?? false;

        return [
            'id' => $token->id,
            'label' => $token->name ?: ($client?->name ?: 'OAuth client'),
            'kind' => $isPersonal ? 'personal' : 'oauth',
            'client_name' => $client?->name,
            'scopes' => $token->scopes ?? [],
            'created_at' => $token->created_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ];
    }

    /** Revoke a token together with its refresh token. */
    public static function revoke(Token $token): void
    {
        $token->revoke();
        $token->refreshToken?->revoke();
    }

    public const CLAUDE_CLIENT_NAME = 'Claude connector (claude.ai)';

    /** Redirect URIs Claude uses for custom connectors (claude.ai / Claude app / Desktop). */
    public const CLAUDE_REDIRECT_URIS = [
        'https://claude.ai/api/mcp/auth_callback',
        'https://claude.com/api/mcp/auth_callback',
    ];

    /**
     * A pre-registered public OAuth client for Claude, so the connector can be
     * added with an explicit "OAuth Client ID" instead of relying on Dynamic
     * Client Registration. Public + PKCE, exactly like the client Claude would
     * register itself. Created on first use.
     */
    public static function ensureClaudeClient(): Client
    {
        $existing = Passport::client()->newQuery()
            ->where('name', self::CLAUDE_CLIENT_NAME)
            ->where('revoked', false)
            ->latest()
            ->get()
            ->first(fn (Client $c) => $c->hasGrantType('authorization_code'));

        if ($existing) {
            return $existing;
        }

        return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: self::CLAUDE_CLIENT_NAME,
            redirectUris: self::CLAUDE_REDIRECT_URIS,
            confidential: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentClient(Client $client): array
    {
        return [
            'id' => (string) $client->id,
            'name' => $client->name,
            'redirect_uris' => $client->redirect_uris ?? [],
            'confidential' => $client->confidential(),
            'created_at' => $client->created_at?->toIso8601String(),
        ];
    }
}
