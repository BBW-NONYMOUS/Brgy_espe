<?php

namespace App\Http\Resources;

use App\Enums\MaternalStatus;
use App\Http\Resources\Concerns\FormatsValues;
use App\Models\Pregnancy;
use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resident
 */
class ResidentResource extends JsonResource
{
    use FormatsValues;

    private bool $withSensitive = false;

    /**
     * Include the full PhilHealth number (profile page only, and only if the
     * viewer's policy allows it). Lists always get the masked value.
     */
    public function withSensitive(): self
    {
        $this->withSensitive = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Resident $resident */
        $resident = $this->resource;
        $canViewSensitive = $this->withSensitive && ($request->user()?->can('viewSensitive', $resident) ?? false);

        return [
            'id' => $resident->id,
            'resident_code' => $resident->resident_code,
            'last_name' => $resident->last_name,
            'first_name' => $resident->first_name,
            'middle_name' => $resident->middle_name,
            'suffix' => $resident->suffix,
            'full_name' => $resident->full_name,
            'sex' => $this->enum($resident->sex),
            'birthdate' => $this->date($resident->birthdate),
            'age' => $resident->age,
            'birthplace' => $resident->birthplace,
            'civil_status' => $this->enum($resident->civil_status),
            'contact_no' => $resident->contact_no,
            'philhealth_no_masked' => $this->maskPhilHealth($resident->philhealth_no),
            'philhealth_no' => $this->when($canViewSensitive, $resident->philhealth_no),
            'is_pwd' => $resident->is_pwd,
            'blood_type' => $this->when($canViewSensitive, fn () => $resident->blood_type?->value),
            // 4Ps is recorded on the household; members inherit it.
            'is_4ps' => $this->whenLoaded('household', fn () => $resident->household->is_4ps),
            'is_senior' => $resident->isSenior(),
            'is_pregnant' => $this->whenLoaded(
                'pregnancies',
                fn () => $resident->pregnancies->contains(fn (Pregnancy $r) => $r->status === MaternalStatus::Ongoing),
            ),
            'relationship_to_head' => $this->enum($resident->relationship_to_head),
            'status' => $this->enum($resident->status),
            'archive_reason' => $resident->archive_reason,
            'archived_at' => $this->dateTime($resident->deleted_at),
            'household' => HouseholdResource::make($this->whenLoaded('household')),
            'created_by' => $this->whenLoaded('creator', fn () => $resident->creator?->name),
            'updated_by' => $this->whenLoaded('updater', fn () => $resident->updater?->name),
            'created_at' => $this->dateTime($resident->created_at),
            'updated_at' => $this->dateTime($resident->updated_at),
        ];
    }
}
