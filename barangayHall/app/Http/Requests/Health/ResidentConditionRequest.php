<?php

namespace App\Http\Requests\Health;

use App\Enums\ConditionStatus;
use App\Models\Resident;
use App\Models\ResidentCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidentConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $condition = $this->route('condition');

        if ($condition instanceof ResidentCondition) {
            return $this->user()?->can('update', $condition) ?? false;
        }

        $resident = $this->route('resident');

        return $resident instanceof Resident && ($this->user()?->can('recordHealth', $resident) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'condition_id' => ['required', 'integer', Rule::exists('conditions', 'id')],
            'diagnosed_date' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::enum(ConditionStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
