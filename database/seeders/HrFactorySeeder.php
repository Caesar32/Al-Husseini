<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\Attendance;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\DeductionRule;
use App\Models\User;
use Carbon\Carbon;

class HrFactorySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('بدء تهيئة وتغذية بيانات الموارد البشرية عبر الـ Factories...');

        // 1. الفروع (Branches - فرع دمياط الجديدة الفرع الفعلي حالياً)
        $branchesData = [
            ['name' => 'فرع دمياط الجديدة', 'code' => 'MAIN', 'phone' => '0572400000', 'address' => 'شارع المحجوب، دمياط الجديدة'],
        ];

        $branches = collect();
        foreach ($branchesData as $bData) {
            $branch = Branch::firstOrCreate(
                ['code' => $bData['code']],
                [
                    'name' => $bData['name'],
                    'phone' => $bData['phone'],
                    'address' => $bData['address'],
                    'is_active' => true,
                ]
            );
            $branches->push($branch);
        }

        // 2. الأقسام (Departments)
        $departmentsData = [
            ['name' => 'ورشة الصيانة والشحن السريع', 'code' => 'WORKSHOP'],
            ['name' => 'المبيعات وصالة العرض', 'code' => 'SALES'],
            ['name' => 'الإدارة العامة والموارد البشرية', 'code' => 'MGMT'],
            ['name' => 'المستودعات وسلاسل الإمداد', 'code' => 'INVENTORY'],
            ['name' => 'الحسابات والشؤون المالية', 'code' => 'FINANCE'],
        ];

        $departments = collect();
        foreach ($departmentsData as $dData) {
            $dept = Department::firstOrCreate(
                ['code' => $dData['code']],
                ['name' => $dData['name']]
            );
            $departments->push($dept);
        }

        // 3. المسميات الوظيفية (Job Titles)
        $jobTitlesData = [
            ['dept' => 'WORKSHOP', 'title' => 'كبير فنيي بطاريات سيارات', 'min' => 7000, 'max' => 12000],
            ['dept' => 'WORKSHOP', 'title' => 'فني تشخيص بطاريات ودينامو', 'min' => 5500, 'max' => 9500],
            ['dept' => 'WORKSHOP', 'title' => 'مساعد فني شحن وتركيب', 'min' => 4500, 'max' => 7000],
            ['dept' => 'SALES', 'title' => 'مدير مبيعات المعرض', 'min' => 8000, 'max' => 14000],
            ['dept' => 'SALES', 'title' => 'أخصائي مبيعات واستبدال بطاريات', 'min' => 5000, 'max' => 9000],
            ['dept' => 'SALES', 'title' => 'كاشير ومسؤول خزينة الفرع', 'min' => 5000, 'max' => 8000],
            ['dept' => 'MGMT', 'title' => 'مدير فرع تشغيلي', 'min' => 12000, 'max' => 22000],
            ['dept' => 'MGMT', 'title' => 'أخصائي شؤون موظفين وموارد بشرية', 'min' => 6000, 'max' => 10000],
            ['dept' => 'INVENTORY', 'title' => 'أمين مخزن بطاريات وسكراب', 'min' => 5500, 'max' => 9000],
            ['dept' => 'FINANCE', 'title' => 'محاسب فرع ومراجع مالي', 'min' => 6500, 'max' => 11000],
        ];

        $jobTitles = collect();
        foreach ($jobTitlesData as $jt) {
            $dept = $departments->firstWhere('code', $jt['dept']);
            $job = JobTitle::firstOrCreate(
                ['department_id' => $dept->id, 'title' => $jt['title']],
                ['min_salary' => $jt['min'], 'max_salary' => $jt['max']]
            );
            $jobTitles->push($job);
        }

        // 4. مستخدم المسؤول لاعتماد العمليات
        $admin = User::first() ?? User::factory()->create([
            'name' => 'المهندس أحمد الحسيني',
            'email' => 'admin@alhusseini.com',
        ]);

        // 5. قواعد الجزاءات
        $deductionRuleLate = DeductionRule::firstOrCreate(['name' => 'تأخير غير مبرر'], [
            'type' => 'lateness',
            'calculation_method' => 'day_wage_multiplier',
            'multiplier_value' => 0.25,
            'description' => 'خصم ربع يوم للتأخير أكثر من 15 دقيقة',
        ]);

        $deductionRuleAbsence = DeductionRule::firstOrCreate(['name' => 'غياب بدون إذن مسبق'], [
            'type' => 'absence',
            'calculation_method' => 'day_wage_multiplier',
            'multiplier_value' => 1.00,
            'description' => 'خصم يوم كامل عن يوم الغياب غير المعتمد',
        ]);

        // 6. توليد موظفين واقعيين باستخدام الأسماء والبيانات الواقعية
        $arabicNames = [
            'محمود أحمد النجار',
            'طارق عبدالسلام غانم',
            'كريم صبحي الحسيني',
            'عمر فاروق الشهاوي',
            'يوسف إبراهيم رضوان',
            'إسلام حامد الدسوقي',
            'مصطفى كمال خليل',
            'حسام الدين الشريف',
            'علاء حسن عفيفي',
            'بلال عبدالحليم زكي',
            'سامح عادل الجوهري',
            'رمضان شعبان منصور',
            'أشرف عبدالمعطي الباز',
            'وائل كمال عثمان',
            'محمد فتحي الجمال',
            'هشام توفيق البدوي',
            'مدحت عبدالفتاح قنديل',
            'ياسر ممدوح العشري',
        ];

        $allEmployees = collect();

        foreach ($arabicNames as $index => $fullName) {
            $branch = $branches[$index % $branches->count()];
            $jobTitle = $jobTitles[$index % $jobTitles->count()];
            $code = sprintf('EMP-%04d', $index + 101);
            $pin = sprintf('%04d', $index + 201);

            $employee = Employee::firstOrCreate(
                ['employee_code' => $code],
                [
                    'branch_id' => $branch->id,
                    'job_title_id' => $jobTitle->id,
                    'full_name' => $fullName,
                    'national_id' => '29' . rand(85, 99) . sprintf('%010d', rand(1000000000, 9999999999)),
                    'phone' => '01' . rand(0, 2) . sprintf('%08d', rand(10000000, 99999999)),
                    'hire_date' => Carbon::now()->subMonths(rand(2, 24))->format('Y-m-d'),
                    'shift_start_time' => '09:00:00',
                    'shift_end_time' => '17:00:00',
                    'grace_period_minutes' => 15,
                    'zkteco_pin' => $pin,
                    'status' => $index === 15 ? 'on_leave' : ($index === 17 ? 'terminated' : 'active'),
                ]
            );

            // هيكل الراتب
            $basic = rand(round($jobTitle->min_salary / 100) * 100, round($jobTitle->max_salary / 100) * 100);
            $housing = rand(5, 12) * 100;
            $transport = rand(3, 8) * 100;

            SalaryStructure::firstOrCreate(
                ['employee_id' => $employee->id, 'is_current' => true],
                [
                    'basic_salary' => $basic,
                    'housing_allowance' => $housing,
                    'transport_allowance' => $transport,
                    'other_allowances' => 0,
                    'effective_from' => $employee->hire_date,
                    'is_current' => true,
                ]
            );

            $allEmployees->push($employee);
        }

        $this->command->info('تم إنشاء ' . $allEmployees->count() . ' موظفاً بهياكل رواتبهم بنجاح.');

        // 7. توليد سجلات الحضور والبصمات (Attendances)
        $activeEmployees = $allEmployees->where('status', 'active');
        $attendanceDaysCount = 18; // آخر 18 يوماً
        $startDate = Carbon::now()->subDays($attendanceDaysCount);

        $attendanceCount = 0;
        for ($i = 0; $i < $attendanceDaysCount; $i++) {
            $currentDate = $startDate->copy()->addDays($i);

            // استبعاد يوم الجمعة كإجازة أسبوعية
            if ($currentDate->isFriday()) {
                continue;
            }

            $dateStr = $currentDate->toDateString();

            foreach ($activeEmployees as $emp) {
                // تجنب التكرار
                if (Attendance::where('employee_id', $emp->id)->whereDate('work_date', $dateStr)->exists()) {
                    continue;
                }

                // محاكاة احتمالية: 80% في الموعد، 10% تأخير، 5% إضافي، 5% غياب
                $rand = rand(1, 100);

                if ($rand <= 80) {
                    // منضبط
                    $checkInMin = rand(45, 59); // بين 08:45 و 08:59
                    Attendance::create([
                        'employee_id' => $emp->id,
                        'work_date' => $dateStr,
                        'check_in' => sprintf('%s 08:%02d:00', $dateStr, $checkInMin),
                        'check_out' => sprintf('%s 17:%02d:00', $dateStr, rand(5, 20)),
                        'late_minutes' => 0,
                        'early_leave_minutes' => 0,
                        'overtime_hours' => 0,
                        'status' => 'present',
                        'source' => 'biometric',
                    ]);
                } elseif ($rand <= 90) {
                    // تأخير
                    $lateMins = rand(20, 50);
                    $checkInHour = 9;
                    $checkInMin = $lateMins;
                    Attendance::create([
                        'employee_id' => $emp->id,
                        'work_date' => $dateStr,
                        'check_in' => sprintf('%s %02d:%02d:00', $dateStr, $checkInHour, $checkInMin),
                        'check_out' => sprintf('%s 17:10:00', $dateStr),
                        'late_minutes' => $lateMins,
                        'early_leave_minutes' => 0,
                        'overtime_hours' => 0,
                        'status' => 'late',
                        'source' => 'biometric',
                    ]);
                } elseif ($rand <= 95) {
                    // عمل إضافي
                    $overtimeHours = rand(1, 3);
                    Attendance::create([
                        'employee_id' => $emp->id,
                        'work_date' => $dateStr,
                        'check_in' => sprintf('%s 08:55:00', $dateStr),
                        'check_out' => sprintf('%s %02d:00:00', $dateStr, 17 + $overtimeHours),
                        'late_minutes' => 0,
                        'early_leave_minutes' => 0,
                        'overtime_hours' => $overtimeHours,
                        'status' => 'present',
                        'source' => 'biometric',
                    ]);
                } else {
                    // غياب
                    Attendance::create([
                        'employee_id' => $emp->id,
                        'work_date' => $dateStr,
                        'check_in' => null,
                        'check_out' => null,
                        'late_minutes' => 0,
                        'early_leave_minutes' => 0,
                        'overtime_hours' => 0,
                        'status' => 'absent',
                        'source' => 'manual',
                    ]);
                }

                $attendanceCount++;
            }
        }

        $this->command->info("تم توليد {$attendanceCount} سجل حضور وانصراف واقعي بنجاح.");

        // 8. توليد جزاءات واقعية (EmployeeDeductions)
        $deductionReasons = [
            'تأخير متكرر عن موعد فتح الورشة واستقبال العملاء',
            'عدم ارتداء مهمات السلامة المهنية أثناء فحص حمض البطارية',
            'إهمال في تنظيف شاحن البطاريات بعد انتهاء الدوام',
            'مغادرة صالة العرض بدون إذن المشرف',
        ];

        $deductionsCreated = 0;
        foreach ($activeEmployees->take(5) as $idx => $emp) {
            $deductionDate = Carbon::now()->subDays(rand(2, 10))->toDateString();
            EmployeeDeduction::firstOrCreate(
                [
                    'employee_id' => $emp->id,
                    'deduction_date' => $deductionDate,
                ],
                [
                    'deduction_rule_id' => $deductionRuleLate->id,
                    'amount' => rand(150, 400),
                    'reason' => $deductionReasons[$idx % count($deductionReasons)],
                    'approved_by' => $admin->id,
                    'status' => 'approved',
                ]
            );
            $deductionsCreated++;
        }

        $this->command->info("تم إنشاء {$deductionsCreated} جزاءات إدارية معتمدة.");

        // 9. طلبات الإجازات (EmployeeLeaves)
        $leaveReasons = [
            'إجازة سنوية اعتيادية لقضاء عطلة عائلية',
            'ظروف صحية طارئة ومراجعة المستشفى',
            'مناسبة عائلية خاصة في البلدة',
            'إجراء فحوصات طبية وتجديد رخصة القيادة',
        ];

        $leavesCreated = 0;
        foreach ($allEmployees->take(6) as $idx => $emp) {
            $startDate = Carbon::now()->addDays(rand(1, 14));
            $days = rand(1, 4);
            $status = $idx < 3 ? 'approved' : ($idx === 3 ? 'rejected' : 'pending');

            EmployeeLeave::firstOrCreate(
                [
                    'employee_id' => $emp->id,
                    'start_date' => $startDate->toDateString(),
                ],
                [
                    'leave_type' => $idx % 2 === 0 ? 'annual' : 'sick',
                    'end_date' => (clone $startDate)->addDays($days - 1)->toDateString(),
                    'days_count' => $days,
                    'reason' => $leaveReasons[$idx % count($leaveReasons)],
                    'status' => $status,
                    'actioned_by' => $status !== 'pending' ? $admin->id : null,
                    'action_notes' => $status === 'approved' ? 'موافقة الإدارة مع تمنياتنا بالتوفيق' : ($status === 'rejected' ? 'اعتذار نظراً لضغط العمل وحاجة الورشة' : null),
                ]
            );
            $leavesCreated++;
        }

        $this->command->info("تم إنشاء {$leavesCreated} طلبات إجازة بمختلف الحالات (معتمدة، معلقة، مرفوضة).");

        // 10. مسير رواتب لكل فرع (Payrolls & PayrollItems)
        $now = Carbon::now();
        $prevMonth = $now->copy()->subMonth();

        foreach ($branches as $branch) {
            // مسير الشهر الماضي كـ Disbursed
            $payrollPrev = Payroll::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'year' => $prevMonth->year,
                    'month' => $prevMonth->month,
                ],
                [
                    'total_basic' => 0,
                    'total_allowances' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                    'status' => 'disbursed',
                    'approved_by' => $admin->id,
                    'disbursed_at' => $prevMonth->copy()->endOfMonth(),
                ]
            );

            // مسير الشهر الحالي كـ Draft
            $payrollCurrent = Payroll::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'year' => $now->year,
                    'month' => $now->month,
                ],
                [
                    'total_basic' => 0,
                    'total_allowances' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                    'status' => 'draft',
                ]
            );

            // توليد بنود الرواتب لموظفي الفرع
            $branchEmployees = $allEmployees->where('branch_id', $branch->id)->where('status', '!=', 'terminated');

            foreach ([$payrollPrev, $payrollCurrent] as $pBatch) {
                $pBatch->items()->delete();
                $bSum = 0; $aSum = 0; $dSum = 0; $nSum = 0;

                foreach ($branchEmployees as $bEmp) {
                    $sal = $bEmp->currentSalary;
                    if (!$sal) continue;

                    $basic = (float) $sal->basic_salary;
                    $allowances = (float) ($sal->housing_allowance + $sal->transport_allowance);
                    $deductions = rand(0, 3) > 1 ? rand(100, 350) : 0;
                    $net = ($basic + $allowances) - $deductions;

                    $pBatch->items()->create([
                        'employee_id' => $bEmp->id,
                        'basic_salary' => $basic,
                        'total_allowance' => $allowances,
                        'total_overtime' => 0,
                        'total_deduction' => $deductions,
                        'net_salary' => $net,
                        'absent_days' => $deductions > 0 ? 1 : 0,
                        'late_minutes_total' => $deductions > 0 ? 30 : 0,
                    ]);

                    $bSum += $basic;
                    $aSum += $allowances;
                    $dSum += $deductions;
                    $nSum += $net;
                }

                $pBatch->update([
                    'total_basic' => $bSum,
                    'total_allowances' => $aSum,
                    'total_deductions' => $dSum,
                    'total_net' => $nSum,
                ]);
            }
        }

        $this->command->info('تم إنشاء مسيرات الرواتب لكافة الفروع (سابق منصرف + حالي مسودة) مع بنودها.');
        $this->command->info('🎉 تم اكتمال تغذية بيانات الـ HR بنجاح تام وبأعلى معايير الواقعية!');
    }
}
