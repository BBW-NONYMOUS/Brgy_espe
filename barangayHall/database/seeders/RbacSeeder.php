<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    /**
     * Seed roles, permissions and the default role → permission matrix.
     */
    public function run(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::query()->updateOrCreate(['name' => $permission->value], ['label' => $permission->label()]);
        }

        $descriptions = [
            RoleName::Admin->value => 'Manages accounts, oversees all records, reports, audit logs and backups.',
            RoleName::Bhw->value => 'Registers residents and households, geo-tags, records health information, appointments and program attendance.',
            RoleName::Midwife->value => 'Manages maternal and child health, referrals, medicine inventory and disease case reports.',
            RoleName::Official->value => 'Read-only access to aggregated reports, outbreak summaries, purok-level maps and the program schedule.',
        ];

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName->value],
                ['label' => $roleName->label(), 'description' => $descriptions[$roleName->value]],
            );

            $role->permissions()->sync(
                Permission::query()
                    ->whereIn('name', array_map(fn (PermissionName $p) => $p->value, PermissionName::forRole($roleName)))
                    ->pluck('id'),
            );
        }
    }
}
