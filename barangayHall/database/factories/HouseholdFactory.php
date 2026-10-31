<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\Purok;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_no' => fake()->unique()->numerify('HH-#####'),
            'purok_id' => fn () => Purok::query()->inRandomOrder()->value('id')
                ?? Purok::query()->create(['name' => 'Purok 1', 'sort_order' => 1])->id,
            'address' => 'Brgy. Kalawag II, Isulan, Sultan Kudarat',
            'is_4ps' => fake()->boolean(15),
        ];
    }
}
