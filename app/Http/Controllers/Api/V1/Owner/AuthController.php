<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate executive owner, verify super-admin/admin role, and issue Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login'       => ['required', 'string'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $login = trim($validated['login']);
        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'status'  => 'error',
                'message' => 'تم إيقاف هذا الحساب. يرجى مراجعة إدارة النظام.',
            ], 403);
        }

        // Security Enforcement: The Owner App is exclusively for executive management.
        if (! $user->hasRole('super-admin') && ! $user->hasRole('admin')) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'OWNER_ROLE_REQUIRED',
                'message' => 'هذا التطبيق مخصص لمالك وإدارة المنشأة فقط.',
            ], 403);
        }

        $deviceName = $validated['device_name'] ?? 'Executive-Device';
        $token = $user->createToken($deviceName, ['owner:monitor'], now()->addDays(90))->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'phone'       => $user->phone,
                    'role'        => $user->getRoleNames()->first() ?? 'owner',
                    'avatar_url'  => $user->avatarUrl(),
                    'branch'      => $user->branch ? [
                        'id'   => $user->branch->id,
                        'name' => $user->branch->name,
                    ] : null,
                ],
            ],
        ]);
    }

    /**
     * Revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * Return authenticated owner profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'role'       => $user->getRoleNames()->first(),
                'avatar_url' => $user->avatarUrl(),
                'branch'     => $user->branch ? [
                    'id'   => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                ] : null,
            ],
        ]);
    }

    /**
     * Register or update mobile push device token (FCM/APNs).
     */
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['required', 'string', 'max:500'],
            'platform'     => ['required', 'in:ios,android'],
            'device_name'  => ['nullable', 'string', 'max:100'],
        ]);

        UserDevice::updateOrCreate(
            ['device_token' => $validated['device_token']],
            [
                'user_id'        => $request->user()->id,
                'platform'       => $validated['platform'],
                'device_name'    => $validated['device_name'] ?? null,
                'last_active_at' => now(),
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'تم ربط الجهاز بنجاح لاستقبال الإشعارات الفورية.',
        ]);
    }
}
