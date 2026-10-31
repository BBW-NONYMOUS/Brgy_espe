<?php

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reason and return-slip feedback are clinical: omitted unless the
 * reader has health.view_sensitive (lists never include them).
 *
 * @mixin Referral
 */
class ReferralResource extends JsonResource
{
    use FormatsValues;

    /** Detail pages include the clinical text; lists never do. */
    private bool $withDetails = false;

    public function withDetails(): static
    {
        $this->withDetails = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Referral $referral */
        $referral = $this->resource;
        $canViewSensitive = $request->user()?->hasPermission(PermissionName::HealthViewSensitive) ?? false;

        return [
            'id' => $referral->id,
            'referral_no' => $referral->referral_no,
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $referral->resident->id,
                'resident_code' => $referral->resident->resident_code,
                'full_name' => $referral->resident->full_name,
                'age' => $referral->resident->age,
                'sex' => $this->enum($referral->resident->sex),
            ]),
            'facility' => $this->whenLoaded('facility', fn () => [
                'id' => $referral->facility->id,
                'name' => $referral->facility->name,
                'type' => $this->enum($referral->facility->type),
            ]),
            'referred_by' => $this->whenLoaded('referredBy', fn () => $referral->referredBy->name),
            'referred_at' => $this->dateTime($referral->referred_at),
            'urgency' => $this->enum($referral->urgency),
            'status' => $this->enum($referral->status),
            'completed_at' => $this->dateTime($referral->completed_at),
            'restricted' => ! $canViewSensitive,
            'reason' => $this->when($canViewSensitive && $this->withDetails, $referral->reason),
            'feedback_note' => $this->when($canViewSensitive && $this->withDetails, $referral->feedback_note),
            'history' => $this->whenLoaded('statusChanges', fn () => $referral->statusChanges->map(fn ($change) => [
                'from' => $this->enum($change->from_status),
                'to' => $this->enum($change->to_status),
                'by' => $change->changedBy->name,
                'note' => $change->note,
                'at' => $this->dateTime($change->created_at),
            ])->all()),
        ];
    }
}
