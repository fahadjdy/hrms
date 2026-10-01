<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Roles, their permissions and the users of the company.
     */
    public function index(Request $request): Response
    {
        $companyId = $this->company()->id;

        return Inertia::render('company-settings/Roles', [
            'roles' => Role::query()
                ->withCount('users')
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    ...$role->only(['id', 'name', 'slug', 'permissions', 'is_system']),
                    'users_count' => $role->users_count,
                    'is_admin' => $role->slug === Role::ADMIN_SLUG,
                ]),
            'permissions' => Permission::options(),
            'users' => User::query()
                ->where('company_id', $companyId)
                ->with('role')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role' => $user->role?->name,
                    'is_active' => $user->is_active,
                    'is_self' => $user->id === $request->user()->id,
                ]),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);

        $role = new Role([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'permissions' => $validated['permissions'],
        ]);
        $role->save();

        $audit->log('role.created', $role, null, $role->only(['name', 'permissions']), "Role {$role->name} created");
        $this->toast('Role created.');

        return back();
    }

    public function update(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        if ($role->slug === Role::ADMIN_SLUG) {
            throw ValidationException::withMessages([
                'role' => 'The Company Admin role always has every permission and cannot be changed.',
            ]);
        }

        $validated = $this->validated($request, $role);
        $old = $role->only(['name', 'permissions']);

        $role->update(['name' => $validated['name'], 'permissions' => $validated['permissions']]);

        $audit->log('role.updated', $role, $old, $role->only(['name', 'permissions']), "Role {$role->name} updated");
        $this->toast('Role updated.');

        return back();
    }

    public function destroy(Role $role, AuditLogger $audit): RedirectResponse
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => 'Built-in roles cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'This role is still assigned to users. Give them another role first.']);
        }

        $audit->log('role.deleted', $role, $role->only(['name', 'permissions']), null, "Role {$role->name} deleted");
        $role->delete();

        $this->toast('Role deleted.');

        return back();
    }

    /**
     * @return array{name: string, permissions: list<string>}
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(Permission::class)],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $suffix = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".$suffix++;
        }

        return $slug;
    }
}
