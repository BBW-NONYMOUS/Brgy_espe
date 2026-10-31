<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\MicronutrientRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MicronutrientRecord
 */
class MicronutrientRecordResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MicronutrientRecord $record */
        $record = $this->resource;

        return [
            'id' => $record->id,
            'type' => $this->enum($record->type),
            'dose' => $record->dose,
            'date_given' => $this->date($record->date_given),
            'given_by' => $this->whenLoaded('givenBy', fn () => $record->givenBy->name),
        ];
    }
}
