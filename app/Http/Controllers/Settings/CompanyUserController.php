<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Admin / HR users of the company. These are the only people who can sign
 * in; employees never get an account.
 */
class CompanyUserController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'role_id' => ['required', 'integer', TenantRule::exists('roles')],
        ], [], ['role_id' => 'role']);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $user->company_id = $this->company()->id;
        $user->role_id = (int) $validated['role_id'];
        $user->email_verified_at = now();
        $user->save();

        $audit->log('user.created', $user, null, ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id], "User {$user->name} added");
        $this->toast('User added.');

        return back();
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        // Users are not tenant-scoped models, so ownership is checked explicitly.
        abort_unless($user->company_id === $this->company()->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'integer', TenantRule::exists('roles')],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', Password::defaults()],
        ], [], ['role_id' => 'role']);

        $isSelf = $user->id === $request->user()->id;

        if ($isSelf && (! $validated['is_active'] || (int) $validated['role_id'] !== $user->role_id)) {
            throw ValidationException::withMessages([
                'role_id' => 'You cannot change your own role or deactivate your own account.',
            ]);
        }

        $this->guardLastAdmin($user, (int) $validated['role_id'], (bool) $validated['is_active']);

        $old = ['name' => $user->name, 'role_id' => $user->role_id, 'is_active' => $user->is_active];

        $user->name = $validated['name'];
        $user->role_id = (int) $validated['role_id'];
        $user->is_active = (bool) $validated['is_active'];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        $audit->log('user.updated', $user, $old, ['name' => $user->name, 'role_id' => $user->role_id, 'is_active' => $user->is_active], "User {$user->name} updated");
        $this->toast('User updated.');

        return back();
    }

    /**
     * A company must always keep at least one active Company Admin.
     */
    private function guardLastAdmin(User $user, int $newRoleId, bool $active): void
    {
        $adminRoleId = Role::query()->where('slug', Role::ADMIN_SLUG)->value('id');

        if ($user->role_id !== $adminRoleId || ($newRoleId === $adminRoleId && $active)) {
            return;
        }

        $otherAdmins = User::query()
            ->where('company_id', $this->company()->id)
            ->where('role_id', $adminRoleId)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $otherAdmins) {
            throw ValidationException::withMessages([
                'role_id' => 'This is the only active Company Admin. Make another user a Company Admin first.',
            ]);
        }
    }
}
