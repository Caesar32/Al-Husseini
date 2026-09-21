<?php

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('attendance belongs to employee and can have related deductions', function () {
    $emp = Employee::first();
    $attendance = Attendance::create([
        'employee_id' => $emp->id,
        'work_date' => '2026-09-21',
        'status' => 'late',
        'late_minutes' => 35,
        'source' => 'manual',
    ]);

    expect($attendance->employee)->toBeInstanceOf(Employee::class)
        ->and($attendance->employee->id)->toBe($emp->id)
        ->and($attendance->deductions)->toBeIterable();
});
