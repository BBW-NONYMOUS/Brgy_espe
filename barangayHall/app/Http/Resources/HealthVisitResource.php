<?php

namespace App\Http\Resources;

use App\Enums\MaternalVisitKind;
use App\Http\Resources\Concerns\FormatsValues;
use App\Models\HealthVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HealthVisit
 */
class HealthVisitResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HealthVisit $visit */
        $visit = $this->resource;
        $canViewSensitive = $request->user()?->can('view', $visit) ?? false;
        $vitals = $visit->relationLoaded('vitalSign') ? $visit->vitalSign : null;
        $prenatal = $visit->relationLoaded('prenatalVisit') ? $visit->prenatalVisit : null;
        $postnatal = $visit->relationLoaded('postnatalVisit') ? $visit->postnatalVisit : null;

        return [
            'id' => $visit->id,
            'visit_date' => $this->date($visit->visit_date),
            'visit_type' => $this->whenLoaded('visitType', fn () => ['id' => $visit->visitType->id, 'name' => $visit->visitType->name]),
            'attended_by' => $this->whenLoaded('attendedBy', fn () => $visit->attendedBy->name),
            // Sensitive clinical text: omitted entirely for roles without access.
            'restricted' => ! $canViewSensitive,
            'chief_complaint' => $this->when($canViewSensitive, $visit->chief_complaint),
            'diagnosis' => $this->when($canViewSensitive, $visit->diagnosis),
            'notes' => $this->when($canViewSensitive, $visit->notes),
            'vitals' => $vitals ? [
                'bp_systolic' => $vitals->bp_systolic,
                'bp_diastolic' => $vitals->bp_diastolic,
                'weight_kg' => $vitals->weight_kg !== null ? (float) $vitals->weight_kg : null,
                'height_cm' => $vitals->height_cm !== null ? (float) $vitals->height_cm : null,
                'temperature_c' => $vitals->temperature_c !== null ? (float) $vitals->temperature_c : null,
                'pulse_rate' => $vitals->pulse_rate,
                'respiratory_rate' => $vitals->respiratory_rate,
                'bmi' => $vitals->bmi(),
            ] : null,
            'maternal' => match (true) {
                $prenatal !== null => [
                    'pregnancy_id' => $prenatal->pregnancy_id,
                    'kind' => $this->enum(MaternalVisitKind::Prenatal),
                    'fundal_height_cm' => $prenatal->fundal_height_cm !== null ? (float) $prenatal->fundal_height_cm : null,
                    'fetal_heart_rate' => $prenatal->fetal_heart_rate,
                    'remarks' => $canViewSensitive ? $prenatal->remarks : null,
                ],
                $postnatal !== null => [
                    'pregnancy_id' => $postnatal->pregnancy_id,
                    'kind' => $this->enum(MaternalVisitKind::Postnatal),
                    'fundal_height_cm' => null,
                    'fetal_heart_rate' => null,
                    'remarks' => $canViewSensitive ? $postnatal->remarks : null,
                ],
                default => null,
            },
            'created_at' => $this->dateTime($visit->created_at),
        ];
    }
}
