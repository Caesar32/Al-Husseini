<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->accountant = User::where('email', 'accountant@alhusseini.com')->firstOrFail();
    $this->branchManager = User::factory()->create(['branch_id' => Branch::first()->id]);
    $this->branchManager->assignRole('branch-manager');

    $this->category = Category::firstOrCreate(['slug' => 'batteries'], ['name' => 'بطاريات السيارات']);
});

function productPayload(array $overrides = []): array
{
    return array_merge([
        'category_id'     => Category::where('slug', 'batteries')->value('id'),
        'sku'             => 'BAT-TEST-70',
        'barcode'         => '6220000000070',
        'name'            => 'بطارية اختبار 70 أمبير',
        'brand'           => 'TestBrand',
        'capacity_ah'     => '70Ah',
        'warranty_months' => 12,
        'retail_price'    => 3200,
        'cost_price'      => 2500,
        'is_battery'      => true,
    ], $overrides);
}

function makeProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'category_id'   => Category::where('slug', 'batteries')->value('id'),
        'sku'           => 'SKU-' . uniqid(),
        'name'          => 'منتج',
        'brand'         => 'B',
        'cost_price'    => 100,
        'retail_price'  => 150,
        'current_stock' => 0,
    ], $overrides));
}

// ─── Products: access ────────────────────────────────────────────────────────────

test('products index renders real catalog rows for users with products.view', function () {
    makeProduct(['name' => 'بطارية حقيقية من القاعدة', 'sku' => 'REAL-1']);

    $this->actingAs($this->cashier)
        ->get(route('admin.sales.products'))
        ->assertOk()
        ->assertSee('بطارية حقيقية من القاعدة')
        ->assertSee('REAL-1')
        ->assertDontSee('AlHusseiniSales');
});

test('cashier cannot create, update or delete products', function () {
    $product = makeProduct();

    $this->actingAs($this->cashier)->postJson(route('admin.products.store'), productPayload())->assertForbidden();
    $this->actingAs($this->cashier)->putJson(route('admin.products.update', $product), productPayload(['sku' => $product->sku]))->assertForbidden();
    $this->actingAs($this->cashier)->deleteJson(route('admin.products.destroy', $product))->assertForbidden();

    expect(Product::count())->toBe(1);
});

test('branch manager can create and edit but not delete products', function () {
    $this->actingAs($this->branchManager)->postJson(route('admin.products.store'), productPayload())->assertCreated();
    $product = Product::where('sku', 'BAT-TEST-70')->firstOrFail();

    $this->actingAs($this->branchManager)
        ->putJson(route('admin.products.update', $product), productPayload(['retail_price' => 3300, 'is_active' => true]))
        ->assertOk();

    $this->actingAs($this->branchManager)->deleteJson(route('admin.products.destroy', $product))->assertForbidden();
    expect((float) $product->fresh()->retail_price)->toBe(3300.0);
});

// ─── Products: business rules ────────────────────────────────────────────────────

test('creating a product always starts with zero stock and records the initial cost', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), productPayload(['current_stock' => 500]))
        ->assertCreated();

    $product = Product::where('sku', 'BAT-TEST-70')->firstOrFail();
    expect($product->current_stock)->toBe(0)
        ->and((float) $product->cost_price)->toBe(2500.0)
        ->and($product->voltage)->toBe('12V')
        ->and($product->terminal_type)->toBe('regular');
});

test('updating a product cannot change stock or weighted average cost', function () {
    $product = makeProduct(['current_stock' => 7, 'cost_price' => 111]);

    $this->actingAs($this->admin)
        ->putJson(route('admin.products.update', $product), productPayload([
            'sku' => $product->sku, 'is_active' => true, 'current_stock' => 999, 'cost_price' => 1,
        ]))
        ->assertOk();

    $product->refresh();
    expect($product->current_stock)->toBe(7)
        ->and((float) $product->cost_price)->toBe(111.0);
});

