<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Pregnancy;
use App\Models\User;

/**
 * Pregnancy, delivery and postnatal records. Everyone with health access may
 * view them; only Midwife/Admin (maternal.manage) may change them.
 */
class PregnancyPolicy
{
    public function view(User $user, Pregnancy $pregnancy): bool
    {
        return $user->hasPermission(PermissionName::HealthViewSensitive);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::MaternalManage);
    }

    public function update(User $user, Pregnancy $pregnancy): bool
    {
        return $user->hasPermission(PermissionName::MaternalManage);
    }

    public function delete(User $user, Pregnancy $pregnancy): bool
    {
        return $user->hasPermission(PermissionName::MaternalManage);
    }
}
