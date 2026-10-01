<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Database\Seeders\ScrapPricingTiersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// FE-01: the POS screen is driven by server data with real database ids.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);
    $this->seed(ScrapPricingTiersSeeder::class);
    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
});

test('pos view embeds real product, customer and scrap tier data from the database', function () {
    $response = $this->actingAs($this->cashier)->get(route('admin.pos.index'))->assertOk();

    $products = $response->viewData('products');
    $customers = $response->viewData('customers');
    $tiers = $response->viewData('scrapTiers');

    $activeProductIds = Product::where('is_active', true)->pluck('id')->sort()->values()->all();
    expect(collect($products)->pluck('id')->sort()->values()->all())->toBe($activeProductIds);

    $customerIds = Customer::where('is_active', true)->where('phone', '!=', '00000000000')->pluck('id')->sort()->values()->all();
    expect(collect($customers)->pluck('id')->sort()->values()->all())->toBe($customerIds);

    expect(collect($tiers))->not->toBeEmpty();

    // Embedded JSON carries the real ids.
    $firstProduct = Product::where('is_active', true)->first();
    $response->assertSee('"id":' . $firstProduct->id, false);
});

test('pos view no longer depends on the browser mock store or invented ids', function () {
    $html = $this->actingAs($this->cashier)->get(route('admin.pos.index'))->assertOk()->getContent();

    // The global layout still loads sales-store.js until Phase 10, but the POS script must not use it.
    $posScript = substr($html, strpos($html, 'const POS_DATA'));
    expect($posScript)->not->toContain('AlHusseiniSales')
        ->and($posScript)->not->toContain('CUST-CASH')
        ->and($posScript)->not->toContain('BAT-${')
        ->and($posScript)->not->toContain('PROD-');
});

test('pos view does not expose cost prices or customer national ids', function () {
    $product = Product::where('is_active', true)->first();
    $product->update(['cost_price' => 1234.56]);
    $customer = Customer::where('phone', '!=', '00000000000')->first();
    $customer?->update(['national_id' => '29901011234567']);

    $response = $this->actingAs($this->cashier)->get(route('admin.pos.index'))->assertOk();

    $response->assertDontSee('cost_price', false)
        ->assertDontSee('1234.56', false)
        ->assertDontSee('national_id', false)
        ->assertDontSee('29901011234567', false);
});

test('the walk-in customer account is not offered as a selectable customer', function () {
    $walkIn = Customer::firstOrCreate(['phone' => '00000000000'], ['name' => 'عميل نقدي عابر', 'credit_limit' => 0]);

    $customers = $this->actingAs($this->cashier)->get(route('admin.pos.index'))->viewData('customers');

    expect(collect($customers)->pluck('id'))->not->toContain($walkIn->id);
});
