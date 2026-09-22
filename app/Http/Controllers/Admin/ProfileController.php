<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
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

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('uploads/avatars');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);

            $user->update(['avatar' => $filename]);
        }

        return redirect()->back()->with('status', 'تم تحديث الصورة الشخصية بنجاح.');
    }
}