test('product validation rejects duplicate sku and barcode and missing price', function () {
    makeProduct(['sku' => 'DUP-1', 'barcode' => 'BC-1']);

    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), productPayload(['sku' => 'DUP-1', 'barcode' => 'BC-1', 'retail_price' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sku', 'barcode', 'retail_price']);
});

test('a product with stock cannot be deleted', function () {
    $product = makeProduct(['current_stock' => 3]);

    $this->actingAs($this->admin)->deleteJson(route('admin.products.destroy', $product))->assertStatus(422);
    expect(Product::find($product->id))->not->toBeNull();
});

test('a product referenced by a sale cannot be deleted', function () {
    $product = makeProduct();
    $invoice = Invoice::create([
        'invoice_number' => 'INV-T-1', 'branch_id' => Branch::first()->id, 'cashier_id' => $this->admin->id,
        'subtotal' => 150, 'final_amount' => 150, 'paid_amount' => 150, 'status' => 'paid',
    ]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 150, 'total_price' => 150]);

    $this->actingAs($this->admin)->deleteJson(route('admin.products.destroy', $product))->assertStatus(422);
    expect(Product::find($product->id))->not->toBeNull();
});

test('an unreferenced product without stock can be deleted by super admin', function () {
    $product = makeProduct();

    $this->actingAs($this->admin)->deleteJson(route('admin.products.destroy', $product))->assertOk();
    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

test('product search returns only active products with real fields', function () {
    makeProduct(['name' => 'Varta Blue 70', 'sku' => 'VB70', 'current_stock' => 4, 'is_battery' => true]);
    makeProduct(['name' => 'Varta Old', 'sku' => 'VOLD', 'is_active' => false]);

    $response = $this->actingAs($this->cashier)->getJson(route('admin.products.search', ['q' => 'Varta']))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.sku'))->toBe('VB70')
        ->and($response->json('data.0.current_stock'))->toBe(4)
        ->and($response->json('data.0.category_slug'))->toBe('batteries');
});

// ─── Customers ───────────────────────────────────────────────────────────────────

test('customers index renders real customers', function () {
    Customer::create(['name' => 'عميل حقيقي من القاعدة', 'phone' => '01011112222']);

    $this->actingAs($this->cashier)
        ->get(route('admin.sales.customers'))
        ->assertOk()
        ->assertSee('عميل حقيقي من القاعدة')
        ->assertDontSee('AlHusseiniSales');
});

test('cashier can create a customer with a vehicle but cannot set the credit limit or balance', function () {
    $this->actingAs($this->cashier)->postJson(route('admin.customers.store'), [
        'name' => 'عميل جديد', 'phone' => '01055556666', 'tier' => 'standard', 'credit_limit' => 99999,
    ])->assertStatus(422)->assertJsonValidationErrors(['credit_limit']);

    $this->actingAs($this->cashier)->postJson(route('admin.customers.store'), [
        'name' => 'عميل جديد', 'phone' => '01055556666', 'tier' => 'standard', 'current_credit_balance' => 5000,
        'vehicle' => ['plate_number' => 'ط ب 123', 'car_brand' => 'Toyota', 'car_model' => 'Corolla'],
    ])->assertCreated();

    $customer = Customer::where('phone', '01055556666')->firstOrFail();
    expect((float) $customer->current_credit_balance)->toBe(0.0)
        ->and((float) $customer->credit_limit)->toBe(5000.0)
        ->and($customer->vehicles()->count())->toBe(1);
});

test('cashier cannot edit or delete customers', function () {
    $customer = Customer::create(['name' => 'X', 'phone' => '01000000001']);

    $this->actingAs($this->cashier)->putJson(route('admin.customers.update', $customer), ['name' => 'Y', 'phone' => '01000000001', 'tier' => 'vip'])->assertForbidden();
    $this->actingAs($this->cashier)->deleteJson(route('admin.customers.destroy', $customer))->assertForbidden();
    $this->actingAs($this->cashier)->postJson(route('admin.customers.vehicles.store', $customer), ['plate_number' => 'P1', 'car_brand' => 'A', 'car_model' => 'B'])->assertForbidden();
});

test('credit limit can be changed only with credit.adjust_limit', function () {
    $customer = Customer::create(['name' => 'X', 'phone' => '01000000002']);

    // branch-manager has customers.edit but not credit.adjust_limit
    $this->actingAs($this->branchManager)
        ->putJson(route('admin.customers.update', $customer), ['name' => 'X', 'phone' => '01000000002', 'tier' => 'standard', 'credit_limit' => 20000])
        ->assertStatus(422)->assertJsonValidationErrors(['credit_limit']);

    $this->actingAs($this->admin)
        ->putJson(route('admin.customers.update', $customer), ['name' => 'X', 'phone' => '01000000002', 'tier' => 'standard', 'credit_limit' => 20000])
        ->assertOk();

    expect((float) $customer->fresh()->credit_limit)->toBe(20000.0);
});

test('a customer with an outstanding balance cannot be deleted', function () {
    $customer = Customer::create(['name' => 'مدين', 'phone' => '01000000003']);
    $customer->forceFill(['current_credit_balance' => 750])->save();

    $this->actingAs($this->admin)->deleteJson(route('admin.customers.destroy', $customer))->assertStatus(422);
    expect(Customer::find($customer->id))->not->toBeNull();
});

test('the walk-in customer cannot be deleted or have its phone changed', function () {
    $walkIn = Customer::create(['name' => 'عميل نقدي عابر', 'phone' => '00000000000', 'credit_limit' => 0]);

    $this->actingAs($this->admin)->deleteJson(route('admin.customers.destroy', $walkIn))->assertStatus(422);
    $this->actingAs($this->admin)
        ->putJson(route('admin.customers.update', $walkIn), ['name' => 'عميل نقدي عابر', 'phone' => '01099999999', 'tier' => 'standard'])
        ->assertStatus(422)->assertJsonValidationErrors(['phone']);
});

test('vehicles are managed per customer and plates are unique per customer', function () {
    $customer = Customer::create(['name' => 'X', 'phone' => '01000000004']);
    $other = Customer::create(['name' => 'Z', 'phone' => '01000000005']);
    $otherVehicle = CustomerVehicle::create(['customer_id' => $other->id, 'plate_number' => 'P9', 'car_brand' => 'A', 'car_model' => 'B']);

    $this->actingAs($this->branchManager)
        ->postJson(route('admin.customers.vehicles.store', $customer), ['plate_number' => 'P1', 'car_brand' => 'Kia', 'car_model' => 'Rio'])
        ->assertCreated();
    $this->actingAs($this->branchManager)
        ->postJson(route('admin.customers.vehicles.store', $customer), ['plate_number' => 'P1', 'car_brand' => 'Kia', 'car_model' => 'Rio'])
        ->assertStatus(422)->assertJsonValidationErrors(['plate_number']);

    // A vehicle of another customer cannot be reached through this customer's URL (scoped bindings).
    $this->actingAs($this->branchManager)
        ->putJson(route('admin.customers.vehicles.update', [$customer, $otherVehicle]), ['plate_number' => 'P9', 'car_brand' => 'A', 'car_model' => 'B'])
        ->assertNotFound();
});

test('customer search excludes the walk-in account and returns vehicles', function () {
    Customer::create(['name' => 'عميل نقدي عابر', 'phone' => '00000000000']);
    $customer = Customer::create(['name' => 'Ahmed Search', 'phone' => '01000000006']);
    CustomerVehicle::create(['customer_id' => $customer->id, 'plate_number' => 'S1', 'car_brand' => 'A', 'car_model' => 'B']);

    $all = $this->actingAs($this->cashier)->getJson(route('admin.customers.search'))->assertOk()->json('data');
    $found = $this->actingAs($this->cashier)->getJson(route('admin.customers.search', ['q' => 'S1']))->assertOk()->json('data');

    expect(collect($all)->pluck('phone'))->not->toContain('00000000000')
        ->and($found)->toHaveCount(1)
        ->and($found[0]['vehicles'][0]['plate_number'])->toBe('S1');
});

test('customer profile endpoint returns server data and requires customers.view', function () {
    $customer = Customer::create(['name' => 'Profile', 'phone' => '01000000007']);

    $this->actingAs($this->admin)->getJson(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertJsonPath('customer.id', $customer->id);

    $noViewer = User::factory()->create();
    $noViewer->assignRole('workshop-supervisor'); // has no customers.view
    $this->actingAs($noViewer)->getJson(route('admin.customers.show', $customer))->assertForbidden();
});
