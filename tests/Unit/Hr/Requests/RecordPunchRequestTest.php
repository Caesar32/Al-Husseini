<?php

use App\Http\Requests\Hr\RecordPunchRequest;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('record punch request validates valid check in payload', function () {
    $emp = Employee::first();
    $request = new RecordPunchRequest();

    $data = [
        'employee_id' => $emp->id,
        'timestamp' => '2026-09-21 09:00:00',
        'punch_state' => 'check_in',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->passes())->toBeTrue();
});

test('record punch request fails when both employee_id and pin are missing', function () {
    $request = new RecordPunchRequest();

    $data = [
        'timestamp' => '2026-09-21 09:00:00',
        'punch_state' => 'check_in',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('employee_id'))->toBeTrue()
        ->and($validator->errors()->has('pin'))->toBeTrue();
});

test('record punch request fails when timestamp is missing or invalid', function () {
    $emp = Employee::first();
    $request = new RecordPunchRequest();

    $data = [
        'employee_id' => $emp->id,
        'punch_state' => 'check_in',
        'timestamp' => 'not-a-valid-date',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('timestamp'))->toBeTrue();
});
