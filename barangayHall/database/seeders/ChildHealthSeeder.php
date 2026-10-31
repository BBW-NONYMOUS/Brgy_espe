<?php

namespace Database\Seeders;

use App\Enums\MicronutrientType;
use App\Enums\NutritionalStatus;
use App\Enums\Sex;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\FindsSeededResidents;
use Illuminate\Database\Seeder;

/**
 * Growth monitoring and micronutrient supplementation for every child under
 * five. Weights and lengths follow the WHO Child Growth Standards medians;
 * Jamil is seeded underweight and Mika Ella has missed her vitamin A dose so
 * the child health screens show both cases.
 */
class ChildHealthSeeder extends Seeder
{
    use FindsSeededResidents;

    /**
     * WHO medians by age in months: [weight kg boys, girls, length/height cm boys, girls].
     *
     * @var array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private const WHO_MEDIANS = [
        0 => [3.3, 3.2, 49.9, 49.1],
        6 => [7.9, 7.3, 67.6, 65.7],
        12 => [9.6, 8.9, 75.7, 74.0],
        24 => [12.2, 11.5, 87.1, 85.7],
        36 => [14.3, 13.9, 96.1, 95.1],
        48 => [16.3, 16.1, 103.3, 102.7],
        60 => [18.3, 18.2, 110.0, 109.4],
    ];

    /** @var array<string, array{weight: float, height: float, status: NutritionalStatus}> */
    private const UNDERNOURISHED = [
        'Abdulrahman|Jamil' => ['weight' => 0.80, 'height' => 0.97, 'status' => NutritionalStatus::Underweight],
    ];

    /** Children whose latest vitamin A dose was missed (overdue on the checklist). */
    private const MISSED_VITAMIN_A = ['Panganiban|Mika Ella'];

    public function run(): void
    {
        $bhw = $this->user('bhw.rosario');
        $midwife = $this->user('mw.cristina');
        $today = CarbonImmutable::today();

        $children = Resident::query()
            ->where('birthdate', '>', $today->subYears(5)->toDateString())
            ->orderBy('birthdate')
            ->get();

        foreach ($children as $child) {
            $key = "{$child->last_name}|{$child->first_name}";
            $profile = self::UNDERNOURISHED[$key] ?? null;
            $ageMonths = (int) $child->birthdate->diffInMonths($today);

            // Monthly in the first year, then quarterly (Operation Timbang + check-ups).
            $months = collect(range(0, $ageMonths))
                ->filter(fn (int $m) => $m < 12 || $m % 3 === 0)
                ->filter(fn (int $m) => $child->birthdate->addMonths($m)->greaterThan($today->subYear()))
                ->values();

            foreach ($months as $index => $month) {
                $latest = $index === $months->count() - 1;
                [$weight, $height] = $this->median($child->sex, $month);
                $weightFactor = $profile !== null && $month >= 6 ? $profile['weight'] : 1 + fake()->randomFloat(3, -0.04, 0.04);
                $heightFactor = $profile !== null && $month >= 6 ? $profile['height'] : 1 + fake()->randomFloat(3, -0.015, 0.015);

                $child->growthMeasurements()->create([
                    'measured_on' => $child->birthdate->addMonths($month),
                    'weight_kg' => round($weight * $weightFactor, 2),
                    'height_cm' => round($height * $heightFactor, 1),
                    'muac_cm' => $month >= 6 ? round(13.5 + $month * 0.03 - ($profile !== null ? 1.2 : 0), 1) : null,
                    'nutritional_status' => $profile !== null && $month >= 6 ? $profile['status'] : NutritionalStatus::Normal,
                    'measured_by' => $month === 0 ? $midwife->id : $bhw->id,
                    'remarks' => $profile !== null && $latest ? 'Enrolled in the supplementary feeding program.' : null,
                ]);
            }

            $this->seedMicronutrients($child, $ageMonths, in_array($key, self::MISSED_VITAMIN_A, true), $bhw->id);
        }

        // Iron with folic acid for pregnant women at their latest prenatal contact.
        foreach ([['Gallardo', 'Kristine Mae'], ['Ocampo', 'Rhealyn'], ['Panganiban', 'Maricel']] as [$last, $first]) {
            $this->resident($last, $first)->micronutrientRecords()->create([
                'type' => MicronutrientType::Iron,
                'dose' => 'Ferrous sulfate 60 mg + folic acid 400 mcg, 1 tab daily x 30',
                'date_given' => $today->subDays(fake()->numberBetween(0, 14)),
                'given_by' => $midwife->id,
            ]);
        }
    }

    /**
     * Vitamin A from 6 months (100,000 IU, then 200,000 IU every 6 months from
     * 12 months) and deworming every 6 months from 12 months.
     */
    private function seedMicronutrients(Resident $child, int $ageMonths, bool $missedLatestVitaminA, int $givenBy): void
    {
        for ($month = 6; $month <= $ageMonths; $month += 6) {
            $skip = $missedLatestVitaminA && $month + 6 > $ageMonths;

            if (! $skip) {
                $child->micronutrientRecords()->create([
                    'type' => MicronutrientType::VitaminA,
                    'dose' => $month < 12 ? '100,000 IU' : '200,000 IU',
                    'date_given' => $child->birthdate->addMonths($month)->addDays(fake()->numberBetween(0, 10))->min(CarbonImmutable::today()),
                    'given_by' => $givenBy,
                ]);
            }

            if ($month >= 12) {
                $child->micronutrientRecords()->create([
                    'type' => MicronutrientType::Deworming,
                    'dose' => $month < 24 ? 'Albendazole 200 mg' : 'Albendazole 400 mg',
                    'date_given' => $child->birthdate->addMonths($month)->addDays(fake()->numberBetween(0, 10))->min(CarbonImmutable::today()),
                    'given_by' => $givenBy,
                ]);
            }
        }
    }

    /**
     * Linear interpolation between the WHO medians.
     *
     * @return array{0: float, 1: float}
     */
    private function median(Sex $sex, int $month): array
    {
        $girl = $sex === Sex::Female ? 1 : 0;
        $points = array_keys(self::WHO_MEDIANS);
        $month = min($month, 60);

        foreach ($points as $i => $lower) {
            $upper = $points[$i + 1] ?? $lower;

            if ($month >= $lower && $month <= $upper) {
                $span = $upper - $lower;
                $t = $span > 0 ? ($month - $lower) / $span : 0.0;
                $a = self::WHO_MEDIANS[$lower];
                $b = self::WHO_MEDIANS[$upper];

                return [
                    $a[$girl] + ($b[$girl] - $a[$girl]) * $t,
                    $a[2 + $girl] + ($b[2 + $girl] - $a[2 + $girl]) * $t,
                ];
            }
        }

        return [self::WHO_MEDIANS[60][$girl], self::WHO_MEDIANS[60][2 + $girl]];
    }
}
