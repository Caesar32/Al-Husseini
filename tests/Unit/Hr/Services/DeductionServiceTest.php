<?php

use App\Contracts\Hr\DeductionServiceInterface;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->service = app(DeductionServiceInterface::class);
});

test('service applies deduction with approved status', function () {
    $emp = Employee::first();
    $admin = User::first();

    $ded = $this->service->applyDeduction([
        'employee_id' => $emp->id,
        'deduction_date' => '2026-09-21',
        'amount' => 450,
        'reason' => 'إتلاف جهاز فحص شحن البطارية بسبب الإهمال',
    ], $admin->id);

    expect($ded)->toBeInstanceOf(EmployeeDeduction::class)
        ->and((float)$ded->amount)->toBe(450.0)
        ->and($ded->status)->toBe('approved')
        ->and($ded->approved_by)->toBe($admin->id);
});

test('service updates deduction status to cancelled', function () {
    $emp = Employee::first();
    $ded = $this->service->applyDeduction([
        'employee_id' => $emp->id,
        'deduction_date' => '2026-09-21',
        'amount' => 300,
        'reason' => 'خصم مؤقت للمراجعة',
    ]);

    $updated = $this->service->updateDeductionStatus($ded, 'cancelled');

    expect($updated)->toBeTrue();
    expect($ded->fresh()->status)->toBe('cancelled');
});

test('service returns paginated deductions', function () {
    $emp = Employee::first();
    $this->service->applyDeduction([
        'employee_id' => $emp->id,
        'deduction_date' => '2026-09-21',
        'amount' => 200,
        'reason' => 'تأخير',
    ]);

    $paginated = $this->service->getPaginatedDeductions(10);
    expect($paginated->total())->toBeGreaterThanOrEqual(1);
});
