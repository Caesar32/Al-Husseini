<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\DeductionRule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. الفروع (فرع دمياط الجديدة - الفرع الحالي الفعلي للمجموعة)
        $mainBranch = Branch::updateOrCreate(['code' => 'MAIN'], [
            'name' => 'فرع دمياط الجديدة',
            'phone' => '0572400000',
            'address' => 'شارع المحجوب، دمياط الجديدة',
            'is_active' => true,
        ]);

        // 2. الأقسام
        $workshopDept = Department::firstOrCreate(['code' => 'WORKSHOP'], ['name' => 'ورشة الصيانة والشحن']);
        $salesDept = Department::firstOrCreate(['code' => 'SALES'], ['name' => 'المبيعات وصالة العرض']);
        $mgmtDept = Department::firstOrCreate(['code' => 'MGMT'], ['name' => 'الإدارة والإشراف']);
        $inventoryDept = Department::firstOrCreate(['code' => 'INVENTORY'], ['name' => 'المخازن وسلاسل الإمداد']);

        // 3. المسميات الوظيفية
        $techTitle = JobTitle::firstOrCreate(['department_id' => $workshopDept->id, 'title' => 'فني صيانة وبطاريات'], ['min_salary' => 5000, 'max_salary' => 9000]);
        $electricianTitle = JobTitle::firstOrCreate(['department_id' => $workshopDept->id, 'title' => 'كهربائي سيارات وتشخيص'], ['min_salary' => 6000, 'max_salary' => 11000]);
        $supervisorTitle = JobTitle::firstOrCreate(['department_id' => $workshopDept->id, 'title' => 'مشرف الورشة'], ['min_salary' => 8000, 'max_salary' => 14000]);
        $cashierTitle = JobTitle::firstOrCreate(['department_id' => $salesDept->id, 'title' => 'كاشير ومسؤول صالة بيع'], ['min_salary' => 4500, 'max_salary' => 7500]);
        $managerTitle = JobTitle::firstOrCreate(['department_id' => $mgmtDept->id, 'title' => 'مدير فرع'], ['min_salary' => 10000, 'max_salary' => 18000]);

        // 4. قواعد الجزاءات
        DeductionRule::firstOrCreate(['name' => 'تأخير أكثر من نصف ساعة'], [
            'type' => 'lateness',
            'calculation_method' => 'day_wage_multiplier',
            'multiplier_value' => 0.25,
            'description' => 'خصم ربع يوم عمل للتأخير بدون إذن',
        ]);
        DeductionRule::firstOrCreate(['name' => 'غياب بدون عذر مقبول'], [
            'type' => 'absence',
            'calculation_method' => 'day_wage_multiplier',
            'multiplier_value' => 1.00,
            'description' => 'خصم يوم كامل للغياب غير المبرر',
        ]);

        // 5. حساب المشرف العام للتجربة
        $superAdminUser = User::updateOrCreate(['email' => 'admin@alhusseini.com'], [
            'name' => 'المهندس أحمد الحسيني',
            'password' => Hash::make('12345678'),
            'branch_id' => $mainBranch->id,
            'is_active' => true,
        ]);
        if (!$superAdminUser->hasRole('super-admin')) {
            $superAdminUser->assignRole('super-admin');
        }

        // 6. حساب المحاسب للتجربة
        $accountantUser = User::updateOrCreate(['email' => 'accountant@alhusseini.com'], [
            'name' => 'محمد كمال - محاسب الفرع',
            'password' => Hash::make('12345678'),
            'branch_id' => $mainBranch->id,
            'is_active' => true,
        ]);
        if (!$accountantUser->hasRole('accountant')) {
            $accountantUser->assignRole('accountant');
        }

        // 7. حساب الكاشير للتجربة
        $cashierUser = User::updateOrCreate(['email' => 'cashier@alhusseini.com'], [
            'name' => 'أحمد سمير - كاشير المبيعات',
            'password' => Hash::make('12345678'),
            'branch_id' => $mainBranch->id,
            'is_active' => true,
        ]);
        if (!$cashierUser->hasRole('cashier')) {
            $cashierUser->assignRole('cashier');
        }

        // 8. موظفون تجريبيون
        $emp1 = Employee::firstOrCreate(['employee_code' => 'EMP-001'], [
            'branch_id' => $mainBranch->id,
            'job_title_id' => $techTitle->id,
            'full_name' => 'محمود إبراهيم الدسوقي',
            'national_id' => '29501010101111',
            'phone' => '01011111111',
            'hire_date' => '2024-01-15',
            'shift_start_time' => '09:00:00',
            'shift_end_time' => '17:00:00',
            'grace_period_minutes' => 15,
            'zkteco_pin' => '101',
            'status' => 'active',
        ]);
        SalaryStructure::firstOrCreate(['employee_id' => $emp1->id, 'is_current' => true], [
            'basic_salary' => 6500,
            'transport_allowance' => 500,
            'effective_from' => '2024-01-15',
        ]);

        $emp2 = Employee::firstOrCreate(['employee_code' => 'EMP-002'], [
            'branch_id' => $mainBranch->id,
            'job_title_id' => $electricianTitle->id,
            'full_name' => 'علي حسن الشرقاوي',
            'national_id' => '29302020202222',
            'phone' => '01022222222',
            'hire_date' => '2024-03-01',
            'shift_start_time' => '09:00:00',
            'shift_end_time' => '17:00:00',
            'grace_period_minutes' => 15,
            'zkteco_pin' => '102',
            'status' => 'active',
        ]);
        SalaryStructure::firstOrCreate(['employee_id' => $emp2->id, 'is_current' => true], [
            'basic_salary' => 7500,
            'transport_allowance' => 600,
            'effective_from' => '2024-03-01',
        ]);

        $emp3 = Employee::firstOrCreate(['employee_code' => 'EMP-003'], [
            'branch_id' => $mainBranch->id,
            'job_title_id' => $cashierTitle->id,
            'user_id' => $accountantUser->id,
            'full_name' => 'محمد كمال رضوان',
            'national_id' => '29603030303333',
            'phone' => '01033333333',
            'hire_date' => '2024-05-10',
            'shift_start_time' => '08:30:00',
            'shift_end_time' => '16:30:00',
            'grace_period_minutes' => 15,
            'zkteco_pin' => '103',
            'status' => 'active',
        ]);
        SalaryStructure::firstOrCreate(['employee_id' => $emp3->id, 'is_current' => true], [
            'basic_salary' => 6000,
            'housing_allowance' => 500,
            'effective_from' => '2024-05-10',
        ]);
    }
}
