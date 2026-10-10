<?php

use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->service = app(PayrollServiceInterface::class);
    $this->admin = User::first();
    auth()->login($this->admin);
});

test('service generates monthly payroll draft with items for active branch employees', function () {
    $branch = Branch::first();
    $year = 2026;
    $month = 9;

    $payroll = $this->service->generateMonthlyPayroll($branch->id, $year, $month);

    expect($payroll)->toBeInstanceOf(Payroll::class)
        ->and($payroll->branch_id)->toBe($branch->id)
        ->and($payroll->status)->toBe('draft')
        ->and($payroll->year)->toBe($year)
        ->and($payroll->month)->toBe($month);

    expect($payroll->items->count())->toBeGreaterThanOrEqual(1);
    expect((float)$payroll->total_basic)->toBeGreaterThan(0);
});

test('service approves a draft payroll batch', function () {
    $branch = Branch::first();
    $payroll = $this->service->generateMonthlyPayroll($branch->id, 2026, 9);
    $admin = User::first();

    $approved = $this->service->approvePayroll($payroll, $admin->id);

    expect($approved)->toBeTrue();
    expect($payroll->fresh()->status)->toBe('approved')
        ->and($payroll->fresh()->approved_by)->toBe($admin->id);
});

test('service disburses an approved payroll batch and records timestamp', function () {
    $branch = Branch::first();
    $payroll = $this->service->generateMonthlyPayroll($branch->id, 2026, 9);
    $this->service->approvePayroll($payroll);

    $disbursed = $this->service->disbursePayroll($payroll);

    expect($disbursed)->toBeTrue();
    expect($payroll->fresh()->status)->toBe('disbursed')
        ->and($payroll->fresh()->disbursed_at)->not->toBeNull();
});

test('service prevents regenerating an already disbursed payroll', function () {
    $branch = Branch::first();
    $payroll = $this->service->generateMonthlyPayroll($branch->id, 2026, 9);
    $this->service->approvePayroll($payroll);
    $this->service->disbursePayroll($payroll);

    expect(fn() => $this->service->generateMonthlyPayroll($branch->id, 2026, 9))
        ->toThrow(Exception::class, 'لا يمكن إعادة احتساب مسير رواتب تم صرفه وإغلاقه بالفعل.');
});

test('service prevents disbursing a draft payroll before approval', function () {
    $branch = Branch::first();
    $payroll = $this->service->generateMonthlyPayroll($branch->id, 2026, 9);

    expect(fn() => $this->service->disbursePayroll($payroll))
        ->toThrow(Exception::class, 'يجب اعتماد مسير الرواتب أولاً قبل الصرف.');
});

test('payroll month window includes attendance and deductions dated the last day of the month', function () {
    // Regression: date-cast columns are stored as 'Y-m-d 00:00:00' on SQLite, so a
    // whereBetween upper bound of 'Y-m-d' silently dropped every last-day-of-month row.
    $branch = Branch::factory()->create();
    $employee = \App\Models\Employee::factory()->create(['branch_id' => $branch->id]);
    \App\Models\SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 6000,
        'housing_allowance' => 500,
        'transport_allowance' => 500,
        'other_allowances' => 0,
        'effective_from' => '2026-01-01',
        'is_current' => true,
    ]);

    // 26 present days in September 2026, the last one on 2026-09-30.
    foreach (range(5, 30) as $day) {
        \App\Models\Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => sprintf('2026-09-%02d', $day),
            'status' => 'present',
        ]);
    }
    \App\Models\EmployeeDeduction::create([
        'employee_id' => $employee->id,
        'deduction_date' => '2026-09-30',
        'amount' => 50,
        'reason' => 'month-end regression',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
    ]);

    $payroll = $this->service->generateMonthlyPayroll($branch->id, 2026, 9);
    $item = $payroll->items()->where('employee_id', $employee->id)->first();

    expect($item)->not->toBeNull()
        ->and($item->absent_days)->toBe(0)
        ->and((float) $item->total_deduction)->toBe(50.0)
        ->and((float) $item->net_salary)->toBe(6950.0);
});

test('service prevents approving payroll without authenticated or specified approver', function () {
    auth()->logout();
    $branch = Branch::first();
    $payroll = $this->service->generateMonthlyPayroll($branch->id, 2026, 9);

    expect(fn() => $this->service->approvePayroll($payroll))
        ->toThrow(Exception::class, 'يجب تحديد المستخدم المعتمِد — لا يمكن اعتماد مسير الرواتب بدون تسجيل المسؤول.');
});
