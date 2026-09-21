<?php

use App\Models\Payroll;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
});

test('payroll model belongs to branch and has items', function () {
    $branch = Branch::first();
    $admin = User::first();

    $payroll = Payroll::create([
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 9,
        'total_basic' => 50000,
        'total_allowances' => 5000,
        'total_deductions' => 2000,
        'total_net' => 53000,
        'status' => 'approved',
        'approved_by' => $admin->id,
    ]);

    expect($payroll->branch)->toBeInstanceOf(Branch::class)
        ->and($payroll->approvedBy)->toBeInstanceOf(User::class)
        ->and($payroll->items)->toBeIterable();
});
