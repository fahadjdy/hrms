<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mcp\Support\McpCredentials;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

/**
 * Settings -> AI access: lets any signed-in user connect Claude, ChatGPT or
 * another MCP client to HRMS. The client then acts as this user, limited to
 * this user's own permissions.
 */
class McpAccessController extends Controller
{
    /**
     * Show the MCP URL, the user's live tokens and how to connect.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $tokens = $user->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Token $token): array => McpCredentials::present($token))
            ->values()
            ->all();

        return Inertia::render('settings/AiAccess', [
            'mcpUrl' => $this->mcpUrl(),
            'tokens' => $tokens,
            'claudeClientId' => (string) McpCredentials::ensureClaudeClient()->id,
            'claudeCodeCommand' => $this->claudeCodeCommand('<token>'),
        ]);
    }

    /**
     * Create a personal access token. The secret is flashed once and never
     * stored or shown again.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:60'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $label = trim($validated['label']);

        $result = McpCredentials::issue($user, $label);

        $this->audit($user, 'mcp_token.created', null, ['label' => $label], "AI access token \"{$label}\" created");

        Inertia::flash('mcpToken', [
            'label' => $label,
            'token' => $result->accessToken,
            'command' => $this->claudeCodeCommand($result->accessToken),
        ]);
        $this->toast(__('Token created. Copy it now; it will not be shown again.'));

        return to_route('mcp-access.edit');
    }

    /**
     * Revoke one of the signed-in user's own tokens (personal or connector).
     */
    public function destroy(Request $request, string $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Token $model */
        $model = $user->tokens()->with('client')->whereKey($token)->firstOrFail();
        $label = McpCredentials::present($model)['label'];

        McpCredentials::revoke($model);

        $this->audit($user, 'mcp_token.revoked', ['label' => $label], null, "AI access token \"{$label}\" revoked");
        $this->toast(__('Access revoked.'));

        return to_route('mcp-access.edit');
    }

    private function mcpUrl(): string
    {
        return url('/mcp');
    }

    private function claudeCodeCommand(string $token): string
    {
        return "claude mcp add --transport http --scope user hrms {$this->mcpUrl()} --header \"Authorization: Bearer {$token}\"";
    }

    /**
     * Audit logs belong to a company, so only company users' token changes
     * are recorded; the super admin has no company to log against.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(User $user, string $action, ?array $old, ?array $new, string $description): void
    {
        $company = $user->company;

        if ($company === null) {
            return;
        }

        $this->tenant()->run($company, fn () => app(AuditLogger::class)->log(
            $action,
            $user,
            $old,
            $new,
            $description,
        ));
    }
}
