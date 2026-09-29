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
});

test('admin can view dashboard with sales periods filter', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('إجمالي مبيعات المركز')
        ->assertSee('اليوم')
        ->assertSee('أسبوع')
        ->assertSee('شهر')
        ->assertSee('سنة')
        ->assertSee('الكل')
        ->assertViewHas('salesPeriods')
        ->assertViewHas('selectedPeriodKey');
});

test('admin can request dashboard with specific sales period query', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['sales_period' => 'today']));

    $response->assertOk()
        ->assertViewHas('selectedPeriodKey', 'today');

    $responseMonth = $this->actingAs($admin)->get(route('admin.dashboard', ['sales_period' => 'month']));
    $responseMonth->assertOk()
        ->assertViewHas('selectedPeriodKey', 'month');
});

test('admin can fetch sales period data via ajax json', function () {
    $admin = User::where('email', 'admin@alhusseini.com')->first();

    $response = $this->actingAs($admin)->json('GET', route('admin.dashboard', ['sales_period' => 'today']));

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'selected_period',
            'data' => [
                'key',
                'label',
                'badge',
                'sublabel',
                'total',
                'total_formatted',
                'count',
                'invoices_url',
            ],
            'all_periods',
        ])
        ->assertJson([
            'status' => 'success',
            'selected_period' => 'today',
        ]);
});
