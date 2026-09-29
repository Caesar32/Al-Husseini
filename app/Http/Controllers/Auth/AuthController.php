<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * عرض شاشة تسجيل الدخول
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if ($user->hasRole('cashier') || (!$user->can('dashboard.view') && $user->can('pos.access'))) {
                return redirect()->route('admin.pos.index');
            }
            return redirect()->route('admin.dashboard');
        }

        $lockoutSeconds = session('lockout_seconds') ?? 0;

        return view('admin.auth.login', compact('lockoutSeconds'));
    }

    /**
     * معالجة تسجيل الدخول
     */
    public function login(Request $request)
    {
        $login = trim((string) ($request->input('login') ?? $request->input('email')));
        $password = (string) $request->input('password');

        if ($login === '') {
            throw ValidationException::withMessages([
                'email' => 'يرجى إدخال اسم المستخدم أو البريد الإلكتروني أو الهاتف.',
            ]);
        }

        if ($password === '') {
            throw ValidationException::withMessages([
                'password' => 'يرجى إدخال كلمة المرور.',
            ]);
        }

        $throttleKey = Str::transliterate(Str::lower($login) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            session()->flash('lockout_seconds', $seconds);
            throw ValidationException::withMessages([
                'email' => "تم تجاوز عدد محاولات الدخول المسموح بها. يرجى المحاولة بعد {$seconds} ثانية.",
            ]);
        }

        $remember = $request->boolean('remember');
        $authenticated = false;

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $authenticated = Auth::attempt(['email' => $login, 'password' => $password], $remember);
        } else {
            // Try by phone
            if (Auth::attempt(['phone' => $login, 'password' => $password], $remember)) {
                $authenticated = true;
            } else {
                // Try matching by name
                $candidate = User::where('name', $login)->first();
                if ($candidate && \Illuminate\Support\Facades\Hash::check($password, $candidate->password)) {
                    Auth::login($candidate, $remember);
                    $authenticated = true;
                }
            }
        }

        if ($authenticated) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                return back()->withInput()->withErrors([
                    'email' => 'هذا الحساب معطل حالياً من قِبل إدارة النظام.',
                ]);
            }

            RateLimiter::clear($throttleKey);
            session()->forget('lockout_seconds');
            $request->session()->regenerate();

            if ($user->hasRole('cashier') || (!$user->can('dashboard.view') && $user->can('pos.access'))) {
                return redirect()->intended(route('admin.pos.index'));
            }

            return redirect()->intended(route('admin.dashboard'));
        }

        RateLimiter::hit($throttleKey, 300);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            session()->flash('lockout_seconds', $seconds);
            throw ValidationException::withMessages([
                'email' => "تم تجاوز عدد محاولات الدخول المسموح بها. يرجى المحاولة بعد {$seconds} ثانية.",
            ]);
        }

        return back()->withInput($request->only('email', 'login', 'remember'))->withErrors([
            'email' => 'بيانات تسجيل الدخول غير صحيحة، يرجى التأكد من البريد أو اسم المستخدم وكلمة المرور.',
        ]);
    }

    /**
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'تم تسجيل الخروج من النظام بنجاح.');
    }
}
