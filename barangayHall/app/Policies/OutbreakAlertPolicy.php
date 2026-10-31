<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\OutbreakAlert;
use App\Models\User;

/**
 * The alert summary (disease, purok, count) is visible to Officials too.
 */
class OutbreakAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::OutbreaksViewSummary);
    }

    public function update(User $user, OutbreakAlert $outbreakAlert): bool
    {
        return $user->hasPermission(PermissionName::OutbreaksManage);
    }
}
