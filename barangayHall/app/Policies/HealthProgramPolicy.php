<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\HealthProgram;
use App\Models\User;

/**
 * Officials see the program schedule; staff schedule programs and take attendance.
 */
class HealthProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::ProgramsView);
    }

    public function view(User $user, HealthProgram $healthProgram): bool
    {
        return $user->hasPermission(PermissionName::ProgramsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::ProgramsManage);
    }

    public function update(User $user, HealthProgram $healthProgram): bool
    {
        return $user->hasPermission(PermissionName::ProgramsManage);
    }

    public function delete(User $user, HealthProgram $healthProgram): bool
    {
        return $user->hasPermission(PermissionName::ProgramsManage);
    }

    public function recordAttendance(User $user, HealthProgram $healthProgram): bool
    {
        return $user->hasPermission(PermissionName::ProgramsManage);
    }
}
