<?php

namespace App\Http\Requests\Residents;

use App\Enums\ResidentStatus;
use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchiveResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resident = $this->route('resident');

        return $resident instanceof Resident && ($this->user()?->can('delete', $resident) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ResidentStatus::class)->only([ResidentStatus::Moved, ResidentStatus::Deceased, ResidentStatus::Active])],
            'archive_reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archive_reason.required' => 'Please explain why this record is being archived (e.g. "Moved to Tacurong City, March 2026").',
        ];
    }
}
