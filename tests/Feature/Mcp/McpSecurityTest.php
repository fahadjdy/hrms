<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Support\McpCredentials;
use App\Mcp\Support\RouteBridge;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

/**
 * Hardening of the MCP surface: tokens of deactivated accounts, state carried
 * between calls, fields an agent may not change and OAuth client registration.
 */
class McpSecurityTest extends TestCase
{
    use InteractsWithCompanies, InteractsWithMcp, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePassportKeys();
        $this->company = $this->useCompany($this->createCompany(['name' => 'Company A']));
    }

    public function test_a_token_stops_working_when_the_user_is_deactivated(): void
    {
        $admin = $this->adminOf($this->company);
        $token = McpCredentials::issue($admin, 'Claude')->accessToken;

        $this->postJson('/mcp', $this->rpc('tools/list'), $this->mcpHeaders($token))->assertOk();

        $admin->forceFill(['is_active' => false])->save();
        // A new HTTP request resolves the token user again; tests reuse the app.
        app('auth')->forgetGuards();

        $this->postJson('/mcp', $this->rpc('tools/list'), $this->mcpHeaders($token))
            ->assertForbidden()
            ->assertJsonPath('error', 'account_deactivated');
    }

    public function test_a_token_stops_working_when_the_company_is_deactivated(): void
    {
        $token = McpCredentials::issue($this->adminOf($this->company), 'Claude')->accessToken;

        $this->company->forceFill(['is_active' => false])->save();

        $this->postJson('/mcp', $this->rpc('tools/call', ['name' => 'get_my_profile', 'arguments' => []]), $this->mcpHeaders($token))
            ->assertForbidden();
    }

    public function test_the_bridge_refuses_a_deactivated_user_even_on_personal_routes(): void
    {
        $admin = $this->adminOf($this->company);
        $admin->is_active = false;

        $this->expectExceptionMessage('deactivated');

        RouteBridge::call($admin, 'profile.edit');
    }

    public function test_flash_data_does_not_leak_into_the_next_call(): void
    {
        $admin = $this->adminOf($this->company);

        // A previous call in the same process left an error toast behind.
        $store = app('session')->driver('array');
        Inertia::flash('toast', ['type' => 'error', 'message' => 'LEAKED']);
        $store->flash('status', 'LEAKED');
        $store->save();

        $result = RouteBridge::call($admin, 'departments.store', [], ['name' => 'Logistics']);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertStringNotContainsString('LEAKED', json_encode($result));
    }

    public function test_the_tenant_set_before_a_call_is_restored_afterwards(): void
    {
        $other = $this->createCompany(['name' => 'Company B']);
        $this->useCompany($other);

        RouteBridge::call($this->adminOf($this->company), 'departments.index');

        $this->assertSame($other->id, $this->tenant()->id());
    }

    public function test_the_sign_in_email_cannot_be_changed_through_mcp(): void
    {
        $admin = $this->adminOf($this->company);
        $email = $admin->email;

        $this->callTool($admin, 'update_my_profile', ['name' => 'New Name', 'email' => 'attacker@example.com'])
            ->assertHasErrors(['can only be changed in the web app']);

        $this->callTool($admin, 'update_my_profile', ['name' => 'New Name'])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'New Name', 'email' => $email]);
    }

    public function test_oauth_clients_can_only_redirect_to_known_ai_apps(): void
    {
        $register = fn (string $uri) => $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => [$uri],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);

        $register('https://evil.example/callback')->assertStatus(400);
        $register('https://claude.ai/api/mcp/auth_callback')->assertSuccessful();
        $register('https://chatgpt.com/connector_platform_oauth_redirect')->assertSuccessful();
        $register('http://localhost:33418/callback')->assertSuccessful();
    }

    public function test_client_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/oauth/register', [])->assertStatus(400);
        }

        $this->postJson('/oauth/register', [])->assertTooManyRequests();
    }
}
