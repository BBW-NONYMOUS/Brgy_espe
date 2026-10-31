<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy for clinical records: HealthVisit, Immunization,
 * ResidentCondition and Pregnancy (registered in AppServiceProvider).
 */
class HealthRecordPolicy
{
    public function view(User $user, Model $record): bool
    {
        return $user->hasPermission(PermissionName::HealthViewSensitive);
    }

    public function update(User $user, Model $record): bool
    {
        return $user->hasPermission(PermissionName::HealthRecord);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->hasPermission(PermissionName::HealthRecord);
    }
}
