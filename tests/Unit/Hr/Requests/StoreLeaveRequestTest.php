<?php

use App\Http\Requests\Hr\StoreLeaveRequest;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('store leave request validates valid dates and leave type', function () {
    $emp = Employee::first();
    $request = new StoreLeaveRequest();

    $data = [
        'employee_id' => $emp->id,
        'leave_type' => 'annual',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-05',
        'reason' => 'إجازة سنوية',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->passes())->toBeTrue();
});

test('store leave request fails when end date is before start date', function () {
    $emp = Employee::first();
    $request = new StoreLeaveRequest();

    $data = [
        'employee_id' => $emp->id,
        'leave_type' => 'annual',
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-01',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('end_date'))->toBeTrue();
});

test('store leave request fails when leave type is invalid', function () {
    $emp = Employee::first();
    $request = new StoreLeaveRequest();

    $data = [
        'employee_id' => $emp->id,
        'leave_type' => 'invalid_type',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-02',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('leave_type'))->toBeTrue();
});
