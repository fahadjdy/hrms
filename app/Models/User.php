<?php

namespace App\Models;

use App\Enums\Permission;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $role_id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property bool $is_super_admin
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read Role|null $role
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Mirrors the column defaults so a newly created model has them before a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_super_admin' => false,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The role is loaded without the tenant scope because it is resolved while
     * authenticating, before the tenant context exists. The user's own
     * `role_id` is the only key used, so no other company's role is reachable.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class)->withoutGlobalScope(CompanyScope::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->is_super_admin || $this->company_id === null) {
            return false;
        }

        $role = $this->role;

        return $role !== null
            && $role->company_id === $this->company_id
            && $role->allows($permission);
    }

    /**
     * @return list<string>
     */
    public function permissionList(): array
    {
        $role = $this->company_id !== null ? $this->role : null;

        if ($role === null || $role->company_id !== $this->company_id) {
            return [];
        }

        return $role->permissions;
    }
}
