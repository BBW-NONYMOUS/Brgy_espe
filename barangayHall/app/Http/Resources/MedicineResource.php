<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * usable_on_hand / nearest_expiry come from withSum / withMin on the query.
 *
 * @mixin Medicine
 */
class MedicineResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Medicine $medicine */
        $medicine = $this->resource;

        return [
            'id' => $medicine->id,
            'generic_name' => $medicine->generic_name,
            'brand_name' => $medicine->brand_name,
            'display_name' => $medicine->display_name,
            'form' => $this->enum($medicine->form),
            'strength' => $medicine->strength,
            'unit' => $medicine->unit,
            'reorder_level' => $medicine->reorder_level,
            'is_active' => $medicine->is_active,
            'usable_on_hand' => $this->when(array_key_exists('usable_on_hand', $medicine->getAttributes()), fn () => (int) $medicine->getAttribute('usable_on_hand')),
            'expired_on_hand' => $this->when(array_key_exists('expired_on_hand', $medicine->getAttributes()), fn () => (int) $medicine->getAttribute('expired_on_hand')),
            'nearest_expiry' => $this->when(array_key_exists('nearest_expiry', $medicine->getAttributes()), fn () => $medicine->getAttribute('nearest_expiry') !== null ? substr((string) $medicine->getAttribute('nearest_expiry'), 0, 10) : null),
            'batches' => MedicineBatchResource::collection($this->whenLoaded('batches')),
        ];
    }
}
