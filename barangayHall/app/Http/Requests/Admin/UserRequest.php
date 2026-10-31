<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin create / update of staff accounts. Passwords are never typed here:
 * a temporary password is generated and shown once to the admin.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? ($this->user()?->can('update', $user) ?? false)
            : ($this->user()?->can('create', User::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::lower(trim((string) $this->input('username'))),
            'name' => Str::squish((string) $this->input('name')),
            'email' => filled($this->input('email')) ? Str::lower(trim((string) $this->input('email'))) : null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users')->ignore($userId)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Use lowercase letters, numbers, dots, dashes or underscores (e.g. bhw.maria).',
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');
                $actor = $this->user();

                if (! $target instanceof User || ! $actor instanceof User || ! $actor->is($target)) {
                    return;
                }

                if ((int) $this->input('role_id') !== $target->role_id || ! $this->boolean('is_active')) {
                    $validator->errors()->add('role_id', 'You cannot change your own role or deactivate your own account.');
                }
            },
        ];
    }
}
