<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Protected system roles that cannot be deleted
     */
    protected array $systemRoles = [
        'super-admin',
        'branch-manager',
        'accountant',
        'cashier',
        'workshop-supervisor',
    ];

    /**
     * Display a listing of all roles
     */
    public function index(): View
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('id')
            ->get()
            ->map(function (Role $role) {
                $meta = PermissionRegistry::getRoleMetadata($role->name);
                $role->display_label = $meta['label'];
                $role->badge_class = $meta['badge'];
                $role->icon_class = $meta['icon'];
                $role->description_text = $meta['description'];
                $role->is_system = in_array($role->name, $this->systemRoles, true);
                return $role;
            });

        $totalPermissions = Permission::count();

        return view('admin.roles.index', compact('roles', 'totalPermissions'));
    }

    /**
     * Show form for creating a new role
     */
    public function create(): View
    {
        $groupedPermissions = PermissionRegistry::getGroupedPermissions();

        return view('admin.roles.create', compact('groupedPermissions'));
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'          => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\-_]+$/', 'unique:roles,name'],
            'display_name'  => ['nullable', 'string', 'max:100'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ], [
            'name.required' => 'يرجى إدخال المعرف البرمجي للدور بالإنجليزية.',
            'name.unique'   => 'هذا الدور موجود بالفعل في النظام.',
            'name.regex'    => 'المعرف البرمجي يجب أن يحتوي فقط على أحرف وأرقام وشرطات (a-z, 0-9, -).',
        ]);

        $roleSlug = Str::slug($request->input('name'));

        $role = Role::create([
            'name'       => $roleSlug,
            'guard_name' => 'web',
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->input('permissions', []));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')
            ->with('status', "تم إنشاء الدور ({$role->name}) وتحديد صلاحياته بنجاح.");
    }

    /**
     * Show form for editing an existing role
     */
    public function edit(Role $role): View
    {
        $groupedPermissions = PermissionRegistry::getGroupedPermissions();
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        $meta = PermissionRegistry::getRoleMetadata($role->name);
        $role->display_label = $meta['label'];
        $role->description_text = $meta['description'];
        $role->is_system = in_array($role->name, $this->systemRoles, true);

        return view('admin.roles.edit', compact('role', 'groupedPermissions', 'rolePermissions'));
    }

    /**
     * Update the specified role in storage
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'super-admin') {
            return redirect()->route('admin.roles.index')
                ->with('warning', 'دور المشرف العام (Super Admin) يملك كافة الصلاحيات بصورة دائمة ولا يمكن تقييده.');
        }

        $isSystem = in_array($role->name, $this->systemRoles, true);

        $rules = [
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];

        if (!$isSystem) {
            $rules['name'] = [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_]+$/',
                'unique:roles,name,' . $role->id,
            ];
        }

        $request->validate($rules, [
            'name.required' => 'اسم الدور مطلوب.',
            'name.unique'   => 'اسم الدور مستخدم مسبقاً.',
        ]);

        if (!$isSystem && $request->filled('name')) {
            $role->name = Str::slug($request->input('name'));
            $role->save();
        }

        $role->syncPermissions($request->input('permissions', []));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')
            ->with('status', "تم تحديث صلاحيات الدور ({$role->name}) بنجاح.");
    }

    /**
     * Remove the specified role from storage
     */
    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, $this->systemRoles, true)) {
            return redirect()->route('admin.roles.index')
                ->withErrors(['role_error' => "لا يمكن حذف الدور الأساسي للنظام ({$role->name})."]);
        }

        if ($role->users()->count() > 0) {
            return redirect()->route('admin.roles.index')
                ->withErrors(['role_error' => "لا يمكن حذف الدور ({$role->name}) لوجود ({$role->users()->count()}) مستخدمين مرتبطين به حالياً. قم بنقلهم إلى دور آخر أولاً."]);
        }

        $roleName = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')
            ->with('status', "تم حذف الدور ({$roleName}) بنجاح.");
    }
}
