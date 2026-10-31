<?php

namespace App\Http\Requests\Health;

use App\Enums\NutritionalStatus;
use App\Models\Resident;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One weighing (Operation Timbang or check-up). The nutritional status is
 * read by the BHW from the OPT Plus chart.
 */
class GrowthMeasurementRequest extends FormRequest
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
            'measured_on' => [
                'required', 'date', 'before_or_equal:today', 'after_or_equal:'.$resident->birthdate->toDateString(),
                // whereDate: compares the date part whatever the driver stores (DATE or "Y-m-d 00:00:00").
                function (string $attribute, mixed $value, Closure $fail) use ($resident) {
                    if (is_string($value) && $resident->growthMeasurements()->whereDate('measured_on', $value)->exists()) {
                        $fail('This child was already measured on that date.');
                    }
                },
            ],
            'weight_kg' => ['required', 'numeric', 'between:0.5,150'],
            'height_cm' => ['nullable', 'numeric', 'between:30,200'],
            'muac_cm' => ['nullable', 'numeric', 'between:5,40'],
            'nutritional_status' => ['nullable', Rule::enum(NutritionalStatus::class)],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'measured_on.after_or_equal' => 'The date cannot be before the child\'s birthdate.',
        ];
    }
}
