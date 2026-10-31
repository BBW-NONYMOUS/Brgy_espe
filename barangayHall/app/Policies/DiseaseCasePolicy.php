<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\DiseaseCase;
use App\Models\User;

/**
 * BHWs report cases; only Midwife/Admin confirm, rule out or close them.
 */
class DiseaseCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::DiseasesView);
    }

    public function view(User $user, DiseaseCase $diseaseCase): bool
    {
        return $user->hasPermission(PermissionName::DiseasesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::DiseasesRecord);
    }

    public function update(User $user, DiseaseCase $diseaseCase): bool
    {
        return $user->hasPermission(PermissionName::DiseasesClassify);
    }

    public function delete(User $user, DiseaseCase $diseaseCase): bool
    {
        return $user->hasPermission(PermissionName::DiseasesClassify);
    }
}
