<?php

namespace Database\Factories;

use App\Enums\CivilStatus;
use App\Enums\RelationshipToHead;
use App\Enums\ResidentStatus;
use App\Enums\Sex;
use App\Models\Household;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /** Common surnames in Isulan (Hiligaynon, Cebuano, Ilocano and Maguindanaon families). */
    public const LAST_NAMES = [
        'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Gallardo', 'Villanueva', 'Bautista', 'Ramos',
        'Mendoza', 'Pascual', 'Tolentino', 'Aquino', 'Castillo', 'Navarro', 'Torres', 'Panganiban',
        'Pescadero', 'Lumabao', 'Abdulrahman', 'Kamid', 'Usman', 'Mamalinta', 'Ignacio', 'Ocampo',
        'Gonzaga', 'Dimaculangan', 'Sumagaysay', 'Palmares', 'Gelito', 'Bañez', 'Montaño', 'Espinosa',
        'Alonzo', 'Catalan', 'Dagohoy', 'Sarmiento', 'Hilario', 'Tan', 'Lim', 'Guiamad',
    ];

    public const MALE_FIRST_NAMES = [
        'Juan', 'Jose', 'Rodrigo', 'Ernesto', 'Ramon', 'Mark Anthony', 'Jun Rey', 'Benjamin', 'Arnel',
        'Pedro', 'Michael Angelo', 'Teodoro', 'Paolo', 'Christian Dale', 'Joshua', 'Ahmad', 'Jamil',
        'Faisal', 'Mohammad', 'Gabriel', 'Kyle', 'Ethan', 'Liam', 'Matteo', 'Rafael', 'Noel', 'Romeo',
    ];

    public const FEMALE_FIRST_NAMES = [
        'Maria', 'Marites', 'Angelica', 'Lourdes', 'Remedios', 'Kristine Mae', 'Normina', 'Sittie Aisha',
        'Lorna', 'Princess Joy', 'Felicidad', 'Rhealyn', 'Rosalie', 'Jennifer', 'Kaye', 'Rohaida', 'Hanna',
        'Carmelita', 'Joy Ann', 'Althea', 'Nenita', 'Maricel', 'Mika Ella', 'Asuncion', 'Ella Mae', 'Zia',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = fake()->randomElement(Sex::cases());
        $birthdate = fake()->dateTimeBetween('-90 years', '-1 month');
        $isAdult = $birthdate < now()->subYears(18);

        return [
            'resident_code' => fake()->unique()->numerify('KII-'.now()->year.'-#####'),
            'household_id' => Household::factory(),
            'last_name' => fake()->randomElement(self::LAST_NAMES),
            'first_name' => fake()->randomElement($sex === Sex::Male ? self::MALE_FIRST_NAMES : self::FEMALE_FIRST_NAMES),
            'middle_name' => fake()->randomElement(self::LAST_NAMES),
            'suffix' => null,
            'sex' => $sex,
            'birthdate' => $birthdate,
            'birthplace' => 'Isulan, Sultan Kudarat',
            'civil_status' => $isAdult ? fake()->randomElement(CivilStatus::cases()) : CivilStatus::Single,
            'contact_no' => $isAdult ? fake()->numerify('09#########') : null,
            'philhealth_no' => $isAdult && fake()->boolean(60) ? fake()->numerify('##-#########-#') : null,
            'is_pwd' => fake()->boolean(4),
            'relationship_to_head' => RelationshipToHead::Head,
            'status' => ResidentStatus::Active,
        ];
    }

    public function male(): static
    {
        return $this->state(fn () => [
            'sex' => Sex::Male,
            'first_name' => fake()->randomElement(self::MALE_FIRST_NAMES),
        ]);
    }

    public function female(): static
    {
        return $this->state(fn () => [
            'sex' => Sex::Female,
            'first_name' => fake()->randomElement(self::FEMALE_FIRST_NAMES),
        ]);
    }

    public function senior(): static
    {
        return $this->state(fn () => ['birthdate' => fake()->dateTimeBetween('-90 years', '-61 years')]);
    }

    public function infant(): static
    {
        return $this->state(fn () => [
            'birthdate' => fake()->dateTimeBetween('-11 months', '-1 week'),
            'civil_status' => CivilStatus::Single,
            'relationship_to_head' => RelationshipToHead::Child,
            'contact_no' => null,
            'philhealth_no' => null,
        ]);
    }
}
