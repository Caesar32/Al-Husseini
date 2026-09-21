<?php

use App\Http\Requests\Hr\GeneratePayrollRequest;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('generate payroll request validates valid branch and month/year', function () {
    $branch = Branch::first();
    $request = new GeneratePayrollRequest();

    $data = [
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 9,
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->passes())->toBeTrue();
});

test('generate payroll request fails when month is out of range 1-12', function () {
    $branch = Branch::first();
    $request = new GeneratePayrollRequest();

    $data = [
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 13,
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('month'))->toBeTrue();
});

test('generate payroll request fails when branch does not exist', function () {
    $request = new GeneratePayrollRequest();

    $data = [
        'branch_id' => 999999,
        'year' => 2026,
        'month' => 9,
    ];

    $validator = Validator::make($data, $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('branch_id'))->toBeTrue();
});
