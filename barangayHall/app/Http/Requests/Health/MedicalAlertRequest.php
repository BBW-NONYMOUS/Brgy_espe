<?php

namespace App\Http\Requests\Health;

use App\Enums\AlertSeverity;
use App\Enums\MedicalAlertType;
use App\Models\MedicalAlert;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Allergies, chronic conditions, disabilities. Critical alerts become the
 * red banner on the profile and every health form.
 */
class MedicalAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordHealth', $this->resident()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->has('is_active') ? $this->boolean('is_active') : true]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alert_type' => ['required', Rule::enum(MedicalAlertType::class)],
            'description' => ['required', 'string', 'max:500'],
            'severity' => ['required', Rule::enum(AlertSeverity::class)],
            'is_active' => ['boolean'],
        ];
    }

    public function resident(): Resident
    {
        $alert = $this->route('alert');

        $resident = $alert instanceof MedicalAlert ? $alert->resident : $this->route('resident');
        abort_unless($resident instanceof Resident, 404);

        return $resident;
    }
}
