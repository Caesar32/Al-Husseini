<?php

use App\Contracts\Hr\AttendanceServiceInterface;
use App\Contracts\Hr\DeductionServiceInterface;
use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);

    $this->attendanceService = app(AttendanceServiceInterface::class);
    $this->deductionService = app(DeductionServiceInterface::class);
    $this->payrollService = app(PayrollServiceInterface::class);
});

test('net salary never drops below zero even when deductions exceed total earnings', function () {
    $branch = Branch::factory()->create();
    $employee = Employee::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    // الراتب الأساسي: 4000 ج.م
    $employee->salaryStructures()->create([
        'basic_salary' => 4000,
        'housing_allowance' => 0,
        'transport_allowance' => 0,
        'other_allowances' => 0,
        'effective_from' => '2026-09-01',
        'is_current' => true,
    ]);

    // توقيع جزاء ضخم قيمته 8000 ج.م (أكبر من الراتب)
    $this->deductionService->applyDeduction([
        'employee_id' => $employee->id,
        'deduction_date' => '2026-09-15',
        'amount' => 8000,
        'reason' => 'تعويض خسائر حادث سيارة الفرع',
    ]);

    $payroll = $this->payrollService->generateMonthlyPayroll($branch->id, 2026, 9);
    $item = $payroll->items()->where('employee_id', $employee->id)->first();

    expect((float)$item->net_salary)->toBe(0.0)
        ->and((float)$item->total_deduction)->toBeGreaterThan(4000);
});

test('payroll calculation handles short months like february correctly without errors', function () {
    $branch = Branch::factory()->create();
    $employee = Employee::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);

    $employee->salaryStructures()->create([
        'basic_salary' => 7000,
        'housing_allowance' => 500,
        'transport_allowance' => 300,
        'other_allowances' => 0,
        'effective_from' => '2026-02-01',
        'is_current' => true,
    ]);

    // احتساب شهر فبراير 2026 (28 يوماً)
    $payroll = $this->payrollService->generateMonthlyPayroll($branch->id, 2026, 2);

    expect($payroll->items()->count())->toBe(1);
    $item = $payroll->items->first();
    expect((float)$item->basic_salary)->toBe(7000.0);
});

test('duplicate punch in on the same date updates existing record without duplicate rows', function () {
    $employee = Employee::first();
    $date = '2026-09-21';

    // البصمة الأولى الساعة 09:00
    $firstPunch = $this->attendanceService->recordPunch(
        $employee->id,
        Carbon::parse("{$date} 09:00:00"),
        'check_in'
    );

    // بصمة مكررة بعد دقيقتين 09:02
    $secondPunch = $this->attendanceService->recordPunch(
        $employee->id,
        Carbon::parse("{$date} 09:02:00"),
        'check_in'
    );

    expect($firstPunch->id)->toBe($secondPunch->id);
    expect(Attendance::where('employee_id', $employee->id)->whereDate('work_date', $date)->count())->toBe(1);
});

test('terminated employees are ignored when generating branch payroll', function () {
    $branch = Branch::factory()->create();
    $activeEmp = Employee::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'active',
    ]);
    $activeEmp->salaryStructures()->create([
        'basic_salary' => 6000,
        'effective_from' => '2026-09-01',
        'is_current' => true,
    ]);

    $terminatedEmp = Employee::factory()->create([
        'branch_id' => $branch->id,
        'status' => 'terminated',
    ]);
    $terminatedEmp->salaryStructures()->create([
        'basic_salary' => 8000,
        'effective_from' => '2026-09-01',
        'is_current' => true,
    ]);

    $payroll = $this->payrollService->generateMonthlyPayroll($branch->id, 2026, 9);

    expect($payroll->items()->count())->toBe(1);
    expect($payroll->items()->where('employee_id', $activeEmp->id)->exists())->toBeTrue();
    expect($payroll->items()->where('employee_id', $terminatedEmp->id)->exists())->toBeFalse();
});
