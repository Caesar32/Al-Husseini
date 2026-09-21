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
