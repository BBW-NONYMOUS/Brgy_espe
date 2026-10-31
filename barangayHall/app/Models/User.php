<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Observers\AuditableObserver;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $username
 * @property string $name
 * @property string|null $email
 * @property int $role_id
 * @property string $password
 * @property bool $is_active
 * @property bool $must_change_password
 * @property int $failed_attempts
 * @property CarbonImmutable|null $locked_until
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $password_changed_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Role $role
 */
#[Fillable(['username', 'name', 'email', 'role_id', 'password', 'is_active', 'must_change_password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy(AuditableObserver::class)]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(RoleName $role): bool
    {
        return $this->role->name === $role->value;
    }

    public function hasPermission(PermissionName|string $permission): bool
    {
        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        return $this->role->permissionNames()->contains($name);
    }

    /**
     * Active users whose role grants any of the permissions (notification recipients).
     *
     * @param  Builder<self>  $query
     * @param  PermissionName|list<PermissionName>  $permissions
     */
    public function scopeWithPermission(Builder $query, PermissionName|array $permissions): void
    {
        $names = array_map(fn (PermissionName $p) => $p->value, is_array($permissions) ? $permissions : [$permissions]);

        $query->where('is_active', true)
            ->whereHas('role.permissions', fn (Builder $q) => $q->whereIn('name', $names));
    }

    /**
     * Login bookkeeping is recorded as login/locked audit events instead.
     *
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return ['failed_attempts', 'locked_until', 'last_login_at', 'password_changed_at'];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}
