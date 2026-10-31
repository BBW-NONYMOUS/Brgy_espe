<?php

namespace Database\Seeders;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Enums\OutbreakAlertStatus;
use App\Models\Disease;
use App\Models\OutbreakAlert;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\FindsSeededResidents;
use Illuminate\Database\Seeder;

/**
 * Disease cases with one live outbreak and one closed one:
 *  - Dengue, Purok 3: 3 cases in the last 14 days (threshold 3 / 14 days) → active alert.
 *  - Acute bloody diarrhea, Purok 2: 3 cases within 7 days two months ago → closed alert.
 *  - A measles suspect that was ruled out, so it does not trigger the 1-case rule.
 */
class SurveillanceSeeder extends Seeder
{
    use FindsSeededResidents;

    /**
     * [last|first, disease, onset days ago, classification, outcome, outcome days ago|null, notes]
     *
     * @var list<array{0: string, 1: string, 2: int, 3: CaseClassification, 4: CaseOutcome, 5: int|null, 6: string}>
     */
    private const CASES = [
        ['Santos|Jun Rey', 'Dengue', 9, CaseClassification::Confirmed, CaseOutcome::Recovered, 2, 'NS1 antigen positive at RHU. Platelets recovered.'],
        ['Lumabao|Gabriel', 'Dengue', 6, CaseClassification::Probable, CaseOutcome::Ongoing, null, 'Fever 4 days, abdominal pain. Referred to provincial hospital.'],
        ['Lumabao|Rosalie', 'Dengue', 3, CaseClassification::Suspected, CaseOutcome::Ongoing, null, 'Fever, retro-orbital pain, rash. CBC requested.'],
        ['Dela Cruz|Angelica', 'Dengue', 21, CaseClassification::Suspected, CaseOutcome::Recovered, 14, 'Seen at the health center; recovered at home.'],
        ['Abdulrahman|Ahmad', 'Acute bloody diarrhea', 62, CaseClassification::Probable, CaseOutcome::Recovered, 55, 'Household uses an unprotected well.'],
        ['Abdulrahman|Sittie Aisha', 'Acute bloody diarrhea', 61, CaseClassification::Probable, CaseOutcome::Recovered, 54, 'Same household as index case.'],
        ['Abdulrahman|Jamil', 'Acute bloody diarrhea', 59, CaseClassification::Confirmed, CaseOutcome::Recovered, 50, 'Stool exam positive for Shigella.'],
        ['Panganiban|Mika Ella', 'Influenza-like illness', 10, CaseClassification::Suspected, CaseOutcome::Recovered, 4, 'Cough, colds and low-grade fever.'],
        ['Villanueva|Kaye', 'Measles', 40, CaseClassification::RuledOut, CaseOutcome::Recovered, 33, 'Rash was a drug reaction; measles IgM negative.'],
        ['Kamid|Rohaida', 'Pulmonary tuberculosis', 640, CaseClassification::Confirmed, CaseOutcome::Recovered, 450, 'Completed 6-month DOTS regimen.'],
    ];

    public function run(): void
    {
        $bhw = $this->user('bhw.analiza');
        $midwife = $this->user('mw.cristina');
        $admin = $this->user('admin');
        $today = CarbonImmutable::today();
        $diseases = Disease::query()->pluck('id', 'name');

        foreach (self::CASES as [$key, $disease, $onsetDaysAgo, $classification, $outcome, $outcomeDaysAgo, $notes]) {
            $resident = $this->resident(...explode('|', $key));
            $onset = $today->subDays($onsetDaysAgo);

            $resident->diseaseCases()->create([
                'disease_id' => $diseases[$disease],
                'purok_id' => $resident->household->purok_id,
                'onset_date' => $onset,
                'reported_date' => $onset->addDays(min(2, $onsetDaysAgo)),
                'classification' => $classification,
                'outcome' => $outcome,
                'outcome_date' => $outcomeDaysAgo !== null ? $today->subDays($outcomeDaysAgo) : null,
                'notes' => $notes,
                'reported_by' => $bhw->id,
                'classified_by' => $classification === CaseClassification::Suspected ? null : $midwife->id,
                'health_visit_id' => $this->matchingVisit($resident, $onset),
            ]);
        }

        // What OutbreakDetectionService would have raised for the cases above.
        $purok3 = $this->resident('Lumabao', 'Rosalie')->household->purok_id;
        OutbreakAlert::query()->create([
            'disease_id' => $diseases['Dengue'],
            'purok_id' => $purok3,
            'threshold_min_cases' => 3,
            'threshold_days_window' => 14,
            'case_count' => 3,
            'period_start' => $today->subDays(9),
            'period_end' => $today->subDays(3),
            'status' => OutbreakAlertStatus::Active,
            'created_at' => $today->subDays(3)->setTime(6, 0),
            'updated_at' => $today->subDays(3)->setTime(6, 0),
        ]);

        $purok2 = $this->resident('Abdulrahman', 'Jamil')->household->purok_id;
        OutbreakAlert::query()->create([
            'disease_id' => $diseases['Acute bloody diarrhea'],
            'purok_id' => $purok2,
            'threshold_min_cases' => 3,
            'threshold_days_window' => 7,
            'case_count' => 3,
            'period_start' => $today->subDays(62),
            'period_end' => $today->subDays(59),
            'status' => OutbreakAlertStatus::Closed,
            'acknowledged_by' => $midwife->id,
            'acknowledged_at' => $today->subDays(59)->setTime(8, 15),
            'closed_by' => $admin->id,
            'closed_at' => $today->subDays(35)->setTime(10, 0),
            'closing_note' => 'Well chlorinated and household given water-safety teaching; no new cases for 3 weeks.',
            'created_at' => $today->subDays(59)->setTime(6, 0),
            'updated_at' => $today->subDays(35)->setTime(10, 0),
        ]);
    }

    /**
     * Link a case to a consultation on or right after its onset, if one exists.
     */
    private function matchingVisit(Resident $resident, CarbonImmutable $onset): ?int
    {
        return $resident->healthVisits()
            ->whereBetween('visit_date', [$onset->toDateString(), $onset->addDays(3)->toDateString()])
            ->value('id');
    }
}
