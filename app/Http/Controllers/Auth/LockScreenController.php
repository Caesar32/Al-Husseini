<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LockScreenController extends Controller
{
    /**
     * عرض شاشة قفل النظام
     */
    public function show()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        session(['lockscreen_locked' => true]);

        return view('admin.auth.lockscreen', compact('user'));
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

        // ─── Rate Limiting: حماية من هجمات Brute Force على شاشة القفل ───────────
        $throttleKey = 'lockscreen|' . $user->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'password' => "تم تجاوز عدد محاولات فتح الشاشة المسموح بها. يرجى الانتظار {$seconds} ثانية.",
            ]);
        }
        // ──────────────────────────────────────────────────────────────────────────

        if (Hash::check($request->password, $user->password)) {
            RateLimiter::clear($throttleKey);
            session()->forget('lockscreen_locked');

            return redirect()->intended(route('admin.dashboard'))
                ->with('status', 'أهلاً بك مجدداً، تم فتح الشاشة بنجاح.');
        }

        RateLimiter::hit($throttleKey, 300); // 5 دقائق عقوبة

        return back()->withErrors([
            'password' => 'كلمة المرور غير صحيحة، يرجى إعادة المحاولة.',
        ]);
    }
}
