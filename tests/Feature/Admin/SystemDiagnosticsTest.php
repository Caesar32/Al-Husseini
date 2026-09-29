<?php

use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);

    $this->superAdmin = User::where('email', 'admin@alhusseini.com')->first();
    $this->regularUser = User::where('email', 'cashier@alhusseini.com')->first();
});

test('super admin can access system diagnostics dashboard', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('admin.diagnostics.index'))
        ->assertOk()
        ->assertViewIs('admin.diagnostics.index')
        ->assertSee('منظومة الفحص والتشخيص والمحاكاة الذاتية')
        ->assertSee('مؤشر سلامة البيانات');
});

test('unauthorized user cannot access system diagnostics page', function () {
    $this->actingAs($this->regularUser)
        ->get(route('admin.diagnostics.index'))
        ->assertForbidden();
});

test('audit endpoint returns accurate json health report', function () {
    $response = $this->actingAs($this->superAdmin)
        ->postJson(route('admin.diagnostics.run_audit'));

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'health_score',
                'passed_count',
                'total_checks',
                'duration_ms',
                'checks',
                'audited_at',
            ],
        ]);

    expect($response->json('data.health_score'))->toBeGreaterThanOrEqual(80);
});

test('simulation endpoint executes end to end workflow and returns 100 percent pass rate', function () {
    $response = $this->actingAs($this->superAdmin)
        ->postJson(route('admin.diagnostics.run_simulation'), [
            'rollback' => true,
        ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'all_passed' => true,
                'is_rolled_back' => true,
            ],
        ]);

    expect($response->json('data.passed_count'))->toBe($response->json('data.total_steps'));
});

test('artisan command system:diagnose executes with zero exit code', function () {
    $this->artisan('system:diagnose')
        ->assertSuccessful();
});
