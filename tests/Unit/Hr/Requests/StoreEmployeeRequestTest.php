<?php

use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Models\Branch;
use App\Models\JobTitle;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('store employee request validates complete valid payload', function () {
    $branch = Branch::first();
    $jobTitle = JobTitle::first();
    $request = new StoreEmployeeRequest();

    $data = [
        'branch_id' => $branch->id,
        'department' => $jobTitle->department->name,
        'job_title' => $jobTitle->title,
        'employee_code' => 'EMP-REQ-01',
        'full_name' => 'فني كهرباء سيارات',
        'national_id' => '29501011234567',
        'phone' => '01012345678',
        'hire_date' => '2026-09-01',
        'shift_start_time' => '09:00',
        'shift_end_time' => '17:00',
        'grace_period_minutes' => 15,
        'basic_salary' => 6000,
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->passes())->toBeTrue();
});

test('store employee request fails on missing required fields', function () {
    $request = new StoreEmployeeRequest();
    $validator = Validator::make([], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('employee_code'))->toBeTrue()
        ->and($validator->errors()->has('full_name'))->toBeTrue()
        ->and($validator->errors()->has('basic_salary'))->toBeTrue()
        ->and($validator->errors()->has('branch_id'))->toBeTrue();
});

test('store employee request fails when employee code is duplicate', function () {
    $existing = Employee::first();
    $request = new StoreEmployeeRequest();

    $data = [
        'branch_id' => $existing->branch_id,
        'department' => $existing->jobTitle->department->name,
        'job_title' => $existing->jobTitle->title,
        'employee_code' => $existing->employee_code,
        'full_name' => 'موظف مكرر',
        'national_id' => '29501019999999',
        'phone' => '01199999999',
        'hire_date' => '2026-09-01',
        'shift_start_time' => '09:00',
        'shift_end_time' => '17:00',
        'grace_period_minutes' => 15,
        'basic_salary' => 6000,
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('employee_code'))->toBeTrue();
});
