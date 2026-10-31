<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\ResidentCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResidentCondition
 */
class ResidentConditionResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ResidentCondition $condition */
        $condition = $this->resource;
        $canViewSensitive = $request->user()?->can('view', $condition) ?? false;

        return [
            'id' => $condition->id,
            'condition' => $this->whenLoaded('condition', fn () => [
                'id' => $condition->condition->id,
                'name' => $condition->condition->name,
                'icd10_code' => $condition->condition->icd10_code,
            ]),
            'diagnosed_date' => $this->date($condition->diagnosed_date),
            'status' => $this->enum($condition->status),
            'restricted' => ! $canViewSensitive,
            'notes' => $this->when($canViewSensitive, $condition->notes),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $condition->recordedBy->name),
        ];
    }
}
