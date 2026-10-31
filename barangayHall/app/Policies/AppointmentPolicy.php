<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::AppointmentsManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionName::AppointmentsManage);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasPermission(PermissionName::AppointmentsManage);
    }
}
