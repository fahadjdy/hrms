<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Support\McpCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * The streamable-HTTP endpoint and the OAuth discovery documents MCP clients
 * (claude.ai, the Claude apps, ChatGPT) rely on.
 */
class McpHttpTest extends TestCase
{
    use InteractsWithCompanies, InteractsWithMcp, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePassportKeys();
    }

    public function test_initialize_without_a_token_is_challenged_with_the_resource_metadata_url(): void
    {
        $response = $this->postJson('/mcp', $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ]), $this->mcpHeaders());

        $response->assertUnauthorized();
        $this->assertStringContainsString(
            'resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp').'"',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_server_discover_is_answered_without_a_token(): void
    {
        $response = $this->postJson('/mcp', $this->rpc('server/discover'), $this->mcpHeaders());

        $response->assertOk();
    }

    public function test_tools_list_works_with_a_passport_token_of_the_user(): void
    {
        $company = $this->useCompany($this->createCompany());
        Passport::actingAs($this->adminOf($company), ['mcp:use'], 'api');

        $response = $this->postJson('/mcp', $this->rpc('tools/list'), $this->mcpHeaders());

        $response->assertOk();
        $this->assertContains('list_employees', array_column($response->json('result.tools'), 'name'));
    }

    public function test_tools_call_works_with_a_personal_access_token(): void
    {
        $company = $this->useCompany($this->createCompany(['name' => 'Acme Fabrics']));
        $token = McpCredentials::issue($this->adminOf($company), 'Claude Code')->accessToken;

        $response = $this->postJson('/mcp/hrms', $this->rpc('tools/call', ['name' => 'get_me', 'arguments' => []]), $this->mcpHeaders($token));

        $response->assertOk();
        $this->assertStringContainsString('Acme Fabrics', (string) $response->json('result.content.0.text'));
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $response = $this->postJson('/mcp', $this->rpc('tools/list'), $this->mcpHeaders('not-a-real-token'));

        $response->assertUnauthorized();
    }

    public function test_protected_resource_metadata_is_served_for_the_mcp_endpoints_only(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('authorization_servers', [url('/')]);

        $this->getJson('/.well-known/oauth-protected-resource/mcp/hrms')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp/hrms'));

        $this->getJson('/.well-known/oauth-protected-resource/employees')
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }

    public function test_authorization_server_metadata_points_at_passport(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'));
    }

    public function test_root_authorize_fallback_redirects_to_passport_keeping_the_query(): void
    {
        $this->get('/authorize?client_id=x&state=y')->assertRedirect('/oauth/authorize?client_id=x&state=y');
    }
}
