<?php

namespace App\Console\Commands;

use App\Mcp\Support\McpCredentials;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\Token;

#[Signature('mcp:token
    {email : Email address of the account the token should act as}
    {--name=claude : A label for the token (e.g. "claude-laptop")}
    {--revoke : Revoke every live token of this user carrying that label instead of creating one}
    {--list : List the user\'s live tokens (personal and OAuth)}')]
#[Description('Issue, list or revoke an access token for the HRMS MCP server')]
class McpTokenCommand extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user has the email [{$email}].");

            return self::FAILURE;
        }

        $label = (string) $this->option('name');

        $live = fn (): Builder => Token::query()
            ->with('client')
            ->where('user_id', $user->getKey())
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at');

        if ($this->option('list')) {
            $rows = $live()->get()->map(function (Token $token): array {
                $presented = McpCredentials::present($token);

                return [
                    substr((string) $token->id, 0, 12).'…',
                    $presented['label'],
                    $presented['kind'],
                    $presented['created_at'],
                    $presented['expires_at'] ?? 'never',
                ];
            })->all();

            $this->table(['ID', 'Label', 'Kind', 'Created', 'Expires'], $rows);

            return self::SUCCESS;
        }

        if ($this->option('revoke')) {
            $tokens = $live()->where('name', $label)->get();
            $tokens->each(fn (Token $token) => McpCredentials::revoke($token));
            $this->components->info("Revoked {$tokens->count()} token(s) labelled [{$label}] for {$user->name}.");

            return self::SUCCESS;
        }

        $token = McpCredentials::issue($user, $label)->accessToken;
        $url = rtrim((string) config('app.url'), '/').'/mcp';
        $company = $user->company;
        $scope = $user->isSuperAdmin() ? 'super admin' : ($company === null ? 'no company' : $company->name);

        $this->components->info("MCP token for {$user->name} ({$user->email}, {$scope}) - shown once, store it safely:");
        $this->newLine();
        $this->line("  <fg=yellow>{$token}</>");
        $this->newLine();
        $this->line('Connect from Claude Code (any machine / account) over HTTP:');
        $this->line("  claude mcp add --transport http --scope user hrms {$url} --header \"Authorization: Bearer {$token}\"");
        $this->newLine();
        $this->line('Or run locally over stdio from this checkout:');
        $this->line("  claude mcp add hrms --env HRMS_MCP_TOKEN={$token} -- php ".base_path('artisan').' mcp:start hrms');
        $this->newLine();
        $this->line("Revoke later with: php artisan mcp:token {$email} --name={$label} --revoke");

        return self::SUCCESS;
    }
}
