<?php

use App\Contracts\Hr\EmployeeServiceInterface;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->service = app(EmployeeServiceInterface::class);
});

test('service creates an employee and associates current salary structure', function () {
    $branch = Branch::first();
    $jobTitle = JobTitle::first();

    $code = 'EMP-UNIT-' . rand(1000, 9999);
    $employee = $this->service->createEmployee([
        'branch_id' => $branch->id,
        'job_title_id' => $jobTitle->id,
        'employee_code' => $code,
        'full_name' => 'مهندس صيانة تجريبي',
        'national_id' => '2990101' . rand(1000000, 9999999),
        'phone' => '010' . rand(10000000, 99999999),
        'hire_date' => '2026-09-01',
        'shift_start_time' => '09:00',
        'shift_end_time' => '17:00',
        'grace_period_minutes' => 15,
        'basic_salary' => 8500,
        'housing_allowance' => 1000,
        'transport_allowance' => 500,
    ]);

    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->employee_code)->toBe($code)
        ->and($employee->status)->toBe('active');

    $salary = $employee->currentSalary;
    expect($salary)->not->toBeNull()
        ->and((float)$salary->basic_salary)->toBe(8500.0)
        ->and((float)$salary->housing_allowance)->toBe(1000.0)
        ->and($salary->is_current)->toBeTrue();
});

test('service updates employee and updates current salary structure', function () {
    $employee = Employee::first();

    $updated = $this->service->updateEmployee($employee, [
        'full_name' => 'اسم معدل للخدمة',
        'basic_salary' => 9500,
        'housing_allowance' => 1200,
    ]);

    expect($updated)->toBeTrue();
    expect($employee->fresh()->full_name)->toBe('اسم معدل للخدمة');
    expect((float)$employee->fresh()->currentSalary->basic_salary)->toBe(9500.0);
    expect((float)$employee->fresh()->currentSalary->housing_allowance)->toBe(1200.0);
});

test('service calculates accurate employee statistics', function () {
    $stats = $this->service->getEmployeeStats();

    expect($stats)->toHaveKeys(['total', 'active', 'on_leave', 'avg_salary'])
        ->and($stats['total'])->toBeGreaterThanOrEqual(1)
        ->and($stats['active'])->toBeGreaterThanOrEqual(1)
        ->and($stats['avg_salary'])->toBeGreaterThan(0);
});

test('service filters paginated employees by branch, status and search', function () {
    $emp = Employee::first();

    // Filter by branch
    $byBranch = $this->service->getPaginatedEmployees(['branch_id' => $emp->branch_id]);
    expect($byBranch->total())->toBeGreaterThanOrEqual(1);

    // Filter by search query
    $bySearch = $this->service->getPaginatedEmployees(['search' => $emp->employee_code]);
    expect($bySearch->total())->toBe(1)
        ->and($bySearch->first()->id)->toBe($emp->id);
});

test('service soft deletes an employee', function () {
    $emp = Employee::first();
    $id = $emp->id;

    $deleted = $this->service->deleteEmployee($emp);
    expect($deleted)->toBeTrue();
    expect(Employee::find($id))->toBeNull();
    expect(Employee::withTrashed()->find($id))->not->toBeNull();
});
