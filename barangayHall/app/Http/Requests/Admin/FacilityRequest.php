<?php

namespace App\Http\Requests\Admin;

use App\Enums\FacilityType;
use App\Enums\PermissionName;
use App\Models\Facility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin: referral destinations.
 */
class FacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::LookupsManage) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $facility = $this->route('facility');

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('facilities')->ignore($facility instanceof Facility ? $facility->id : null)],
            'type' => ['required', Rule::enum(FacilityType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:40'],
            'is_active' => ['boolean'],
        ];
    }
}
