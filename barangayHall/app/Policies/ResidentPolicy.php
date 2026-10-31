<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ResidentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::ResidentsView);
    }

    public function view(User $user, Resident $resident): bool
    {
        return $user->hasPermission(PermissionName::ResidentsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::ResidentsCreate);
    }

    public function update(User $user, Resident $resident): bool
    {
        return $user->hasPermission(PermissionName::ResidentsUpdate) && ! $resident->trashed();
    }

    /**
     * Archive (soft delete).
     */
    public function delete(User $user, Resident $resident): bool
    {
        return $user->hasPermission(PermissionName::ResidentsArchive) && ! $resident->trashed();
    }

    public function restore(User $user, Resident $resident): bool
    {
        return $user->hasPermission(PermissionName::ResidentsArchive) && $resident->trashed();
    }

    /**
     * Permanent deletion: Admin only, and only after the retention period.
     */
    public function forceDelete(User $user, Resident $resident): Response
    {
        if (! $user->hasPermission(PermissionName::ResidentsForceDelete)) {
            return Response::deny('Only an administrator can permanently delete records.');
        }

        if (! $resident->trashed()) {
            return Response::deny('Archive the resident before deleting permanently.');
        }

        $years = (int) config('barangay.retention_years');

        return $resident->deleted_at?->lte(now()->subYears($years))
            ? Response::allow()
            : Response::deny("Archived records must be kept for {$years} years before permanent deletion.");
    }

    /**
     * Full PhilHealth number, diagnosis, clinical notes.
     */
    public function viewSensitive(User $user, Resident $resident): bool
    {
        return $user->hasPermission(PermissionName::HealthViewSensitive);
    }

    public function recordHealth(User $user, Resident $resident): bool
    {
        return $user->hasPermission(PermissionName::HealthRecord) && ! $resident->trashed();
    }

    public function recordConsent(User $user, Resident $resident): bool
    {
        return $user->hasPermission(PermissionName::ConsentsRecord) && ! $resident->trashed();
    }
}
