<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use App\Models\VaccineScheduleRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Midwife/Admin: when each EPI dose is due.
 */
class VaccineScheduleRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(PermissionName::SchedulesManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rule = $this->route('vaccineRule');

        return [
            'vaccine_id' => ['required', 'integer', Rule::exists('vaccines', 'id')],
            'dose_no' => [
                'required', 'integer', 'between:1,10',
                Rule::unique('vaccine_schedule_rules')->where('vaccine_id', $this->input('vaccine_id'))->ignore($rule instanceof VaccineScheduleRule ? $rule->id : null),
            ],
            'due_age_days' => ['required', 'integer', 'between:0,6000'],
            'min_interval_days' => ['nullable', 'integer', 'between:1,3650'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['dose_no.unique' => 'This vaccine already has a rule for that dose.'];
    }
}
