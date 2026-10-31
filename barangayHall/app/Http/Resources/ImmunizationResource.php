<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Immunization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Immunization
 */
class ImmunizationResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Immunization $immunization */
        $immunization = $this->resource;

        return [
            'id' => $immunization->id,
            'vaccine' => $this->whenLoaded('vaccine', fn () => [
                'id' => $immunization->vaccine->id,
                'code' => $immunization->vaccine->code,
                'name' => $immunization->vaccine->name,
            ]),
            'dose_no' => $immunization->dose_no,
            'date_given' => $this->date($immunization->date_given),
            'given_by' => $this->whenLoaded('givenBy', fn () => $immunization->givenBy->name),
            'lot_no' => $immunization->lot_no,
            'remarks' => $immunization->remarks,
        ];
    }
}
