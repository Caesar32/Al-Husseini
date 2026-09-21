<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إعادة ضبط الذاكرة المؤقتة للأذونات (Reset cached roles and permissions)
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. تعريف قائمة الصلاحيات الشاملة للنظام (All Permissions)
        $permissions = [
            // إدارة الفروع والإعدادات العامة
            'branches.view',
            'branches.create',
            'branches.edit',
            'branches.delete',

            // نقاط البيع والفواتير (POS & Sales)
            'pos.access',
            'invoices.view',
            'invoices.create',
            'invoices.print',
            'invoices.cancel',
            'invoices.discount',

            // مديونيات العملاء والتحصيل (Credit & Receivables)
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'credit.view',
            'credit.settle',
            'credit.adjust_limit',

            // إدارة المخزون والبطاريات (Inventory & Core Scrap)
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'scrap.view',
            'scrap.transfer',

            // إدارة الموردين وفواتير الشراء (Suppliers & Purchases)
            'suppliers.view',
            'suppliers.create',
            'suppliers.edit',
            'suppliers.delete',
            'purchases.view',
            'purchases.create',
            'purchases.settle_payment',

            // الضمانات وخدمات الورشة (Warranties & Workshop Claims)
            'warranties.view',
            'warranties.claim',
            'warranties.approve_replace',

            // شؤون الموظفين والرواتب (HR & Workforce)
            'employees.view',
            'employees.create',
            'employees.edit',
            'employees.delete',
            'attendance.view',
            'attendance.manual_punch',
            'deductions.manage',
            'leaves.manage',
            'payroll.generate',
            'payroll.approve',
            'payroll.disburse',

            // التقارير المالية والإحصائيات (Financial Reports)
            'reports.financial',
            'reports.sales',
            'reports.hr',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 3. إنشاء دور المشرف العام (Super Admin) - مع كافة الصلاحيات
        $superAdminRole = Role::findOrCreate('super-admin', 'web');
        $superAdminRole->syncPermissions(Permission::all());

        // 4. إنشاء دور المحاسب / الكاشير (Accountant / Cashier) - صلاحيات محددة فقط
        $accountantRole = Role::findOrCreate('accountant', 'web');
        $accountantRole->syncPermissions([
            // الكاشير والمبيعات
            'pos.access',
            'invoices.view',
            'invoices.create',
            'invoices.print',
            
            // إدارة العملاء والتحصيل
            'customers.view',
            'customers.create',
            'credit.view',
            'credit.settle', // تحصيل دفعات من مديونيات العملاء

            // المخزون (عرض فقط + استلام بطاريات الكهنة)
            'products.view',
            'scrap.view',

            // فحص سريان الضمانات
            'warranties.view',

            // تقارير المبيعات اليومية
            'reports.sales',
        ]);

        // 5. إنشاء دور مدير الفرع (Branch Manager)
        $branchManagerRole = Role::findOrCreate('branch-manager', 'web');
        $branchManagerRole->syncPermissions([
            'pos.access',
            'invoices.view',
            'invoices.create',
            'invoices.print',
            'invoices.discount',
            'customers.view',
            'customers.create',
            'customers.edit',
            'credit.view',
            'credit.settle',
            'products.view',
            'products.create',
            'products.edit',
            'scrap.view',
            'scrap.transfer',
            'warranties.view',
            'warranties.claim',
            'employees.view',
            'attendance.view',
            'attendance.manual_punch',
            'deductions.manage',
            'leaves.manage',
            'payroll.generate',
            'reports.sales',
            'reports.hr',
        ]);

        // 6. إنشاء دور مشرف الورشة (Workshop Supervisor)
        $workshopSupervisorRole = Role::findOrCreate('workshop-supervisor', 'web');
        $workshopSupervisorRole->syncPermissions([
            'products.view',
            'warranties.view',
            'warranties.claim',
            'warranties.approve_replace',
            'scrap.view',
            'attendance.view',
        ]);

        // 7. تعيين مستخدم تجريبي للمشرف العام والمحاسب في حال وجودهما
        $adminUser = User::first();
        if ($adminUser) {
            $adminUser->assignRole('super-admin');
        }
    }
}
