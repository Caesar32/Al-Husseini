<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user whose account was deactivated after login.
 * AuthController only checks is_active at login time.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'تم تعطيل حسابك. يرجى التواصل مع المشرف العام.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 401);
            }

            return redirect()->route('admin.login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
