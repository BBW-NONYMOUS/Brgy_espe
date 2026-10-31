<?php

namespace App\Http\Requests\Health;

use App\Enums\ReferralStatus;
use App\Models\Referral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Move a referral along (allowed transitions are checked by ReferralService).
 */
class ReferralStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $referral = $this->route('referral');

        return $referral instanceof Referral && ($this->user()?->can('update', $referral) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReferralStatus::class)->except([ReferralStatus::Pending])],
            'note' => ['nullable', 'string', 'max:255'],
            // Return slip from the facility.
            'feedback_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
