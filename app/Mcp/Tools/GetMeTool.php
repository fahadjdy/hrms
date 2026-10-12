<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\CallableRoutes;
use App\Mcp\Support\RouteAccess;
use App\Mcp\Support\RouteTool;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_me')]
#[Title('Who am I')]
#[Description('The connected account: name, email, super admin or not, company, role and permissions. Call this first: every tool acts as this user with exactly these permissions.')]
#[IsReadOnly]
class GetMeTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $user->loadMissing('company', 'role');

        return RouteTool::respond([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'company' => $user->company === null ? null : [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'is_active' => $user->company->is_active,
                'currency' => $user->company->currency,
                'timezone' => $user->company->timezone,
            ],
            'role' => $user->company_id !== null ? $user->role?->name : null,
            'permissions' => $user->permissionList(),
            'scope' => match (true) {
                $user->is_super_admin => 'Super admin: platform tools (companies, platform settings) and your own profile. Company records are managed by each company\'s own users.',
                RouteAccess::isCompanyUser($user) => 'Company user: the company tools your permissions allow, plus your own profile.',
                default => 'No active company: only your own profile.',
            },
            'callable_routes' => count(CallableRoutes::forUser($user)),
        ]);
    }
}
