<?php

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Http\Resources\Concerns\FormatsValues;
use App\Models\DiseaseCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Case lists are for roles with diseases.view only; free-text notes
 * additionally need health.view_sensitive.
 *
 * @mixin DiseaseCase
 */
class DiseaseCaseResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DiseaseCase $case */
        $case = $this->resource;
        $canViewSensitive = $request->user()?->hasPermission(PermissionName::HealthViewSensitive) ?? false;

        return [
            'id' => $case->id,
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $case->resident->id,
                'resident_code' => $case->resident->resident_code,
                'full_name' => $case->resident->full_name,
                'age' => $case->resident->age,
            ]),
            'disease' => $this->whenLoaded('disease', fn () => [
                'id' => $case->disease->id,
                'name' => $case->disease->name,
                'pidsr_category' => $this->enum($case->disease->pidsr_category),
            ]),
            'purok' => $this->whenLoaded('purok', fn () => ['id' => $case->purok->id, 'name' => $case->purok->name]),
            'onset_date' => $this->date($case->onset_date),
            'reported_date' => $this->date($case->reported_date),
            'classification' => $this->enum($case->classification),
            'outcome' => $this->enum($case->outcome),
            'outcome_date' => $this->date($case->outcome_date),
            'restricted' => ! $canViewSensitive,
            'notes' => $this->when($canViewSensitive, $case->notes),
            'reported_by' => $this->whenLoaded('reportedBy', fn () => $case->reportedBy->name),
            'classified_by' => $this->whenLoaded('classifiedBy', fn () => $case->classifiedBy?->name),
        ];
    }
}
