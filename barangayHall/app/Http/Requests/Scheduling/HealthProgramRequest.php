<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\ProgramStatus;
use App\Enums\ProgramTargetGroup;
use App\Models\HealthProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HealthProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        $program = $this->route('program');

        return $program instanceof HealthProgram
            ? ($this->user()?->can('update', $program) ?? false)
            : ($this->user()?->can('create', HealthProgram::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $updating = $this->route('program') instanceof HealthProgram;

        return [
            'title' => ['required', 'string', 'max:150'],
            'program_type_id' => ['required', 'integer', Rule::exists('program_types', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'venue' => ['required', 'string', 'max:150'],
            'target_group' => ['required', Rule::enum(ProgramTargetGroup::class)],
            'status' => [$updating ? 'required' : 'prohibited', Rule::enum(ProgramStatus::class)],
            // Empty = every purok.
            'purok_ids' => ['array'],
            'purok_ids.*' => ['integer', Rule::exists('puroks', 'id')],
            'staff_ids' => ['array'],
            'staff_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
        ];
    }
}
