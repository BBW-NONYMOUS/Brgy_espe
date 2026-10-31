<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\HealthProgram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attendee names only for program managers; Officials get counts.
 *
 * @mixin HealthProgram
 */
class HealthProgramResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HealthProgram $program */
        $program = $this->resource;

        return [
            'id' => $program->id,
            'title' => $program->title,
            'type' => $this->whenLoaded('type', fn () => ['id' => $program->type->id, 'name' => $program->type->name, 'color' => $program->type->color]),
            'description' => $program->description,
            'start_at' => $this->dateTime($program->start_at),
            'end_at' => $this->dateTime($program->end_at),
            'venue' => $program->venue,
            'target_group' => $this->enum($program->target_group),
            'status' => $this->enum($program->status),
            'puroks' => $this->whenLoaded('puroks', fn () => $program->puroks->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all()),
            'staff' => $this->whenLoaded('staff', fn () => $program->staff->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all()),
            'attendance_count' => $this->whenCounted('attendance'),
        ];
    }
}
