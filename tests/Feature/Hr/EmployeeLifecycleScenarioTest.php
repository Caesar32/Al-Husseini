<?php

use App\Contracts\Hr\AttendanceServiceInterface;
use App\Contracts\Hr\DeductionServiceInterface;
use App\Contracts\Hr\LeaveServiceInterface;
use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);

    $this->attendanceService = app(AttendanceServiceInterface::class);
    $this->deductionService = app(DeductionServiceInterface::class);
    $this->leaveService = app(LeaveServiceInterface::class);
    $this->payrollService = app(PayrollServiceInterface::class);
});

test('end to end monthly lifecycle: hiring, attendance, deductions, leaves, and payroll disbursement', function () {
    // 1. التوظيف وتعيين هيكل الراتب
    $branch = Branch::factory()->create(['name' => 'فرع النزهة الجديد']);
    $jobTitle = JobTitle::factory()->create(['title' => 'فني صيانة بطاريات أول']);

    $employee = Employee::factory()->create([
        'branch_id' => $branch->id,
        'job_title_id' => $jobTitle->id,
        'full_name' => 'محمد كمال الحسيني',
        'shift_start_time' => '09:00:00',
        'shift_end_time' => '17:00:00',
        'grace_period_minutes' => 15,
        'status' => 'active',
    ]);

    $employee->salaryStructures()->create([
        'basic_salary' => 9000,
        'housing_allowance' => 1500,
        'transport_allowance' => 500,
        'other_allowances' => 0,
        'effective_from' => '2026-09-01',
        'is_current' => true,
    ]);

    expect($employee->fresh()->currentSalary)->not->toBeNull()
        ->and((float)$employee->fresh()->currentSalary->basic_salary)->toBe(9000.0);

    // 2. تسجيل حركات البصمة على مدار شهر سبتمبر 2026 (معيار 26 يوم عمل)
    // 20 يوم حضور في الموعد
    for ($d = 1; $d <= 20; $d++) {
        $dayStr = sprintf('2026-09-%02d', $d);
        $this->attendanceService->recordPunch(
            $employee->id,
            Carbon::parse("{$dayStr} 08:55:00"),
            'check_in'
        );
    }

    // يومان تأخير (30 دقيقة و 45 دقيقة)
    $this->attendanceService->recordPunch(
        $employee->id,
        Carbon::parse('2026-09-21 09:30:00'),
        'check_in'
    );
    $this->attendanceService->recordPunch(
        $employee->id,
        Carbon::parse('2026-09-22 09:45:00'),
        'check_in'
    );

    // يومان غياب (أيام 23 و 24 لم يسجل بصمة)

    // 3. توقيع جزاء إداري بقيمة 300 ج.م
    $admin = User::first();
    $deduction = $this->deductionService->applyDeduction([
        'employee_id' => $employee->id,
        'deduction_date' => '2026-09-25',
        'amount' => 300,
        'reason' => 'إهمال فحص كابل شحن البطارية في صالة العرض',
    ], $admin->id);

    expect($deduction->status)->toBe('approved')
        ->and((float)$deduction->amount)->toBe(300.0);

    // 4. طلب إجازة لمدة يومين واعتمادها
    $leave = $this->leaveService->applyForLeave([
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_date' => '2026-09-28',
        'end_date' => '2026-09-29',
        'reason' => 'إجازة سنوية معتمدة',
    ]);
    expect($leave->days_count)->toBe(2);

    $this->leaveService->updateLeaveStatus($leave, 'approved', 'موافقة الفرع', $admin->id);
    expect($leave->fresh()->status)->toBe('approved')
        ->and($employee->fresh()->status)->toBe('on_leave');

    // إعادة الموظف للحالة النشطة لاحتساب رواتب الفرع
    $employee->refresh();
    $employee->update(['status' => 'active']);

    // 5. احتساب مسير الرواتب الآلي لشهر سبتمبر 2026 للفرع
    $payroll = $this->payrollService->generateMonthlyPayroll($branch->id, 2026, 9);

    expect($payroll)->toBeInstanceOf(Payroll::class)
        ->and($payroll->status)->toBe('draft')
        ->and($payroll->items->count())->toBe(1);

    $item = $payroll->items->first();

    // التحقق من الحسابات الرياضية:
    // إجمالي الأساسي: 9,000 ج.م
    // إجمالي البدلات: 1,500 + 500 = 2,000 ج.م
    // أيام الحضور المسجلة: 22 يوم حضور (20 في الموعد + 2 متأخر)
    // أيام الغياب = 26 - 22 = 4 أيام غياب
    // تكلفة الغياب = 4 * (9000 / 30) = 1200 ج.م
    // الجزاء الإداري = 300 ج.م
    // إجمالي الاستقطاعات = 1200 + 300 = 1500 ج.م
    // صافي الراتب المستحق = (9000 + 2000) - 1500 = 9500 ج.م
    expect((float)$item->basic_salary)->toBe(9000.0)
        ->and((float)$item->total_allowance)->toBe(2000.0)
        ->and((float)$item->total_deduction)->toBe(1500.0)
        ->and((float)$item->net_salary)->toBe(9500.0)
        ->and((float)$payroll->total_net)->toBe(9500.0);

    // 6. اعتماد المسير من الإدارة
    $approved = $this->payrollService->approvePayroll($payroll, $admin->id);
    expect($approved)->toBeTrue()
        ->and($payroll->fresh()->status)->toBe('approved');

    // 7. صرف المسير المالي النهائي وإغلاق الشهر
    $disbursed = $this->payrollService->disbursePayroll($payroll);
    expect($disbursed)->toBeTrue()
        ->and($payroll->fresh()->status)->toBe('disbursed')
        ->and($payroll->fresh()->disbursed_at)->not->toBeNull();
});
