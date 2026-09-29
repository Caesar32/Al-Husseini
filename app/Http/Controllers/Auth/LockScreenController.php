<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LockScreenController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300;

    /**
     * عرض شاشة قفل النظام.
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        session(['lockscreen_locked' => true]);

        $lockoutKey = $this->lockoutKey($request, $user);
        $attemptsKey = $this->attemptsKey($request, $user);

        $lockoutSeconds = RateLimiter::tooManyAttempts($lockoutKey, 1)
            ? RateLimiter::availableIn($lockoutKey)
            : 0;

        if ($lockoutSeconds <= 0) {
            RateLimiter::clear($lockoutKey);
            RateLimiter::clear($attemptsKey);
        }

        $lockoutUntil = $lockoutSeconds > 0
            ? now()->addSeconds($lockoutSeconds)->timestamp
            : null;

        return view('admin.auth.lockscreen', compact(
            'user',
            'lockoutSeconds',
            'lockoutUntil'
        ));
    }

    /**
     * إلغاء قفل الشاشة والتحقق من كلمة المرور.
     */
    public function unlock(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'يرجى إدخال كلمة المرور لفتح الشاشة.',
        ]);

        $user = Auth::user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        $lockoutKey = $this->lockoutKey($request, $user);
        $attemptsKey = $this->attemptsKey($request, $user);

        // The backend remains authoritative while the lockout is active.
        if (RateLimiter::tooManyAttempts($lockoutKey, 1)) {
            $seconds = RateLimiter::availableIn($lockoutKey);

            throw ValidationException::withMessages([
                'password' => "تم تجاوز عدد محاولات فتح الشاشة المسموح بها. يرجى الانتظار {$seconds} ثانية.",
            ]);
        }

        if (Hash::check($request->password, $user->password)) {
            RateLimiter::clear($attemptsKey);
            RateLimiter::clear($lockoutKey);
            session()->forget('lockscreen_locked');

            return redirect()->intended(route('admin.dashboard'))
                ->with('status', 'أهلاً بك مجدداً، تم فتح الشاشة بنجاح.');
        }

        $attempts = RateLimiter::hit($attemptsKey, self::LOCKOUT_SECONDS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Start a fresh, full lockout window from the fifth failed attempt.
            RateLimiter::clear($lockoutKey);
            RateLimiter::hit($lockoutKey, self::LOCKOUT_SECONDS);

            $seconds = RateLimiter::availableIn($lockoutKey);

            return back()->withErrors([
                'password' => "تم تجاوز عدد محاولات فتح الشاشة المسموح بها. يرجى الانتظار {$seconds} ثانية.",
            ]);
        }

        return back()->withErrors([
            'password' => 'كلمة المرور غير صحيحة، يرجى إعادة المحاولة.',
        ]);
    }

    private function attemptsKey(Request $request, $user): string
    {
        return 'lockscreen:attempts|' . $user->id . '|' . $request->ip();
    }

    private function lockoutKey(Request $request, $user): string
    {
        return 'lockscreen:lockout|' . $user->id . '|' . $request->ip();
    }
}
