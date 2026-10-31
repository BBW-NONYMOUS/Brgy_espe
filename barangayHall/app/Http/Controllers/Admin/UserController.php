<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\TemporaryPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-only staff account management. Accounts are deactivated, never
 * deleted, so every record keeps its "attended by / recorded by" author.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive,locked'],
        ]);

        $search = trim($filters['search'] ?? '');

        $users = User::query()
            ->with('role')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->when($filters['role_id'] ?? null, fn (Builder $q, $roleId) => $q->where('role_id', $roleId))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'locked', fn (Builder $q) => $q->where('locked_until', '>', now()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => UserResource::collection($users),
            'filters' => [
                'search' => $search,
                'role_id' => isset($filters['role_id']) ? (int) $filters['role_id'] : null,
                'status' => $filters['status'] ?? null,
            ],
            'roles' => Role::query()->orderBy('id')->get(['id', 'name', 'label', 'description']),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $temporaryPassword = TemporaryPassword::generate();

        $user = User::query()->create([
            ...$request->validated(),
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        $this->flashTemporaryPassword($user, $temporaryPassword);
        $this->toast("Account {$user->username} created.");

        return to_route('users.index');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        $this->toast("Account {$user->username} updated.");

        return to_route('users.index');
    }

    public function resetPassword(User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        $temporaryPassword = TemporaryPassword::generate();

        $user->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'failed_attempts' => 0,
            'locked_until' => null,
            'remember_token' => Str::random(60),
        ])->save();

        $audit->log(AuditAction::PasswordReset, $user, "Reset password for {$user->username}");

        $this->flashTemporaryPassword($user, $temporaryPassword);
        $this->toast("Password reset for {$user->username}.");

        return to_route('users.index');
    }

    public function unlock(User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $user);

        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
        $audit->log(AuditAction::Updated, $user, "Unlocked account {$user->username}");

        $this->toast("Account {$user->username} unlocked.");

        return to_route('users.index');
    }

    /**
     * Shown once to the admin, who hands it to the staff member.
     */
    private function flashTemporaryPassword(User $user, string $password): void
    {
        Inertia::flash('temporaryPassword', ['username' => $user->username, 'password' => $password]);
    }
}
