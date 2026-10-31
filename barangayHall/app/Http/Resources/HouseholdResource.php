<?php

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Household
 */
class HouseholdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Household $household */
        $household = $this->resource;
        // Coordinates are personal data (RA 10173): exact points only for health staff.
        $canSeeLocation = $request->user()?->can(PermissionName::MapViewExact->value) ?? false;

        return [
            'id' => $household->id,
            'household_no' => $household->household_no,
            'address' => $household->address,
            'is_4ps' => $household->is_4ps,
            'water_source_id' => $household->water_source_id,
            'toilet_type_id' => $household->toilet_type_id,
            'water_source' => $this->whenLoaded('waterSource', fn () => $household->waterSource?->name),
            'toilet_type' => $this->whenLoaded('toiletType', fn () => $household->toiletType?->name),
            'is_geotagged' => $household->isGeotagged(),
            'latitude' => $this->when($canSeeLocation && $household->isGeotagged(), fn () => (float) $household->latitude),
            'longitude' => $this->when($canSeeLocation && $household->isGeotagged(), fn () => (float) $household->longitude),
            'purok' => $this->whenLoaded('purok', fn () => ['id' => $household->purok->id, 'name' => $household->purok->name]),
            'head' => $this->whenLoaded('head', fn () => $household->head ? [
                'id' => $household->head->id,
                'resident_code' => $household->head->resident_code,
                'full_name' => $household->head->full_name,
            ] : null),
            'members_count' => $this->whenCounted('members'),
            'members' => ResidentResource::collection($this->whenLoaded('members')),
        ];
    }
}
