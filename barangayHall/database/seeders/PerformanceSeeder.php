<?php

namespace Database\Seeders;

use App\Enums\CivilStatus;
use App\Enums\RelationshipToHead;
use App\Models\Purok;
use App\Models\User;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-inserts ~10,000 residents in ~2,500 households to verify the
 * "search under 1 second" requirement, plus disease cases, appointments and
 * medicine batches for the alert-query EXPLAIN checks (Phase 2 addendum).
 * Never run this in production. Batches are inserted without ledger rows.
 *
 *   php artisan db:seed --class=PerformanceSeeder
 */
class PerformanceSeeder extends Seeder
{
    private const RESIDENTS = 10_000;

    private const PER_HOUSEHOLD = 4;

    private const DISEASE_CASES = 5_000;

    private const APPOINTMENTS = 20_000;

    public function run(): void
    {
        fake()->seed(10_000);

        $purokIds = Purok::query()->pluck('id')->all();
        $staffId = User::query()->where('username', 'bhw.analiza')->value('id');
        $now = now();
        $year = $now->year;
        $nextCode = (int) DB::table('residents')->count() + 1;
        $householdStart = (int) DB::table('households')->count() + 1;
        $center = config('barangay.map.center');

        $households = [];

        for ($i = 0; $i < self::RESIDENTS / self::PER_HOUSEHOLD; $i++) {
            $purokId = $purokIds[$i % count($purokIds)];
            // ~80% geo-tagged, scattered ~1 km around the map centre (map load test).
            $geotagged = fake()->boolean(80);
            $households[] = [
                'household_no' => sprintf('HH-%05d', $householdStart + $i),
                'purok_id' => $purokId,
                'address' => 'Brgy. Kalawag II, Isulan, Sultan Kudarat',
                'is_4ps' => fake()->boolean(15),
                'latitude' => $geotagged ? round($center[0] + fake()->randomFloat(7, -0.009, 0.009), 7) : null,
                'longitude' => $geotagged ? round($center[1] + fake()->randomFloat(7, -0.009, 0.009), 7) : null,
                'geotagged_at' => $geotagged ? $now : null,
                'geotagged_by' => $geotagged ? $staffId : null,
                'created_by' => $staffId,
                'updated_by' => $staffId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($households, 500) as $chunk) {
            DB::table('households')->insert($chunk);
        }

        $householdIds = DB::table('households')->where('household_no', '>=', sprintf('HH-%05d', $householdStart))->pluck('id')->all();
        $relationships = [RelationshipToHead::Head, RelationshipToHead::Spouse, RelationshipToHead::Child, RelationshipToHead::Child];

        $rows = [];

        for ($i = 0; $i < self::RESIDENTS; $i++) {
            $male = fake()->boolean();
            $birthdate = fake()->dateTimeBetween('-95 years', '-1 week');

            $rows[] = [
                'resident_code' => sprintf('KII-%d-%05d', $year, $nextCode + $i),
                'household_id' => $householdIds[intdiv($i, self::PER_HOUSEHOLD)],
                'last_name' => fake()->randomElement(ResidentFactory::LAST_NAMES),
                'first_name' => fake()->randomElement($male ? ResidentFactory::MALE_FIRST_NAMES : ResidentFactory::FEMALE_FIRST_NAMES),
                'middle_name' => fake()->randomElement(ResidentFactory::LAST_NAMES),
                'sex' => $male ? 'male' : 'female',
                'birthdate' => $birthdate->format('Y-m-d'),
                'civil_status' => fake()->randomElement(CivilStatus::cases())->value,
                'is_pwd' => fake()->boolean(4),
                'relationship_to_head' => $relationships[$i % self::PER_HOUSEHOLD]->value,
                'status' => 'active',
                'created_by' => $staffId,
                'updated_by' => $staffId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('residents')->insert($chunk);
        }

        $this->surveillanceAndScheduling($staffId);
    }

    /**
     * Volume for the alert queries (outbreak window counts, missed-appointment
     * sweep, calendar range): 5,000 disease cases over two years, 20,000
     * appointments over a year and 300 medicine batches.
     */
    private function surveillanceAndScheduling(int $staffId): void
    {
        $now = now();
        $residents = DB::table('residents')
            ->join('households', 'households.id', '=', 'residents.household_id')
            ->select('residents.id', 'households.purok_id')
            ->get();
        $diseaseIds = DB::table('diseases')->pluck('id')->all();
        $typeIds = DB::table('appointment_types')->pluck('id')->all();
        $classifications = ['suspected', 'suspected', 'probable', 'confirmed', 'ruled_out'];

        $cases = [];

        for ($i = 0; $i < self::DISEASE_CASES; $i++) {
            $resident = $residents[fake()->numberBetween(0, $residents->count() - 1)];
            $onset = $now->copy()->subDays(fake()->numberBetween(0, 730))->toDateString();
            $cases[] = [
                'resident_id' => $resident->id,
                'disease_id' => fake()->randomElement($diseaseIds),
                'purok_id' => $resident->purok_id,
                'onset_date' => $onset,
                'reported_date' => $onset,
                'classification' => fake()->randomElement($classifications),
                'outcome' => 'recovered',
                'outcome_date' => $onset,
                'reported_by' => $staffId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($cases, 1000) as $chunk) {
            DB::table('disease_cases')->insert($chunk);
        }

        $appointments = [];

        for ($i = 0; $i < self::APPOINTMENTS; $i++) {
            $at = $now->copy()->addDays(fake()->numberBetween(-300, 60))->setTime(fake()->numberBetween(8, 15), 0);
            $appointments[] = [
                'resident_id' => $residents[fake()->numberBetween(0, $residents->count() - 1)]->id,
                'appointment_type_id' => fake()->randomElement($typeIds),
                'scheduled_at' => $at,
                'assigned_to' => $staffId,
                'status' => $at->isPast() ? fake()->randomElement(['completed', 'completed', 'completed', 'missed', 'cancelled']) : 'scheduled',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($appointments, 1000) as $chunk) {
            DB::table('appointments')->insert($chunk);
        }

        $medicineIds = DB::table('medicines')->pluck('id')->all();
        $batches = [];

        for ($i = 0; $i < 300 && $medicineIds !== []; $i++) {
            $received = fake()->numberBetween(50, 1000);
            $batches[] = [
                'medicine_id' => fake()->randomElement($medicineIds),
                'batch_no' => sprintf('PERF-%05d', $i),
                'expiry_date' => $now->copy()->addDays(fake()->numberBetween(-60, 900))->toDateString(),
                'quantity_received' => $received,
                'quantity_on_hand' => fake()->numberBetween(0, $received),
                'received_at' => $now->copy()->subDays(400)->toDateString(),
                'received_by' => $staffId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('medicine_batches')->insert($batches);
    }
}
