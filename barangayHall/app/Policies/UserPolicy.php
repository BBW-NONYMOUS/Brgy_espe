<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::UsersManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::UsersManage);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionName::UsersManage);
    }

    /**
     * Changing one's own role / active flag is blocked in UserRequest.
     */
    public function resetPassword(User $user, User $model): bool
    {
        return $user->hasPermission(PermissionName::UsersManage);
    }
}
