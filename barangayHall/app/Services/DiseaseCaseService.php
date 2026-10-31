<?php

namespace App\Services;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Jobs\DetectOutbreaks;
use App\Models\DiseaseCase;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DiseaseCaseService
{
    /**
     * Record a case. The purok is captured from the resident's household now
     * (a historical fact), then the outbreak check is queued.
     *
     * @param  array<string, mixed>  $data  validated DiseaseCaseRequest data (disease_id, onset_date, reported_date?, classification?, notes?, health_visit_id?)
     */
    public function record(Resident $resident, array $data, User $reporter): DiseaseCase
    {
        $resident->loadMissing('household');

        $case = DB::transaction(fn () => $resident->diseaseCases()->create([
            'disease_id' => $data['disease_id'],
            'purok_id' => $resident->household->purok_id,
            'onset_date' => $data['onset_date'],
            'reported_date' => $data['reported_date'] ?? today()->toDateString(),
            'classification' => $data['classification'] ?? CaseClassification::Suspected->value,
            'outcome' => CaseOutcome::Ongoing,
            'notes' => $data['notes'] ?? null,
            'reported_by' => $reporter->id,
            'classified_by' => ($data['classification'] ?? 'suspected') !== CaseClassification::Suspected->value ? $reporter->id : null,
            'health_visit_id' => $data['health_visit_id'] ?? null,
        ]));

        DetectOutbreaks::dispatch($case->disease_id, $case->purok_id)->afterCommit();

        return $case;
    }

    /**
     * Confirm / rule out, record recovery or death. Re-checks thresholds when
     * the classification changes (a confirmed case can tip a purok over).
     *
     * @param  array<string, mixed>  $data  validated ClassifyDiseaseCaseRequest data
     */
    public function classify(DiseaseCase $case, array $data, User $user): DiseaseCase
    {
        $outcome = CaseOutcome::from((string) $data['outcome']);

        $case->update([
            'classification' => $data['classification'],
            'outcome' => $outcome,
            'outcome_date' => $outcome === CaseOutcome::Ongoing ? null : ($data['outcome_date'] ?? today()->toDateString()),
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $case->notes,
            'classified_by' => $user->id,
        ]);

        if ($case->wasChanged('classification')) {
            DetectOutbreaks::dispatch($case->disease_id, $case->purok_id)->afterCommit();
        }

        return $case;
    }
}
