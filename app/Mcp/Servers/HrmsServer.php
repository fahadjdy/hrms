<?php

namespace App\Mcp\Servers;

use App\Mcp\Support\McpCredentials;
use App\Mcp\Support\ToolDefinitions;
use App\Mcp\Tools\CallRouteTool;
use App\Mcp\Tools\GetMeTool;
use App\Mcp\Tools\ListRoutesTool;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('HRMS')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
HRMS is a multi-company HR and payroll system. Hierarchy: Super Admin (platform owner) → Company (tenant) → Company Admin / HR users → Employees. Employees are HR records only; they never sign in.

Every tool runs AS THE CONNECTED ACCOUNT, through the same permission checks, company isolation and validation as the web app:
- Super admin: companies (list / create / edit / activate), platform settings, own profile.
- Company user: only the areas their role's permissions allow (employees, attendance, leave, payroll, finance, settlements, reports, settings, roles, audit). Tools the account cannot use are not listed.
Call get_me first to learn the account, company, role and permissions.

Conventions: ids come from the matching list_* tool. Dates YYYY-MM-DD, months YYYY-MM, times HH:MM (24h), money in the company currency. List tools take the same filters as the web pages (search, status, department, month, date range, page).

Results: read tools return {"ok": true, "data": {...page props...}}. Write tools return {"ok": true, "message": "Saved.", "redirect": "/employees/12", "flash": {"toast": {...}}}; the id of a created record is usually in `redirect`. Failures are tool errors with the reason (validation errors per field, missing permission, not found).

Typical flows:
- Monthly payroll: mark attendance (bulk_mark_attendance / generate_attendance) → record overtime, short hours, bonuses, deductions, borrow recoveries → create_payroll for the month → calculate_payroll → start_payroll_review → add_payroll_adjustment → finalize_payroll → list_salary_slips. A finalized payroll is locked; reopen_payroll needs a reason.
- Employee exit: exit_employee → get_final_settlement (prepares the draft) → update_final_settlement → finalize_final_settlement → mark_final_settlement_paid.
History is append-only (salary revisions, borrow transactions, attendance logs, audit logs) and employees are never deleted.

Anything without a dedicated tool: list_routes, then call_route.
Write tools change real company data: confirm with the user before deleting, finalizing, exiting an employee, or any money-related action.
MD)]
class HrmsServer extends Server
{
    protected array $tools = [
        GetMeTool::class,
        ListRoutesTool::class,
        CallRouteTool::class,
    ];

    public int $maxPaginationLength = 200;

    public int $defaultPaginationLength = 200;

    /**
     * Over HTTP the AuthenticateMcp middleware has already authenticated the
     * bearer token. Over stdio (`php artisan mcp:start hrms`) there is no HTTP
     * layer, so authenticate from the environment instead:
     *   HRMS_MCP_TOKEN - a token from Settings → AI access or `php artisan mcp:token <email>`
     *   HRMS_MCP_USER  - an email to run as (local environment only, no token needed)
     */
    protected function boot(): void
    {
        $this->tools = [...$this->tools, ...ToolDefinitions::tools()];

        if (Auth::check() || ! app()->runningInConsole()) {
            return;
        }

        $user = null;

        if ($token = (string) config('mcp.stdio.token')) {
            $user = McpCredentials::userFromBearerToken($token);
        }

        if (! $user && app()->environment('local') && ($email = (string) config('mcp.stdio.user'))) {
            $user = User::query()->where('email', mb_strtolower($email))->first();
        }

        if ($user instanceof User && $user->is_active) {
            Auth::guard('api')->setUser($user);
            Auth::shouldUse('api');
        }
    }
}
