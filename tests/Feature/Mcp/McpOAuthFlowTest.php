<?php

namespace Tests\Feature\Mcp;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * The OAuth 2.1 flow a Claude connector runs: dynamic client registration,
 * login, consent, authorization code with PKCE, token, then MCP calls.
 */
class McpOAuthFlowTest extends TestCase
{
    use InteractsWithCompanies, InteractsWithMcp, RefreshDatabase;

    private const CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePassportKeys();
        $this->company = $this->useCompany($this->createCompany(['name' => 'Acme Fabrics']));
        $this->admin = $this->adminOf($this->company);
    }

    public function test_connector_registers_gets_consent_and_calls_tools_with_the_issued_token(): void
    {
        $clientId = $this->registerClient();
        $verifier = Str::random(64);

        $consent = $this->actingAs($this->admin)->get($this->authorizeUrl($clientId, $verifier, 'state-123'));

        $consent->assertOk()
            ->assertSee('Connect Claude to', false)
            ->assertSee('Authorize', false)
            ->assertSee($this->admin->email, false);

        $approval = $this->post(route('passport.authorizations.approve'), [
            'state' => 'state-123',
            'client_id' => $clientId,
            'auth_token' => $this->hiddenInput($consent, 'auth_token'),
        ]);

        $approval->assertRedirect();
        $location = (string) $approval->headers->get('Location');
        $this->assertStringStartsWith(self::CALLBACK.'?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('state-123', $query['state'] ?? null);
        $this->assertNotEmpty($query['code'] ?? null);

        $token = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::CALLBACK,
            'code_verifier' => $verifier,
            'code' => $query['code'],
        ]);

        $token->assertOk();
        $accessToken = (string) $token->json('access_token');
        $this->assertNotSame('', $accessToken);

        $this->app['auth']->forgetGuards();
        $tools = $this->postJson('/mcp', $this->rpc('tools/list'), $this->mcpHeaders($accessToken));

        $tools->assertOk();
        $this->assertContains('list_employees', array_column($tools->json('result.tools'), 'name'));
    }

    public function test_token_exchange_fails_with_the_wrong_code_verifier(): void
    {
        $clientId = $this->registerClient();
        $consent = $this->actingAs($this->admin)->get($this->authorizeUrl($clientId, Str::random(64), 'abc'));
        $approval = $this->post(route('passport.authorizations.approve'), [
            'state' => 'abc',
            'client_id' => $clientId,
            'auth_token' => $this->hiddenInput($consent, 'auth_token'),
        ]);
        parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $query);

        $token = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'redirect_uri' => self::CALLBACK,
            'code_verifier' => Str::random(64),
            'code' => $query['code'] ?? '',
        ]);

        $token->assertStatus(400);
        $this->assertNull($token->json('access_token'));
    }

    public function test_guest_is_sent_to_login_and_returns_to_the_consent_page_as_a_full_page_visit(): void
    {
        $clientId = $this->registerClient();
        $authorizeUrl = $this->authorizeUrl($clientId, Str::random(64), 'xyz');

        $this->get($authorizeUrl)->assertRedirect(route('login'));

        $login = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password']);

        $login->assertStatus(409);
        $location = (string) $login->headers->get('X-Inertia-Location');
        $this->assertSame(url('/oauth/authorize'), strtok($location, '?'));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $actualQuery);
        parse_str((string) parse_url($authorizeUrl, PHP_URL_QUERY), $expectedQuery);
        ksort($expectedQuery);
        ksort($actualQuery);
        $this->assertSame($expectedQuery, $actualQuery);
    }

    private function registerClient(): string
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [self::CALLBACK],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);

        $response->assertSuccessful();
        $clientId = (string) $response->json('client_id');
        $this->assertNotSame('', $clientId);

        return $clientId;
    }

    private function authorizeUrl(string $clientId, string $verifier, string $state): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return '/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::CALLBACK,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
            'scope' => 'mcp:use',
        ]);
    }

    private function hiddenInput(TestResponse $page, string $name): string
    {
        preg_match('/<input type="hidden" name="'.preg_quote($name, '/').'" value="([^"]*)"/', (string) $page->getContent(), $match);

        $this->assertNotEmpty($match[1] ?? null, "The consent page has no hidden [{$name}] input.");

        return html_entity_decode($match[1]);
    }
}
