<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Reference data only, for a real health center: roles and permissions,
     * puroks, visit types, conditions, vaccines and schedules, diseases and
     * thresholds, facilities. No residents and no accounts — create the first
     * Admin with `php artisan user:create-admin`.
     *
     * Safe to run again after an update: every row is upserted.
     */
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            LookupSeeder::class,
        ]);
    }
}
