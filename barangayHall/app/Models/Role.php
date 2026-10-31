<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string|null $description
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, User> $users
 */
#[Fillable(['name', 'label', 'description'])]
class Role extends Model
{
    /** @var BaseCollection<int, string>|null */
    private ?BaseCollection $permissionNameCache = null;

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Find a role by name, creating it with its default permission matrix if missing.
     */
    public static function for(RoleName $name): self
    {
        $role = self::query()->firstOrCreate(['name' => $name->value], ['label' => $name->label()]);

        if ($role->wasRecentlyCreated) {
            $permissionIds = collect(PermissionName::forRole($name))
                ->map(fn (PermissionName $permission) => Permission::query()->firstOrCreate(
                    ['name' => $permission->value],
                    ['label' => $permission->label()],
                )->id);

            $role->permissions()->sync($permissionIds);
        }

        return $role;
    }

    /**
     * Permission names for this role, loaded once per request.
     *
     * @return BaseCollection<int, string>
     */
    public function permissionNames(): BaseCollection
    {
        return $this->permissionNameCache ??= $this->permissions()->pluck('name');
    }
}
