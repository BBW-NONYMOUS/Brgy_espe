<?php

namespace Database\Factories;

use App\Models\HealthVisit;
use App\Models\Resident;
use App\Models\User;
use App\Models\VisitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthVisit>
 */
class HealthVisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'visit_type_id' => fn () => VisitType::query()->firstOrCreate(['name' => 'General Consultation'])->id,
            'visit_date' => fake()->dateTimeBetween('-1 year'),
            'attended_by' => User::factory(),
            'chief_complaint' => fake()->randomElement(['Fever for 2 days', 'Cough and colds', 'Headache', 'BP monitoring']),
            'diagnosis' => fake()->randomElement(['Acute upper respiratory infection', 'Essential hypertension', null]),
            'notes' => fake()->boolean(40) ? fake()->sentence() : null,
        ];
    }
}
