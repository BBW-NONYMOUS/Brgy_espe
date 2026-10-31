<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Consent
 */
class ConsentResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Consent $consent */
        $consent = $this->resource;

        return [
            'id' => $consent->id,
            'consent_type' => $this->enum($consent->consent_type),
            'consent_date' => $this->date($consent->consent_date),
            'purpose' => $consent->purpose,
            'obtained_by' => $this->whenLoaded('obtainedBy', fn () => $consent->obtainedBy->name),
            'revoked_at' => $this->date($consent->revoked_at),
        ];
    }
}
