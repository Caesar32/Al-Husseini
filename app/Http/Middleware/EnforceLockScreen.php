<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While the lock screen is active (session flag set by LockScreenController::show),
 * only the lock screen itself, unlocking, logout and session keep-alive are reachable.
 */
class EnforceLockScreen
{
    private const ALLOWED_ROUTES = [
        'admin.lockscreen',
        'admin.lockscreen.unlock',
        'admin.logout',
        'refresh_csrf',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->session()->get('lockscreen_locked', false)) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'الشاشة مقفلة. يرجى إدخال كلمة المرور لفتحها.'], 423);
        }

        return redirect()->route('admin.lockscreen');
    }
}
