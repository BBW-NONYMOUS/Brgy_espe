<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\PermissionName;
use App\Models\Appointment;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            ? ($this->user()?->can('update', $appointment) ?? false)
            : ($this->user()?->can('create', Appointment::class) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $updating = $this->route('appointment') instanceof Appointment;

        return [
            'resident_id' => [$updating ? 'prohibited' : 'required', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'appointment_type_id' => ['required', 'integer', Rule::exists('appointment_types', 'id')],
            'scheduled_at' => ['required', 'date'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'status' => [$updating ? 'required' : 'prohibited', Rule::enum(AppointmentStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'health_visit_id' => ['nullable', 'integer', Rule::exists('health_visits', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $staffId = $this->input('assigned_to');

                if ($staffId && ! User::query()->whereKey($staffId)->withPermission(PermissionName::AppointmentsManage)->exists()) {
                    $validator->errors()->add('assigned_to', 'Assign the appointment to a health worker.');
                }

                if (! $this->route('appointment') && $this->filled('scheduled_at') && strtotime((string) $this->input('scheduled_at')) < today()->getTimestamp()) {
                    $validator->errors()->add('scheduled_at', 'New appointments cannot be in the past.');
                }
            },
        ];
    }
}
