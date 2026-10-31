<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Delivery
 */
class DeliveryResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Delivery $delivery */
        $delivery = $this->resource;

        return [
            'id' => $delivery->id,
            'delivery_time' => $delivery->delivery_time !== null ? substr($delivery->delivery_time, 0, 5) : null,
            'place_type' => $this->enum($delivery->place_type),
            'place_name' => $delivery->place_name,
            'attendant_type' => $this->enum($delivery->attendant_type),
            'delivery_mode' => $this->enum($delivery->delivery_mode),
            'live_births' => $delivery->live_births,
            'stillbirths' => $delivery->stillbirths,
            'facility_based' => $delivery->isFacilityBased(),
            'skilled_attendant' => $delivery->isSkilledAttendant(),
            'remarks' => $delivery->remarks,
        ];
    }
}
