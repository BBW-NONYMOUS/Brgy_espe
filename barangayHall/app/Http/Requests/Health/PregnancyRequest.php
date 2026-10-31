<?php

namespace App\Http\Requests\Health;

use App\Enums\MaternalStatus;
use App\Enums\RiskLevel;
use App\Enums\Sex;
use App\Models\Pregnancy;
use App\Models\Resident;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PregnancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->route('pregnancy');

        if ($record instanceof Pregnancy) {
            return $this->user()?->can('update', $record) ?? false;
        }

        // Midwife/Admin only (maternal.manage), and never on an archived resident.
        $resident = $this->route('resident');

        return $resident instanceof Resident
            && ! $resident->trashed()
            && ($this->user()?->can('create', Pregnancy::class) ?? false);
    }

    /**
     * EDD defaults to LMP + 280 days (Naegele's rule) when left blank.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('edd')) && filled($this->input('lmp')) && strtotime((string) $this->input('lmp'))) {
            $this->merge([
                'edd' => Carbon::parse((string) $this->input('lmp'))->addDays(Pregnancy::GESTATION_DAYS)->toDateString(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $updating = $this->route('pregnancy') instanceof Pregnancy;

        return [
            'lmp' => ['nullable', 'date', 'before_or_equal:today', 'after:'.now()->subMonths(11)->toDateString()],
            'edd' => ['required', 'date', Rule::when(filled($this->input('lmp')), 'after:lmp')],
            'gravida' => ['required', 'integer', 'between:1,20'],
            'para' => ['required', 'integer', 'min:0', 'lte:gravida'],
            'risk_level' => ['required', Rule::enum(RiskLevel::class)],
            // Any flagged factor makes the pregnancy high-risk (PregnancyService).
            'risk_factor_ids' => ['sometimes', 'array'],
            'risk_factor_ids.*' => ['integer', Rule::exists('risk_factors', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            // "Delivered" is set by recording the delivery (DeliveryController), not here;
            // an already-delivered pregnancy keeps it when its details are edited.
            'status' => [
                $updating ? 'required' : 'prohibited',
                ($record = $this->route('pregnancy')) instanceof Pregnancy && $record->status === MaternalStatus::Delivered
                    ? Rule::in([MaternalStatus::Delivered->value])
                    : Rule::enum(MaternalStatus::class)->except([MaternalStatus::Delivered]),
            ],
            'outcome_date' => [
                'nullable', 'date', 'before_or_equal:today',
                Rule::requiredIf(fn () => $updating && $this->input('status') !== MaternalStatus::Ongoing->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'para.lte' => 'Para (births) cannot be greater than gravida (pregnancies).',
            'lmp.after' => 'The LMP is too long ago for a current pregnancy.',
            'outcome_date.required' => 'Enter the date of delivery or pregnancy loss.',
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $resident = $this->route('resident');

                if (! $resident instanceof Resident) {
                    return;
                }

                if ($resident->sex !== Sex::Female || $resident->age < 10 || $resident->age > 55) {
                    $validator->errors()->add('resident', 'Pregnancy records can only be added for female residents aged 10 to 55.');
                }

                if ($resident->pregnancies()->where('status', MaternalStatus::Ongoing)->exists()) {
                    $validator->errors()->add('resident', 'This resident already has an ongoing pregnancy record. Update it instead.');
                }
            },
        ];
    }
}
