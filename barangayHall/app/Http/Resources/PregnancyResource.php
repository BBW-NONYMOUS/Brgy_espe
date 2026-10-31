<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Pregnancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pregnancy
 */
class PregnancyResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Pregnancy $record */
        $record = $this->resource;
        $canViewSensitive = $request->user()?->can('view', $record) ?? false;

        return [
            'id' => $record->id,
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $record->resident->id,
                'resident_code' => $record->resident->resident_code,
                'full_name' => $record->resident->full_name,
                'age' => $record->resident->age,
                'purok' => $record->resident->relationLoaded('household') ? $record->resident->household->purok->name : null,
            ]),
            'lmp' => $this->date($record->lmp),
            'edd' => $this->date($record->edd),
            'aog_weeks' => $record->gestationalAgeWeeks(),
            'gravida' => $record->gravida,
            'para' => $record->para,
            'risk_level' => $this->enum($record->risk_level),
            'status' => $this->enum($record->status),
            'outcome_date' => $this->date($record->outcome_date),
            'restricted' => ! $canViewSensitive,
            'notes' => $this->when($canViewSensitive, $record->notes),
            'risk_factors' => $this->whenLoaded('riskFactors', fn () => $record->riskFactors->map(fn ($f) => ['id' => $f->id, 'name' => $f->name])->all()),
            'delivery' => $this->whenLoaded('delivery', fn () => $record->delivery ? DeliveryResource::make($record->delivery) : null),
            'visits_count' => $this->whenCounted('prenatalVisits', fn (int $prenatal) => $prenatal + (int) $record->getAttribute('postnatal_visits_count')),
        ];
    }
}
