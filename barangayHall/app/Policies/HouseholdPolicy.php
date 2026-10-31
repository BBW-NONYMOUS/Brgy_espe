<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Household;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class HouseholdPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::ResidentsView);
    }

    public function view(User $user, Household $household): bool
    {
        return $user->hasPermission(PermissionName::ResidentsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::HouseholdsManage);
    }

    public function update(User $user, Household $household): bool
    {
        return $user->hasPermission(PermissionName::HouseholdsManage);
    }

    public function delete(User $user, Household $household): Response
    {
        if (! $user->hasPermission(PermissionName::HouseholdsManage)) {
            return Response::deny();
        }

        return $household->members()->withTrashed()->exists()
            ? Response::deny('Move or remove all members before deleting this household.')
            : Response::allow();
    }

    /**
     * Save or clear the household's GPS location. The geo-tagging consent is
     * checked by GeotagRequest (it needs the request data).
     */
    public function geotag(User $user, Household $household): bool
    {
        return $user->hasPermission(PermissionName::HouseholdsGeotag);
    }
}
