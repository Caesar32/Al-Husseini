<?php

use App\Models\User;
use App\Services\Finance\ManagerOverrideService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// SEC-03: no hard-coded override codes, no password fallback, fail closed.

beforeEach(function () {
    $this->service = app(ManagerOverrideService::class);
});

test('legacy hard-coded codes are rejected even when an override secret is configured', function () {
    config(['finance.manager_override_hash' => Hash::make('real-secret'), 'finance.manager_override_code' => null]);

    expect($this->service->isValid('9999', 'k1'))->toBeFalse()
        ->and($this->service->isValid('mgr_override_99', 'k2'))->toBeFalse();
});

test('overrides are refused when no secret is configured', function () {
    config(['finance.manager_override_hash' => null, 'finance.manager_override_code' => null]);

    expect($this->service->isConfigured())->toBeFalse()
        ->and($this->service->isValid('9999', 'k3'))->toBeFalse()
        ->and($this->service->isValid('mgr_override_99', 'k4'))->toBeFalse();
});

test('a manager account password is not accepted as an override code', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $admin->update(['password' => Hash::make('admin-login-password')]);
    config(['finance.manager_override_hash' => null, 'finance.manager_override_code' => null]);

    expect($this->service->isValid('admin-login-password', 'k5'))->toBeFalse();
});

test('configured hash and configured plain code are accepted', function () {
    config(['finance.manager_override_hash' => Hash::make('hashed-secret'), 'finance.manager_override_code' => 'plain-secret']);

    expect($this->service->isValid('hashed-secret', 'k6'))->toBeTrue()
        ->and($this->service->isValid('plain-secret', 'k7'))->toBeTrue()
        ->and($this->service->isValid('wrong', 'k8'))->toBeFalse();
});

test('attempts are rate limited after five failures', function () {
    config(['finance.manager_override_hash' => Hash::make('hashed-secret'), 'finance.manager_override_code' => null]);

    foreach (range(1, 5) as $i) {
        $this->service->isValid('wrong-' . $i, 'k9');
    }

    expect($this->service->isValid('hashed-secret', 'k9'))->toBeFalse();
});
