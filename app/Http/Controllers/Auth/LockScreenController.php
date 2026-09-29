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
     * عرض شاشة قفل النظام
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        session(['lockscreen_locked' => true]);

        $throttleKey = $this->throttleKey($request, $user);
        $lockoutSeconds = RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)
            ? RateLimiter::availableIn($throttleKey)
            : 0;

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
     * إلغاء قفل الشاشة والتحقق من كلمة المرور
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

        $throttleKey = $this->throttleKey($request, $user);

        // Rate limiting: the backend remains authoritative during lockout.
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'password' => "تم تجاوز عدد محاولات فتح الشاشة المسموح بها. يرجى الانتظار {$seconds} ثانية.",
            ]);
        }

        if (Hash::check($request->password, $user->password)) {
            RateLimiter::clear($throttleKey);
            session()->forget('lockscreen_locked');

            return redirect()->intended(route('admin.dashboard'))
                ->with('status', 'أهلاً بك مجدداً، تم فتح الشاشة بنجاح.');
        }

        RateLimiter::hit($throttleKey, self::LOCKOUT_SECONDS);

        return back()->withErrors([
            'password' => 'كلمة المرور غير صحيحة، يرجى إعادة المحاولة.',
        ]);
    }

    private function throttleKey(Request $request, $user): string
    {
        return 'lockscreen|' . $user->id . '|' . $request->ip();
    }
}
