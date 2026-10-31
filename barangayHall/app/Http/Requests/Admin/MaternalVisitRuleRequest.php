<?php

namespace App\Http\Requests\Admin;

use App\Enums\MaternalVisitKind;
use App\Enums\PermissionName;
use App\Models\MaternalVisitRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Midwife/Admin: prenatal (weeks of gestation) and postnatal (days after delivery) contacts.
 */
class MaternalVisitRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SchedulesManage) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rule = $this->route('rule');

        return [
            'kind' => ['required', Rule::enum(MaternalVisitKind::class)],
            'sequence' => [
                'required', 'integer', 'between:1,20',
                Rule::unique('maternal_visit_rules')->where('kind', $this->input('kind'))->ignore($rule instanceof MaternalVisitRule ? $rule->id : null),
            ],
            'label' => ['required', 'string', 'max:60'],
            // Prenatal: up to 42 weeks of gestation; postnatal: up to 6 weeks after delivery.
            'offset_days' => ['required', 'integer', 'min:0', $this->input('kind') === MaternalVisitKind::Postnatal->value ? 'max:60' : 'max:294'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['sequence.unique' => 'Another contact already uses this number.'];
    }
}
