<?php

namespace App\Http\Requests\Inventory;

use App\Enums\MedicineForm;
use App\Models\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicine = $this->route('medicine');

        return $medicine instanceof Medicine
            ? ($this->user()?->can('update', $medicine) ?? false)
            : ($this->user()?->can('create', Medicine::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'generic_name' => Str::squish((string) $this->input('generic_name')),
            'strength' => Str::squish((string) $this->input('strength')),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $medicine = $this->route('medicine');

        return [
            'generic_name' => [
                'required', 'string', 'max:120',
                Rule::unique('medicines')
                    ->where('form', $this->input('form'))
                    ->where('strength', $this->input('strength'))
                    ->ignore($medicine instanceof Medicine ? $medicine->id : null),
            ],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'form' => ['required', Rule::enum(MedicineForm::class)],
            'strength' => ['required', 'string', 'max:40'],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['generic_name.unique' => 'This medicine (same form and strength) is already in the list.'];
    }
}
