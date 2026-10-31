<?php

namespace App\Http\Requests\Health;

use App\Enums\MicronutrientType;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MicronutrientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resident = $this->route('resident');

        return $resident instanceof Resident && ($this->user()?->can('recordHealth', $resident) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Resident $resident */
        $resident = $this->route('resident');

        return [
            'type' => ['required', Rule::enum(MicronutrientType::class)],
            'dose' => ['nullable', 'string', 'max:100'],
            'date_given' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$resident->birthdate->toDateString()],
        ];
    }
}
