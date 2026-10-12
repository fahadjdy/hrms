<?php

namespace Tests\Feature\Settings;

use App\Mcp\Support\McpCredentials;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Tests\TestCase;

class McpAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Signing tokens needs the OAuth key pair; create it on machines that
        // have never run `passport:keys` (the files are not committed).
        if (! is_file(Passport::keyPath('oauth-private.key')) || ! is_file(Passport::keyPath('oauth-public.key'))) {
            Artisan::call('passport:keys', ['--force' => true]);
        }

        // The page and token creation sit behind password confirmation.
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_page_renders_for_a_company_user(): void
    {
        $user = User::factory()->forCompany(Company::factory()->create())->create();

        $this->actingAs($user)
            ->get(route('mcp-access.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/AiAccess')
                ->where('mcpUrl', url('/mcp'))
                ->where('tokens', [])
                ->where('claudeClientId', (string) McpCredentials::ensureClaudeClient()->id)
                ->has('claudeCodeCommand'),
            );
    }

    public function test_page_renders_for_the_super_admin_and_lists_live_tokens(): void
    {
        $admin = User::factory()->superAdmin()->create();
        McpCredentials::issue($admin, 'laptop');
        $revoked = McpCredentials::issue($admin, 'old')->token;
        McpCredentials::revoke($revoked);

        $this->actingAs($admin)
            ->get(route('mcp-access.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/AiAccess')
                ->has('tokens', 1)
                ->where('tokens.0.label', 'laptop')
                ->where('tokens.0.kind', 'personal'),
            );
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('mcp-access.edit'))->assertRedirect(route('login'));
    }

    public function test_page_and_token_creation_require_a_recent_password_confirmation(): void
    {
        $user = User::factory()->forCompany(Company::factory()->create())->create();
        $this->withSession(['auth.password_confirmed_at' => null]);

        $this->actingAs($user)->get(route('mcp-access.edit'))->assertRedirect(route('password.confirm'));
        $this->actingAs($user)->post(route('mcp-access.store'), ['label' => 'Claude'])->assertRedirect(route('password.confirm'));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_created_token_is_flashed_once_and_authenticates_the_mcp_endpoint(): void
    {
        $user = User::factory()->forCompany(Company::factory()->create())->create();

        $response = $this->actingAs($user)->post(route('mcp-access.store'), ['label' => 'Claude Code']);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('mcp-access.edit'))
            ->assertInertiaFlash('mcpToken.label', 'Claude Code');

        $flashed = session(SessionKey::FLASH_DATA)['mcpToken'];
        $this->assertNotEmpty($flashed['token']);
        $this->assertStringContainsString("Bearer {$flashed['token']}", $flashed['command']);
        $this->assertSame(1, $user->tokens()->where('name', 'Claude Code')->count());
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'action' => 'mcp_token.created',
        ]);

        // Only the bearer token may authenticate the MCP request.
        $this->app['auth']->forgetGuards();

        $this->callMcp($flashed['token'])->assertOk();
    }

    public function test_mcp_endpoint_rejects_a_missing_or_revoked_token(): void
    {
        $user = User::factory()->superAdmin()->create();
        $result = McpCredentials::issue($user, 'temp');
        McpCredentials::revoke($result->token);

        $this->callMcp(null)->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->callMcp($result->accessToken)->assertUnauthorized();
    }

    public function test_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->forCompany(Company::factory()->create())->create();
        $token = McpCredentials::issue($user, 'phone')->token;

        $this->actingAs($user)
            ->delete(route('mcp-access.destroy', $token->id))
            ->assertRedirect(route('mcp-access.edit'));

        $this->assertTrue(Token::query()->findOrFail($token->id)->revoked);
        $this->assertTrue(AuditLog::withoutTenancy()->where('action', 'mcp_token.revoked')->where('user_id', $user->id)->exists());
    }

    public function test_super_admin_can_create_and_revoke_tokens_without_an_audit_entry(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('mcp-access.store'), ['label' => 'Desktop'])->assertSessionHasNoErrors();
        $token = $admin->tokens()->firstOrFail();

        $this->actingAs($admin)->delete(route('mcp-access.destroy', $token->id))->assertRedirect(route('mcp-access.edit'));

        $this->assertTrue($token->refresh()->revoked);
        $this->assertSame(0, AuditLog::withoutTenancy()->count());
    }

    public function test_revoking_another_users_token_is_not_found(): void
    {
        $owner = User::factory()->forCompany(Company::factory()->create())->create();
        $other = User::factory()->forCompany(Company::factory()->create())->create();
        $token = McpCredentials::issue($owner, 'mine')->token;

        $this->actingAs($other)
            ->delete(route('mcp-access.destroy', $token->id))
            ->assertNotFound();

        $this->assertFalse(Token::query()->findOrFail($token->id)->revoked);
    }

    public function test_a_label_is_required(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->post(route('mcp-access.store'), ['label' => ''])
            ->assertSessionHasErrors('label');

        $this->assertSame(0, $user->tokens()->count());
    }

    private function callMcp(?string $token): TestResponse
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
        ];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $headers);
    }
}
