<?php

namespace App\Http\Requests\Residents;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;

class StoreResidentRequest extends FormRequest
{
    use ResidentRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Resident::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeResidentInput();
        $this->merge(['confirm_duplicate' => $this->boolean('confirm_duplicate')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->residentRules(),
            // RA 10173: consent is required before personal data is recorded.
            'consent_given' => ['accepted'],
            'consent_date' => ['required', 'date', 'before_or_equal:today'],
            'consent_purpose' => ['required', 'string', 'max:255'],
            'confirm_duplicate' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->residentMessages(),
            'consent_given.accepted' => 'The resident (or parent/guardian) must give consent before the profile can be saved.',
        ];
    }
}
