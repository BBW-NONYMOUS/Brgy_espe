<?php

namespace App\Http\Requests\Residents;

use App\Enums\CivilStatus;
use App\Enums\RelationshipToHead;
use App\Enums\Sex;
use App\Models\Resident;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validation shared by StoreResidentRequest and UpdateResidentRequest.
 */
trait ResidentRules
{
    /** Letters (incl. ñ and accents), spaces, period, apostrophe, hyphen. */
    private const NAME_PATTERN = "/^[\pL\s.'-]+$/u";

    /**
     * @return array<string, mixed>
     */
    protected function residentRules(): array
    {
        return [
            'household_id' => ['required', 'integer', Rule::exists('households', 'id')],
            'last_name' => ['required', 'string', 'max:100', 'regex:'.self::NAME_PATTERN],
            'first_name' => ['required', 'string', 'max:100', 'regex:'.self::NAME_PATTERN],
            'middle_name' => ['nullable', 'string', 'max:100', 'regex:'.self::NAME_PATTERN],
            'suffix' => ['nullable', Rule::in(Resident::SUFFIXES)],
            'sex' => ['required', Rule::enum(Sex::class)],
            'birthdate' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'birthplace' => ['nullable', 'string', 'max:150'],
            'civil_status' => ['required', Rule::enum(CivilStatus::class)],
            'contact_no' => ['nullable', 'string', 'regex:/^09\d{9}$/'],
            'philhealth_no' => ['nullable', 'string', 'regex:/^\d{2}-\d{9}-\d$/'],
            'is_pwd' => ['boolean'],
            'relationship_to_head' => ['required', Rule::enum(RelationshipToHead::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function residentMessages(): array
    {
        return [
            '*.regex' => 'Use letters, spaces, periods, apostrophes or hyphens only.',
            'contact_no.regex' => 'Enter an 11-digit mobile number starting with 09 (e.g. 09171234567).',
            'philhealth_no.regex' => 'Enter the 12-digit PhilHealth number (e.g. 02-051234567-1).',
            'birthdate.before_or_equal' => 'Birthdate cannot be in the future.',
        ];
    }

    protected function normalizeResidentInput(): void
    {
        $squish = fn ($value) => is_string($value) && trim($value) !== '' ? Str::squish($value) : null;

        $philhealth = preg_replace('/\D/', '', (string) $this->input('philhealth_no'));
        $contact = preg_replace('/\D/', '', (string) $this->input('contact_no'));

        if (is_string($contact) && str_starts_with($contact, '639') && strlen($contact) === 12) {
            $contact = '0'.substr($contact, 2);
        }

        $this->merge([
            'last_name' => $squish($this->input('last_name')),
            'first_name' => $squish($this->input('first_name')),
            'middle_name' => $squish($this->input('middle_name')),
            'suffix' => $squish($this->input('suffix')),
            'birthplace' => $squish($this->input('birthplace')),
            'contact_no' => $contact !== '' ? $contact : null,
            'philhealth_no' => is_string($philhealth) && strlen($philhealth) === 12
                ? substr($philhealth, 0, 2).'-'.substr($philhealth, 2, 9).'-'.substr($philhealth, 11)
                : ($squish($this->input('philhealth_no'))),
            'is_pwd' => $this->boolean('is_pwd'),
        ]);
    }
}
