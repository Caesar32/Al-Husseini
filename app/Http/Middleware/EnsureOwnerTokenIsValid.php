<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureOwnerTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active || ! $user->hasRole('super-admin')) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'status' => 'error',
                'code' => 'OWNER_ACCESS_REVOKED',
                'message' => 'تم إلغاء صلاحية الوصول.',
            ], 401);
        }

        return $next($request);
    }
}
