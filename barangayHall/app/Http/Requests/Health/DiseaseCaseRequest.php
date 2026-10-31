<?php

namespace App\Http\Requests\Health;

use App\Enums\CaseClassification;
use App\Enums\PermissionName;
use App\Models\DiseaseCase;
use App\Models\Resident;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Report a disease case. BHWs can only report "suspected"; a Midwife/Admin
 * may record it directly as probable or confirmed.
 */
class DiseaseCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DiseaseCase::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('classification')) || ! ($this->user()?->hasPermission(PermissionName::DiseasesClassify) ?? false)) {
            $this->merge(['classification' => CaseClassification::Suspected->value]);
        }

        if (blank($this->input('reported_date'))) {
            $this->merge(['reported_date' => today()->toDateString()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'disease_id' => ['required', 'integer', Rule::exists('diseases', 'id')->where('is_active', true)],
            'onset_date' => ['required', 'date', 'before_or_equal:today'],
            'reported_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:onset_date'],
            'classification' => ['required', Rule::enum(CaseClassification::class)->except([CaseClassification::RuledOut])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'health_visit_id' => [
                'nullable', 'integer',
                Rule::exists('health_visits', 'id')->where('resident_id', (int) $this->input('resident_id'))->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $resident = Resident::query()->whereKey($this->input('resident_id'))->first();

                if ($resident !== null && $this->filled('onset_date') && strtotime((string) $this->input('onset_date'))
                    && $resident->birthdate->toDateString() > (string) $this->input('onset_date')) {
                    $validator->errors()->add('onset_date', 'The onset date cannot be before the resident\'s birthdate.');
                }
            },
        ];
    }

    public function resident(): Resident
    {
        return Resident::query()->whereKey($this->validated('resident_id'))->firstOrFail();
    }
}
