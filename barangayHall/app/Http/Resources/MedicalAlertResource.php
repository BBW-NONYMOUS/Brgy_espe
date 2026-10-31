<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\MedicalAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only ever loaded for roles with health.view_sensitive.
 *
 * @mixin MedicalAlert
 */
class MedicalAlertResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MedicalAlert $alert */
        $alert = $this->resource;

        return [
            'id' => $alert->id,
            'alert_type' => $this->enum($alert->alert_type),
            'description' => $alert->description,
            'severity' => $this->enum($alert->severity),
            'is_active' => $alert->is_active,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $alert->recordedBy->name),
            'created_at' => $this->dateTime($alert->created_at),
        ];
    }
}
