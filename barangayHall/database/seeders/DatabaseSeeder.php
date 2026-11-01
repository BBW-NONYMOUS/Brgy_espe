<?php

namespace Database\Seeders;

use App\Models\Household;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Large-volume test data (10,000 residents) is separate:
     *   php artisan db:seed --class=PerformanceSeeder
     */
    public function run(): void
    {
        // Sample residents and accounts with a shared known password must
        // never reach a real health center.
        if (app()->isProduction()) {
            $this->command->error('Sample data is not allowed in production. Use: php artisan db:seed --class=ProductionSeeder --force');

            return;
        }

        // `migrate --seed` on a database that already has the sample barangay
        // would stop on duplicate household numbers. Refresh the reference data
        // (it is upserted) and leave the existing records alone.
        if (Household::query()->exists()) {
            $this->call([RbacSeeder::class, LookupSeeder::class]);
            $this->command->warn('Sample data is already loaded; only roles and lookups were refreshed. To start over: php artisan migrate:fresh --seed');

            return;
        }

        $this->call([
            RbacSeeder::class,
            LookupSeeder::class,
            UserSeeder::class,
            ResidentSeeder::class,
            ResidentSafetySeeder::class,
            ChildHealthSeeder::class,
            SurveillanceSeeder::class,
            InventorySeeder::class,
            ReferralSeeder::class,
            SchedulingSeeder::class,
        ]);
    }
}
