<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\GrowthMeasurement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GrowthMeasurement
 */
class GrowthMeasurementResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GrowthMeasurement $m */
        $m = $this->resource;

        return [
            'id' => $m->id,
            'measured_on' => $this->date($m->measured_on),
            'weight_kg' => (float) $m->weight_kg,
            'height_cm' => $m->height_cm !== null ? (float) $m->height_cm : null,
            'muac_cm' => $m->muac_cm !== null ? (float) $m->muac_cm : null,
            'nutritional_status' => $this->enum($m->nutritional_status),
            'measured_by' => $this->whenLoaded('measuredBy', fn () => $m->measuredBy->name),
            'remarks' => $m->remarks,
        ];
    }
}
