<?php

namespace App\Http\Requests\Admin;

use App\Enums\OutbreakScope;
use App\Enums\PermissionName;
use App\Models\OutbreakThreshold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin: "N or more cases in the same purok (or barangay) within D days".
 */
class OutbreakThresholdRequest extends FormRequest
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
        $threshold = $this->route('threshold');

        return [
            'disease_id' => [
                'required', 'integer', Rule::exists('diseases', 'id'),
                Rule::unique('outbreak_thresholds')->where('scope', $this->input('scope'))->ignore($threshold instanceof OutbreakThreshold ? $threshold->id : null),
            ],
            'scope' => ['required', Rule::enum(OutbreakScope::class)],
            'min_cases' => ['required', 'integer', 'between:1,1000'],
            'days_window' => ['required', 'integer', 'between:1,365'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['disease_id.unique' => 'This disease already has a threshold for that scope. Edit it instead.'];
    }
}
