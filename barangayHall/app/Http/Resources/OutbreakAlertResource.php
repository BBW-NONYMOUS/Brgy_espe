<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\OutbreakAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Aggregate only (disease, purok, count, period) — safe for Barangay Officials.
 *
 * @mixin OutbreakAlert
 */
class OutbreakAlertResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OutbreakAlert $alert */
        $alert = $this->resource;

        return [
            'id' => $alert->id,
            'disease' => $this->whenLoaded('disease', fn () => ['id' => $alert->disease->id, 'name' => $alert->disease->name]),
            'purok' => $this->whenLoaded('purok', fn () => $alert->purok ? ['id' => $alert->purok->id, 'name' => $alert->purok->name] : null),
            'scope_label' => $alert->purok_id === null ? 'Whole barangay' : null,
            'case_count' => $alert->case_count,
            'threshold' => ['min_cases' => $alert->threshold_min_cases, 'days_window' => $alert->threshold_days_window],
            'period_start' => $this->date($alert->period_start),
            'period_end' => $this->date($alert->period_end),
            'status' => $this->enum($alert->status),
            'acknowledged_by' => $this->whenLoaded('acknowledgedBy', fn () => $alert->acknowledgedBy?->name),
            'acknowledged_at' => $this->dateTime($alert->acknowledged_at),
            'closed_by' => $this->whenLoaded('closedBy', fn () => $alert->closedBy?->name),
            'closed_at' => $this->dateTime($alert->closed_at),
            'closing_note' => $alert->closing_note,
            'created_at' => $this->dateTime($alert->created_at),
        ];
    }
}
