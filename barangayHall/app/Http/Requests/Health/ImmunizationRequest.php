<?php

namespace App\Http\Requests\Health;

use App\Models\Resident;
use App\Models\Vaccine;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImmunizationRequest extends FormRequest
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
            'vaccine_id' => ['required', 'integer', Rule::exists('vaccines', 'id')],
            'dose_no' => [
                'required', 'integer', 'min:1',
                function (string $attribute, mixed $value, Closure $fail) use ($resident) {
                    $vaccine = Vaccine::query()->find($this->integer('vaccine_id'));

                    if ($vaccine && (int) $value > $vaccine->doses_required) {
                        $fail("{$vaccine->code} has only {$vaccine->doses_required} dose(s).");
                    }

                    if ($resident->immunizations()->where('vaccine_id', $this->integer('vaccine_id'))->where('dose_no', (int) $value)->exists()) {
                        $fail('This dose is already recorded for this resident.');
                    }
                },
            ],
            'date_given' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$resident->birthdate->toDateString()],
            'lot_no' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
