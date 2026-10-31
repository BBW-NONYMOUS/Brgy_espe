<?php

namespace App\Http\Requests\Health;

use App\Enums\BreastfeedingStatus;
use App\Enums\MaternalVisitKind;
use App\Models\HealthVisit;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A consultation / check-up with optional vital signs and, for pregnant or
 * postpartum residents, prenatal/postnatal findings.
 */
class HealthVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordHealth', $this->resident()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $resident = $this->resident();

        return [
            'visit_type_id' => ['required', 'integer', Rule::exists('visit_types', 'id')],
            'visit_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$resident->birthdate->toDateString()],
            'chief_complaint' => ['nullable', 'string', 'max:500'],
            'diagnosis' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'vitals' => ['nullable', 'array'],
            'vitals.bp_systolic' => ['nullable', 'integer', 'between:50,300', 'required_with:vitals.bp_diastolic'],
            'vitals.bp_diastolic' => ['nullable', 'integer', 'between:30,200', 'required_with:vitals.bp_systolic', 'lt:vitals.bp_systolic'],
            'vitals.weight_kg' => ['nullable', 'numeric', 'between:0.5,500'],
            'vitals.height_cm' => ['nullable', 'numeric', 'between:20,272'],
            'vitals.temperature_c' => ['nullable', 'numeric', 'between:30,45'],
            'vitals.pulse_rate' => ['nullable', 'integer', 'between:20,250'],
            'vitals.respiratory_rate' => ['nullable', 'integer', 'between:5,80'],

            'maternal' => ['nullable', 'array'],
            'maternal.pregnancy_id' => [
                'required_with:maternal.kind', 'nullable', 'integer',
                Rule::exists('pregnancies', 'id')->where('resident_id', $resident->id)->whereNull('deleted_at'),
            ],
            'maternal.kind' => ['required_with:maternal.pregnancy_id', 'nullable', Rule::enum(MaternalVisitKind::class)],
            'maternal.fundal_height_cm' => ['nullable', 'numeric', 'between:0,60'],
            'maternal.fetal_heart_rate' => ['nullable', 'integer', 'between:60,220'],
            'maternal.remarks' => ['nullable', 'string', 'max:255'],
            'maternal.breastfeeding' => ['nullable', Rule::enum(BreastfeedingStatus::class)],
            'maternal.family_planning_counseled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vitals.bp_systolic' => 'systolic BP',
            'vitals.bp_diastolic' => 'diastolic BP',
            'vitals.weight_kg' => 'weight',
            'vitals.height_cm' => 'height',
            'vitals.temperature_c' => 'temperature',
            'vitals.pulse_rate' => 'pulse rate',
            'vitals.respiratory_rate' => 'respiratory rate',
            'maternal.fundal_height_cm' => 'fundal height',
            'maternal.fetal_heart_rate' => 'fetal heart rate',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vitals.bp_diastolic.lt' => 'Diastolic BP must be lower than systolic BP.',
            'visit_date.after_or_equal' => 'The visit date cannot be before the resident\'s birthdate.',
        ];
    }

    /**
     * Vital signs that were actually filled in.
     *
     * @return array<string, mixed>
     */
    public function vitals(): array
    {
        $vitals = $this->validated('vitals');

        return is_array($vitals) ? array_filter($vitals, fn ($value) => $value !== null) : [];
    }

    private function resident(): Resident
    {
        $resident = $this->route('resident');
        $visit = $this->route('visit');

        if (! $resident instanceof Resident && $visit instanceof HealthVisit) {
            $resident = $visit->resident;
        }

        abort_unless($resident instanceof Resident, 404);

        return $resident;
    }
}
