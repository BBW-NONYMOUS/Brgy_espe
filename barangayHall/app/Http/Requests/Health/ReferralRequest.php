<?php

namespace App\Http\Requests\Health;

use App\Enums\ReferralUrgency;
use App\Models\Referral;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Referral::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'facility_id' => ['required', 'integer', Rule::exists('facilities', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'max:1000'],
            'urgency' => ['required', Rule::enum(ReferralUrgency::class)],
            'referred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'health_visit_id' => [
                'nullable', 'integer',
                Rule::exists('health_visits', 'id')->where('resident_id', (int) $this->input('resident_id'))->whereNull('deleted_at'),
            ],
        ];
    }

    public function resident(): Resident
    {
        return Resident::query()->whereKey($this->validated('resident_id'))->firstOrFail();
    }
}
