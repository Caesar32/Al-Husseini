<?php

use App\Contracts\Hr\EmployeeServiceInterface;
use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Attendance;
use App\Models\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);

    $this->employeeService = app(EmployeeServiceInterface::class);
    $this->payrollService = app(PayrollServiceInterface::class);
});

test('high volume test: handles 50 employees with 1000 attendance records and calculates branch payroll efficiently', function () {
    $branch = Branch::factory()->create(['name' => 'فرع الضغط العالي']);
    $jobTitle = JobTitle::factory()->create();

    // 1. توليد 50 موظف في الـ RAM
    $employees = Employee::factory()->count(50)->create([
        'branch_id' => $branch->id,
        'job_title_id' => $jobTitle->id,
        'status' => 'active',
    ]);

    expect($employees->count())->toBe(50);

    // 2. إسناد هياكل الرواتب دفعة واحدة
    $salaryData = [];
    foreach ($employees as $emp) {
        $salaryData[] = [
            'employee_id' => $emp->id,
            'basic_salary' => 8000,
            'housing_allowance' => 1000,
            'transport_allowance' => 500,
            'other_allowances' => 0,
            'effective_from' => '2026-09-01',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    SalaryStructure::insert($salaryData);

    // 3. توليد 1000 حركة بصمة (20 يوم حضور لكل موظف)
    $attendances = [];
    foreach ($employees as $emp) {
        for ($day = 1; $day <= 20; $day++) {
            $date = sprintf('2026-09-%02d', $day);
            $attendances[] = [
                'employee_id' => $emp->id,
                'work_date' => $date,
                'check_in' => "{$date} 09:00:00",
                'check_out' => "{$date} 17:00:00",
                'late_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_hours' => 0,
                'status' => 'present',
                'source' => 'biometric',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }
    // إدخال 1000 سجل على دفعات
    foreach (array_chunk($attendances, 250) as $chunk) {
        Attendance::insert($chunk);
    }

    expect(Attendance::whereIn('employee_id', $employees->pluck('id'))->count())->toBe(1000);

    // 4. قياس زمن احتساب المسير لـ 50 موظف مع 1000 بصمة
    $startTime = microtime(true);
    $payroll = $this->payrollService->generateMonthlyPayroll($branch->id, 2026, 9);
    $executionTime = microtime(true) - $startTime;

    // يجب ألا يتجاوز زمن الاحتساب ثانيتين في الذاكرة
    expect($executionTime)->toBeLessThan(3.0);
    expect($payroll->items()->count())->toBe(50);
    expect((float)$payroll->total_basic)->toBe(50 * 8000.0);

    // 5. فحص سرعة ودقة الترقيم للـ 50 موظف
    $paginated = $this->employeeService->getPaginatedEmployees(['branch_id' => $branch->id], 15);
    expect($paginated->total())->toBe(50)
        ->and($paginated->lastPage())->toBe(4);
});
