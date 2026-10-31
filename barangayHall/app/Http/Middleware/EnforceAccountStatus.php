<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated request:
 *  - a deactivated account is logged out immediately (even mid-session);
 *  - an account flagged must_change_password can only reach the
 *    change-password page and logout.
 */
class EnforceAccountStatus
{
    /** Routes reachable while a password change is pending. */
    private const ALLOWED_ROUTES = ['password.change', 'password.change.store', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => __('This account has been deactivated. Please contact the administrator.'),
            ]);
        }

        if ($user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
