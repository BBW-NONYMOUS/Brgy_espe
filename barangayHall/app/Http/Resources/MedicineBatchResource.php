<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\MedicineBatch;
use App\Services\InventoryAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * expiry_band: 0 expired, 30/60/90 = within that many days, null = later.
 *
 * @mixin MedicineBatch
 */
class MedicineBatchResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MedicineBatch $batch */
        $batch = $this->resource;

        return [
            'id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'expiry_date' => $this->date($batch->expiry_date),
            'days_to_expiry' => (int) CarbonImmutable::today()->diffInDays($batch->expiry_date, false),
            'expiry_band' => InventoryAlertService::bandFor($batch->expiry_date),
            'quantity_received' => $batch->quantity_received,
            'quantity_on_hand' => $batch->quantity_on_hand,
            'received_at' => $this->date($batch->received_at),
            'source' => $batch->source,
        ];
    }
}
