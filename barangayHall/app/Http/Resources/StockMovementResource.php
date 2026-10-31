<?php

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Http\Resources\Concerns\FormatsValues;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ledger row. The resident is named only for roles with health.view_sensitive
 * (what a person was given is health information).
 *
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StockMovement $movement */
        $movement = $this->resource;
        $canViewSensitive = $request->user()?->hasPermission(PermissionName::HealthViewSensitive) ?? false;

        return [
            'id' => $movement->id,
            'type' => $this->enum($movement->type),
            'quantity' => $movement->quantity,
            'batch_no' => $this->whenLoaded('batch', fn () => $movement->batch->batch_no),
            'resident' => $this->when($canViewSensitive && $movement->resident_id !== null, fn () => $movement->resident ? [
                'id' => $movement->resident->id,
                'full_name' => $movement->resident->full_name,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $movement->user->name),
            'reason' => $movement->reason,
            'created_at' => $this->dateTime($movement->created_at),
        ];
    }
}
