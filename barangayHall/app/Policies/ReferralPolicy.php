<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Referral;
use App\Models\User;

/**
 * Referral reasons are clinical: viewing needs health.view_sensitive.
 */
class ReferralPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::HealthViewSensitive);
    }

    public function view(User $user, Referral $referral): bool
    {
        return $user->hasPermission(PermissionName::HealthViewSensitive);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::ReferralsManage);
    }

    public function update(User $user, Referral $referral): bool
    {
        return $user->hasPermission(PermissionName::ReferralsManage);
    }
}
