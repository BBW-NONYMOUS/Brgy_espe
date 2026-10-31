<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use App\Enums\PidsrCategory;
use App\Models\Disease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin: the notifiable disease list (DOH PIDSR categories).
 */
class DiseaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::LookupsManage) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_notifiable' => $this->has('is_notifiable') ? $this->boolean('is_notifiable') : true,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $disease = $this->route('disease');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('diseases')->ignore($disease instanceof Disease ? $disease->id : null)],
            'icd10_code' => ['nullable', 'string', 'max:10'],
            'pidsr_category' => ['required', Rule::enum(PidsrCategory::class)],
            'is_notifiable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
