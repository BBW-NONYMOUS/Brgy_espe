<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Forced password change on first login and after an admin reset.
 */
class PasswordChangeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/change-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(ChangePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();

        $audit->log(AuditAction::PasswordChanged, $user, 'Changed password (required at first login / after reset)');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password changed. Welcome!')]);

        return to_route('dashboard');
    }
}
