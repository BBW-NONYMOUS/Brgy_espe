<?php

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->resource;
        $canViewSensitive = $request->user()?->hasPermission(PermissionName::HealthViewSensitive) ?? false;

        return [
            'id' => $appointment->id,
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $appointment->resident->id,
                'resident_code' => $appointment->resident->resident_code,
                'full_name' => $appointment->resident->full_name,
            ]),
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $appointment->type->id,
                'code' => $appointment->type->code,
                'name' => $appointment->type->name,
                'color' => $appointment->type->color,
            ]),
            'scheduled_at' => $this->dateTime($appointment->scheduled_at),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $appointment->assignedTo ? ['id' => $appointment->assignedTo->id, 'name' => $appointment->assignedTo->name] : null),
            'status' => $this->enum($appointment->status),
            'is_generated' => $appointment->source_type !== null,
            'edited_manually' => $appointment->edited_manually,
            'health_visit_id' => $appointment->health_visit_id,
            'notes' => $this->when($canViewSensitive, $appointment->notes),
        ];
    }
}
