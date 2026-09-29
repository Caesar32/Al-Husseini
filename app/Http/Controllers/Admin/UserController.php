<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of system users
     */
    public function index(Request $request): View
    {
        $query = User::with(['roles', 'branch'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->input('role'));
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $users = $query->paginate(15)->withQueryString();
        $roles = Role::all()->map(function (Role $r) {
            $meta = PermissionRegistry::getRoleMetadata($r->name);
            $r->display_label = $meta['label'];
            $r->badge_class = $meta['badge'];
            return $r;
        });
        $branches = Branch::where('is_active', true)->get();

        return view('admin.users.index', compact('users', 'roles', 'branches'));
    }

    /**
     * Store a newly created user in storage
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'password'  => ['required', 'string', 'min:6'],
        ], [
            'name.required'     => 'اسم المستخدم مطلوب.',
            'email.required'    => 'البريد الإلكتروني مطلوب.',
            'email.unique'      => 'هذا البريد الإلكتروني مسجل لمستخدم آخر بالفعل.',
            'role.required'     => 'يرجى اختيار الدور والصلاحيات للمستخدم.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min'      => 'كلمة المرور يجب ألا تقل عن 6 أحرف.',
        ]);

        $user = User::create([
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'phone'     => $request->input('phone'),
            'branch_id' => $request->filled('branch_id') ? $request->input('branch_id') : null,
            'password'  => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        $user->assignRole($request->input('role'));

        return redirect()->route('admin.users.index')
            ->with('status', "تم إنشاء حساب المستخدم ({$user->name}) وتعيين دور ({$request->input('role')}) له بنجاح.");
    }

    /**
     * Update the specified user in storage
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'     => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'password'  => ['nullable', 'string', 'min:6'],
        ], [
            'name.required'  => 'اسم المستخدم مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.unique'   => 'هذا البريد مسجل لمستخدم آخر.',
            'role.required'  => 'يرجى اختيار الدور للمستخدم.',
            'password.min'   => 'كلمة المرور يجب ألا تقل عن 6 أحرف.',
        ]);

        // Guard against removing the last super-admin
        if ($user->hasRole('super-admin') && $request->input('role') !== 'super-admin') {
            $superAdminsCount = User::role('super-admin')->count();
            if ($superAdminsCount <= 1) {
                return redirect()->route('admin.users.index')
                    ->withErrors(['user_error' => 'لا يمكن تغيير دور هذا المستخدم لأنه المشرف العام الوحيد المسجل بالنظام.']);
            }
        }

        $data = [
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'phone'     => $request->input('phone'),
            'branch_id' => $request->filled('branch_id') ? $request->input('branch_id') : null,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        // Sync role
        $user->syncRoles([$request->input('role')]);

        return redirect()->route('admin.users.index')
            ->with('status', "تم تحديث بيانات وصلاحيات المستخدم ({$user->name}) بنجاح.");
    }

    /**
     * Quick role update for user
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        // Guard against removing the last super-admin
        if ($user->hasRole('super-admin') && $request->input('role') !== 'super-admin') {
            $superAdminsCount = User::role('super-admin')->count();
            if ($superAdminsCount <= 1) {
                return redirect()->route('admin.users.index')
                    ->withErrors(['user_error' => 'لا يمكن تغيير دور هذا المستخدم لأنه المشرف العام الوحيد المسجل بالنظام.']);
            }
        }

        $user->syncRoles([$request->input('role')]);

        return redirect()->route('admin.users.index')
            ->with('status', "تم تحديث دور المستخدم ({$user->name}) إلى ({$request->input('role')}) بنجاح.");
    }

    /**
     * Toggle active/inactive status
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')
                ->withErrors(['user_error' => 'لا يمكنك تعطيل حسابك الحالي المسجل به الدخول.']);
        }

        if ($user->hasRole('super-admin') && $user->is_active) {
            $activeSuperAdmins = User::role('super-admin')->where('is_active', true)->count();
            if ($activeSuperAdmins <= 1) {
                return redirect()->route('admin.users.index')
                    ->withErrors(['user_error' => 'لا يمكن تعطيل حساب المشرف العام النشط الوحيد بالنظام.']);
            }
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $state = $user->is_active ? 'تفعيل' : 'تعطيل';

        return redirect()->route('admin.users.index')
            ->with('status', "تم {$state} حساب المستخدم ({$user->name}) بنجاح.");
    }

    /**
     * تعيين كلمة مرور جديدة للمستخدم من قِبل المشرف
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ], [
            'password.required' => 'يرجى إدخال كلمة المرور الجديدة.',
            'password.min'      => 'كلمة المرور يجب ألا تقل عن 6 أحرف.',
        ]);

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return redirect()->route('admin.users.index')
            ->with('status', "تم تعيين كلمة المرور الجديدة للمستخدم ({$user->name}) بنجاح.");
    }
}
