<?php

namespace App\Http\Requests\Health;

use App\Enums\CaseClassification;
use App\Enums\CaseOutcome;
use App\Models\DiseaseCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Midwife/Admin: confirm or rule out a case and record its outcome.
 */
class ClassifyDiseaseCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('case');

        return $case instanceof DiseaseCase && ($this->user()?->can('update', $case) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var DiseaseCase $case */
        $case = $this->route('case');

        return [
            'classification' => ['required', Rule::enum(CaseClassification::class)],
            'outcome' => ['required', Rule::enum(CaseOutcome::class)],
            'outcome_date' => [
                'nullable', 'date', 'before_or_equal:today', 'after_or_equal:'.$case->onset_date->toDateString(),
                Rule::requiredIf(fn () => $this->input('outcome') !== CaseOutcome::Ongoing->value),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['outcome_date.required' => 'Enter the date of recovery or death.'];
    }
}
