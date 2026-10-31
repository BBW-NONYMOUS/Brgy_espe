<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsValues;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Staff account as seen by an Admin on the User Management page.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    use FormatsValues;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'label' => $user->role->label,
            ]),
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'is_locked' => $user->isLocked(),
            'locked_until' => $this->dateTime($user->locked_until),
            'last_login_at' => $this->dateTime($user->last_login_at),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'created_at' => $this->dateTime($user->created_at),
        ];
    }
}
