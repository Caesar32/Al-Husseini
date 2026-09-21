<?php

use App\Models\Employee;
use App\Models\Branch;
use App\Models\JobTitle;
use App\Models\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('employee has expected relationships to branch, job title, and salary', function () {
    $emp = Employee::first();

    expect($emp->branch)->toBeInstanceOf(Branch::class)
        ->and($emp->jobTitle)->toBeInstanceOf(JobTitle::class)
        ->and($emp->currentSalary)->toBeInstanceOf(SalaryStructure::class);
});

test('employee active scope filters out non-active employees', function () {
    $emp = Employee::first();
    $emp->update(['status' => 'terminated']);

    $activeEmployees = Employee::active()->get();

    expect($activeEmployees->contains('id', $emp->id))->toBeFalse();
});
