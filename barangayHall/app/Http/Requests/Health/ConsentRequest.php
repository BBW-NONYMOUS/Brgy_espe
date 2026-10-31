<?php

namespace App\Http\Requests\Health;

use App\Enums\ConsentType;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resident = $this->route('resident');

        return $resident instanceof Resident && ($this->user()?->can('recordConsent', $resident) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'consent_type' => ['sometimes', Rule::enum(ConsentType::class)],
            'consent_date' => ['required', 'date', 'before_or_equal:today'],
            'purpose' => ['required', 'string', 'max:255'],
        ];
    }
}
