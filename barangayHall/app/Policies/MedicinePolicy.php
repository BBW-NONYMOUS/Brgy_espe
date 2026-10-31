<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Medicine;
use App\Models\User;

class MedicinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::InventoryView);
    }

    public function view(User $user, Medicine $medicine): bool
    {
        return $user->hasPermission(PermissionName::InventoryView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::InventoryManage);
    }

    public function update(User $user, Medicine $medicine): bool
    {
        return $user->hasPermission(PermissionName::InventoryManage);
    }

    public function dispense(User $user, Medicine $medicine): bool
    {
        return $user->hasPermission(PermissionName::InventoryDispense);
    }
}
