<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin: the point where the purok-level map draws each purok (never a household).
 */
class PurokCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::LookupsManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [[$minLat, $minLng], [$maxLat, $maxLng]] = config('barangay.map.bounds');

        return [
            'center_latitude' => ['nullable', 'required_with:center_longitude', 'numeric', "between:{$minLat},{$maxLat}"],
            'center_longitude' => ['nullable', 'required_with:center_latitude', 'numeric', "between:{$minLng},{$maxLng}"],
        ];
    }
}
