<?php

use App\Http\Requests\Hr\StoreDeductionRequest;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('store deduction request validates correct deduction payload', function () {
    $emp = Employee::first();
    $request = new StoreDeductionRequest();

    $data = [
        'employee_id' => $emp->id,
        'amount' => 150,
        'reason' => 'إهمال فحص كابل الشحن',
        'deduction_date' => '2026-09-21',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->passes())->toBeTrue();
});

test('store deduction request fails with negative or zero amount', function () {
    $emp = Employee::first();
    $request = new StoreDeductionRequest();

    $data = [
        'employee_id' => $emp->id,
        'amount' => 0,
        'reason' => 'خصم بدون مبلغ',
        'deduction_date' => '2026-09-21',
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('amount'))->toBeTrue();
});
