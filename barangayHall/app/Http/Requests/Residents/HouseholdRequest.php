<?php

namespace App\Http\Requests\Residents;

use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Create and update households. household_no is generated when left blank.
 */
class HouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $household = $this->route('household');

        return $household instanceof Household
            ? ($this->user()?->can('update', $household) ?? false)
            : ($this->user()?->can('create', Household::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'household_no' => filled($this->input('household_no')) ? Str::upper(Str::squish((string) $this->input('household_no'))) : null,
            'address' => Str::squish((string) $this->input('address')),
            'is_4ps' => $this->boolean('is_4ps'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $household = $this->route('household');
        $householdId = $household instanceof Household ? $household->id : null;

        return [
            'household_no' => ['nullable', 'string', 'max:20', 'regex:/^HH-\d{4,6}$/', Rule::unique('households')->ignore($householdId)],
            'purok_id' => ['required', 'integer', Rule::exists('puroks', 'id')],
            'address' => ['required', 'string', 'max:255'],
            'is_4ps' => ['boolean'],
            'water_source_id' => ['nullable', 'integer', Rule::exists('water_sources', 'id')],
            'toilet_type_id' => ['nullable', 'integer', Rule::exists('toilet_types', 'id')],
            'head_resident_id' => [
                'nullable', 'integer',
                // The head must already be a member of this household.
                Rule::exists('residents', 'id')->where('household_id', $householdId ?? 0)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'household_no.regex' => 'Use the format HH-0001.',
            'head_resident_id.exists' => 'The household head must be an active member of this household.',
        ];
    }
}
