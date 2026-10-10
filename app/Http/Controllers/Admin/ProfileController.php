<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    private const AVATAR_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * عرض الصفحة الشخصية للمستخدم الحالي
     */
    public function index()
    {
        $user = Auth::user()->load(['roles.permissions', 'branch']);
        return view('admin.profile.index', compact('user'));
    }

    /**
     * تحديث البيانات الأساسية (الاسم، البريد، الهاتف)
     */
    public function updateInfo(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:25'],
        ], [
            'name.required' => 'يرجى إدخال الاسم بالكامل.',
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'صيغة البريد الإلكتروني غير صالحة.',
            'email.unique' => 'هذا البريد مسجل مسبقاً لمستخدم آخر.',
        ]);

        $user->update($validated);

        return redirect()->back()->with('status', 'تم تحديث البيانات الشخصية بنجاح.');
    }

    /**
     * تغيير كلمة المرور
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'يرجى إدخال كلمة المرور الحالية.',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'password.required' => 'يرجى إدخال كلمة المرور الجديدة.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف وأرقام.',
        ]);

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('status', 'تم تغيير كلمة المرور وتأمين الحساب بنجاح.');
    }

    /**
     * تحديث الصورة الشخصية
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'avatar.required' => 'يرجى اختيار صورة.',
            'avatar.image' => 'يجب أن يكون الملف المرفوع صورة.',
            'avatar.max' => 'الحد الأقصى لحجم الصورة هو 2 ميجابايت.',
        ]);

        $user = Auth::user();
        $file = $request->file('avatar');

        // The extension comes from the detected content type, never from the client-supplied
        // name, and the file is stored outside the web root (private "local" disk), so an
        // uploaded file can never be requested or executed directly by the web server.
        $extension = $file->guessExtension();
        if (!in_array($extension, self::AVATAR_EXTENSIONS, true)) {
            return redirect()->back()->withErrors(['avatar' => 'يجب أن تكون الصورة بصيغة JPG أو PNG أو WEBP.']);
        }

        $path = $file->storeAs('avatars', $user->id . '-' . Str::random(32) . '.' . $extension, 'local');
        if ($path === false) {
            return redirect()->back()->withErrors(['avatar' => 'تعذر حفظ الصورة، يرجى المحاولة مرة أخرى.']);
        }

        $previous = (string) $user->avatar;
        $user->update(['avatar' => $path]);

        if (str_starts_with($previous, 'avatars/')) {
            Storage::disk('local')->delete($previous);
        }

        return redirect()->back()->with('status', 'تم تحديث الصورة الشخصية بنجاح.');
    }

    /**
     * عرض الصورة الشخصية المخزنة خارج المجلد العام (للمستخدمين المسجلين فقط)
     */
    public function showAvatar(User $user): StreamedResponse
    {
        $path = (string) $user->avatar;

        abort_unless(str_starts_with($path, 'avatars/') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
