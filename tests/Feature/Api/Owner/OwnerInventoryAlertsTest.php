<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->owner = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->token = $this->owner->createToken('Device', ['owner:monitor'])->plainTextToken;
    $this->headers = ['Authorization' => 'Bearer ' . $this->token];

    $this->category = Category::firstOrCreate(['slug' => 'batteries'], ['name' => 'بطاريات']);
});

test('inventory alerts returns products below reorder threshold and avoids divide by zero', function () {
    // 1. Critical out of stock (current_stock = 0, threshold = 5)
    $prod1 = Product::create([
        'category_id'       => $this->category->id,
        'name'              => 'بطارية فارغة',
        'sku'               => 'BAT-ZERO',
        'brand'             => 'Chloride',
        'cost_price'        => 1000,
        'retail_price'      => 1200,
        'current_stock'     => 0,
        'reorder_threshold' => 5,
        'is_battery'        => true,
        'is_active'         => true,
    ]);

    // 2. Normal low stock (current_stock = 3, threshold = 4)
    $prod2 = Product::create([
        'category_id'       => $this->category->id,
        'name'              => 'بطارية منخفضة',
        'sku'               => 'BAT-LOW',
        'brand'             => 'Varta',
        'cost_price'        => 1000,
        'retail_price'      => 1200,
        'current_stock'     => 3,
        'reorder_threshold' => 4,
        'is_battery'        => true,
        'is_active'         => true,
    ]);

    // 3. Healthy stock (current_stock = 20, threshold = 5) - should NOT appear
    $prod3 = Product::create([
        'category_id'       => $this->category->id,
        'name'              => 'بطارية وفيرة',
        'sku'               => 'BAT-HEALTHY',
        'brand'             => 'Bosch',
        'cost_price'        => 1000,
        'retail_price'      => 1200,
        'current_stock'     => 20,
        'reorder_threshold' => 5,
        'is_battery'        => true,
        'is_active'         => true,
    ]);

    // 4. Zero threshold edge case (current_stock = 0, threshold = 0) - NULLIF check test
    $prod4 = Product::create([
        'category_id'       => $this->category->id,
        'name'              => 'صنف حد الطلب صفر',
        'sku'               => 'ITEM-ZERO-THRESH',
        'brand'             => 'Generic',
        'cost_price'        => 100,
        'retail_price'      => 150,
        'current_stock'     => 0,
        'reorder_threshold' => 0,
        'is_battery'        => false,
        'is_active'         => true,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.inventory.alerts'));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    $data = $response->json('data');
    $skus = collect($data)->pluck('sku')->all();

    expect($skus)->toContain('BAT-ZERO')
        ->and($skus)->toContain('BAT-LOW')
        ->and($skus)->toContain('ITEM-ZERO-THRESH')
        ->and($skus)->not->toContain('BAT-HEALTHY');

    // Both zero-stock items should be marked out_of_stock
    $zeroStockItem = collect($data)->firstWhere('sku', 'BAT-ZERO');
    expect($zeroStockItem['status'])->toBe('out_of_stock')
        ->and($zeroStockItem['depletion_ratio'])->toEqual(0.0);
});
