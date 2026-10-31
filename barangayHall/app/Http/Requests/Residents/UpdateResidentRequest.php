<?php

namespace App\Http\Requests\Residents;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;

class UpdateResidentRequest extends FormRequest
{
    use ResidentRules;

    public function authorize(): bool
    {
        $resident = $this->route('resident');

        return $resident instanceof Resident && ($this->user()?->can('update', $resident) ?? false);
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
            'confirm_duplicate' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->residentMessages();
    }
}
