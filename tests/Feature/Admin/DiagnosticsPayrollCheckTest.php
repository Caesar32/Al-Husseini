<?php

use App\Models\Payroll;
use App\Models\User;
use App\Services\Diagnostics\SystemDiagnosticService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);
    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail());

    $this->diagnostics = app(SystemDiagnosticService::class);
    $this->diagnostics->runLiveSimulation(false); // creates and disburses a real payroll batch
});

function payrollCheck(array $audit): array
{
    return collect($audit['checks'])->firstWhere('key', 'payroll_math_integrity');
}

test('a payroll produced by the real flow passes the shared consistency check', function () {
    expect(Payroll::count())->toBeGreaterThan(0)
        ->and(payrollCheck($this->diagnostics->runFullAudit())['status'])->toBe('passed');
});

test('an item net that no longer matches its components is reported even when the header total matches', function () {
    $payroll = Payroll::with('items')->firstOrFail();
    $item = $payroll->items->first();

    // Header and item sum stay equal (the old check only compared these two).
    $item->update(['net_salary' => (float) $item->net_salary + 100]);
    $payroll->update(['total_net' => (float) $payroll->total_net + 100]);

    $check = payrollCheck($this->diagnostics->runFullAudit());

    expect($check['status'])->toBe('failed')
        ->and($check['count'])->toBe(1);
});
