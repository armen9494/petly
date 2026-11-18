<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user whose account an admin has deactivated, on whatever device
 * they make their next request from.
 */
class EnsureUserIsActive
{
    public const string MESSAGE = 'This account is inactive.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_active === false) {
            // Logging out also replaces the remember token, so a "Remember me"
            // cookie can't sign them straight back in.
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
