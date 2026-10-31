<?php

namespace App\Http\Requests\Health;

use App\Models\EmergencyContact;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class EmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordHealth', $this->resident()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'phone' => preg_replace('/[\s\-()]/', '', (string) $this->input('phone')),
            'is_primary' => $this->boolean('is_primary'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'relationship' => ['required', 'string', 'max:40'],
            // PH mobile (09xx / +639xx) or landline with area code.
            'phone' => ['required', 'string', 'regex:/^(\+63|0)\d{9,10}$/'],
            'is_primary' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a Philippine number, e.g. 0917 123 4567.'];
    }

    public function resident(): Resident
    {
        $contact = $this->route('contact');

        $resident = $contact instanceof EmergencyContact ? $contact->resident : $this->route('resident');
        abort_unless($resident instanceof Resident, 404);

        return $resident;
    }
}
