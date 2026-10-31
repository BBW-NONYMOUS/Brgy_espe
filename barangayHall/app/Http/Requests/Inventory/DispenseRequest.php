<?php

namespace App\Http\Requests\Inventory;

use App\Models\Medicine;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $medicine = $this->route('medicine');

        return $medicine instanceof Medicine && ($this->user()?->can('dispense', $medicine) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'health_visit_id' => [
                'nullable', 'integer',
                Rule::exists('health_visits', 'id')->where('resident_id', (int) $this->input('resident_id'))->whereNull('deleted_at'),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function resident(): Resident
    {
        return Resident::query()->whereKey($this->validated('resident_id'))->firstOrFail();
    }
}
