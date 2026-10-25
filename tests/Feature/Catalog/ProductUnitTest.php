<?php

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->category = Category::firstOrCreate(['slug' => 'oils'], ['name' => 'زيوت']);
});

function fractionalProductPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => Category::where('slug', 'oils')->value('id'),
        'sku' => 'UNIT-' . uniqid(),
        'name' => 'منتج وحدات كسرية',
        'brand' => 'TestBrand',
        'warranty_months' => 0,
        'retail_price' => 120,
        'cost_price' => 100,
        'is_battery' => false,
    ], $overrides);
}

test('creating a product defaults to pieces and stores a selected fractional unit', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload(['sku' => 'UNIT-PIECE']))
        ->assertCreated();

    $piece = Product::where('sku', 'UNIT-PIECE')->firstOrFail();

    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload(['sku' => 'UNIT-LITER', 'unit' => 'liter']))
        ->assertCreated();

    expect($piece->unit?->value)->toBe('piece')
        ->and(Product::where('sku', 'UNIT-LITER')->firstOrFail()->unit?->value)->toBe('liter');
});

test('rejects fractional units for batteries and accepts pieces', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload([
            'sku' => 'BAT-LITER',
            'is_battery' => true,
            'unit' => 'liter',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['unit']);

    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload([
            'sku' => 'BAT-PIECE',
            'is_battery' => true,
            'unit' => 'piece',
        ]))
        ->assertCreated();
});

test('rejects an invalid product unit', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload(['unit' => 'box']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['unit']);
});

test('accepts three decimal reorder thresholds and rejects excess precision', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload([
            'sku' => 'UNIT-THRESHOLD-OK',
            'unit' => 'liter',
            'reorder_threshold' => '2.5',
        ]))
        ->assertCreated();

    expect(Product::where('sku', 'UNIT-THRESHOLD-OK')->value('reorder_threshold'))->toBe(2.5);

    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload([
            'sku' => 'UNIT-THRESHOLD-BAD',
            'unit' => 'liter',
            'reorder_threshold' => '2.5555',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reorder_threshold']);
});

test('quantity casts round-trip fractional and whole values across catalog records', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'sku' => 'CAST-PRODUCT',
        'name' => 'منتج اختبار التحويل',
        'brand' => 'TestBrand',
        'unit' => 'liter',
        'cost_price' => 100,
        'retail_price' => 120,
        'current_stock' => 3.5,
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-CAST-1',
        'branch_id' => Branch::firstOrFail()->id,
        'cashier_id' => $this->admin->id,
        'subtotal' => 12,
        'final_amount' => 12,
        'paid_amount' => 12,
        'status' => 'paid',
    ]);
    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => '0.125',
        'unit_price' => 10,
        'total_price' => 1.25,
    ]);

    $supplier = Supplier::create([
        'name' => 'مورد اختبار',
        'company_name' => 'شركة اختبار',
        'phone' => '01000000123',
    ]);
    $purchaseInvoice = PurchaseInvoice::create([
        'invoice_number' => 'PUR-CAST-1',
        'supplier_id' => $supplier->id,
        'branch_id' => Branch::firstOrFail()->id,
        'received_by' => $this->admin->id,
        'invoice_date' => now()->toDateString(),
        'subtotal' => 10,
        'final_amount' => 10,
    ]);
    $purchaseItem = PurchaseInvoiceItem::create([
        'purchase_invoice_id' => $purchaseInvoice->id,
        'product_id' => $product->id,
        'quantity' => '3.5',
        'unit_cost_price' => 10,
        'total_cost_price' => 35,
    ]);

    $movement = StockMovement::create([
        'product_id' => $product->id,
        'branch_id' => Branch::firstOrFail()->id,
        'type' => StockMovementType::Adjustment,
        'quantity_delta' => '0.125',
        'stock_after' => 3.5,
        'source_type' => User::class,
        'source_id' => $this->admin->id,
        'user_id' => $this->admin->id,
    ]);

    expect($product->fresh()->current_stock)->toBe(3.5)
        ->and($invoiceItem->fresh()->quantity)->toBe(0.125)
        ->and($purchaseItem->fresh()->quantity)->toBe(3.5)
        ->and($movement->fresh()->quantity_delta)->toBe(0.125)
        ->and($product->fresh()->reorder_threshold)->toBe(5)
        ->and($product->fresh()->current_stock)->not->toBe(4);

    $product->current_stock = 4;
    $product->save();
    $invoiceItem->quantity = 4;
    $invoiceItem->save();

    expect($product->fresh()->current_stock)->toBe(4)
        ->and($invoiceItem->fresh()->quantity)->toBe(4);
});

test('a product holding less than one unit of stock cannot be deleted', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'sku' => 'UNIT-HALF-LITER',
        'name' => 'زيت سائب',
        'brand' => 'TestBrand',
        'unit' => 'liter',
        'cost_price' => 100,
        'retail_price' => 120,
        'current_stock' => 0.5,
    ]);

    $this->actingAs($this->admin)->deleteJson(route('admin.products.destroy', $product))->assertStatus(422);
    expect(Product::find($product->id))->not->toBeNull();
});

test('marking a product as a battery without sending a unit resets it to pieces', function () {
    $this->actingAs($this->admin)
        ->postJson(route('admin.products.store'), fractionalProductPayload(['sku' => 'UNIT-TO-BATTERY', 'unit' => 'liter']))
        ->assertCreated();
    $product = Product::where('sku', 'UNIT-TO-BATTERY')->firstOrFail();

    $this->actingAs($this->admin)
        ->putJson(route('admin.products.update', $product), fractionalProductPayload(['sku' => 'UNIT-TO-BATTERY', 'is_battery' => true, 'is_active' => true]))
        ->assertOk();

    expect($product->fresh()->unit?->value)->toBe('piece');
});

function literProduct(string $sku, int|float|string $stock): Product
{
    return Product::create([
        'category_id' => Category::where('slug', 'oils')->value('id'),
        'sku' => $sku,
        'name' => 'زيت ' . $sku,
        'brand' => 'TestBrand',
        'unit' => 'liter',
        'cost_price' => 100,
        'retail_price' => 120,
        'current_stock' => $stock,
        'is_battery' => false,
    ]);
}

function sellOnInvoice(Product $product, string $number, string $quantity, string $returned = '0'): void
{
    $invoice = Invoice::create([
        'invoice_number' => $number,
        'branch_id' => Branch::firstOrFail()->id,
        'cashier_id' => User::firstOrFail()->id,
        'subtotal' => 10,
        'final_amount' => 10,
        'paid_amount' => 10,
        'status' => 'paid',
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'returned_quantity' => $returned,
        'unit_price' => 10,
        'total_price' => 10,
    ]);
}

test('a product with fractional stock cannot be switched to pieces', function () {
    $product = literProduct('UNIT-GUARD-STOCK', '2.5');

    $this->actingAs($this->admin)
        ->putJson(route('admin.products.update', $product), fractionalProductPayload(['sku' => 'UNIT-GUARD-STOCK', 'unit' => 'piece', 'is_active' => true]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['unit']);

    expect($product->fresh()->unit?->value)->toBe('liter');
});

test('a product with fractional sale or return lines cannot be switched to pieces, even as a battery', function (string $quantity, string $returned) {
    $product = literProduct('UNIT-GUARD-LINE', 4);
    sellOnInvoice($product, 'INV-GUARD-1', $quantity, $returned);

    $this->actingAs($this->admin)
        ->putJson(route('admin.products.update', $product), fractionalProductPayload(['sku' => 'UNIT-GUARD-LINE', 'unit' => 'piece', 'is_active' => true]))
        ->assertStatus(422);
    $this->actingAs($this->admin)
        ->putJson(route('admin.products.update', $product), fractionalProductPayload(['sku' => 'UNIT-GUARD-LINE', 'is_battery' => true, 'is_active' => true]))
        ->assertStatus(422);

    expect($product->fresh()->unit?->value)->toBe('liter')
        ->and($product->fresh()->is_battery)->toBeFalse();
})->with([
    'fractional sold quantity' => ['0.5', '0'],
    'fractional returned quantity' => ['2', '0.5'],
]);

test('a product with fractional purchase lines cannot be switched to pieces', function () {
    $product = literProduct('UNIT-GUARD-PUR', 3);
    $supplier = Supplier::create(['name' => 'مورد', 'company_name' => 'شركة', 'phone' => '01000000456']);
    $purchase = PurchaseInvoice::create([
        'invoice_number' => 'PUR-GUARD-1',
        'supplier_id' => $supplier->id,
        'branch_id' => Branch::firstOrFail()->id,
        'received_by' => $this->admin->id,
        'invoice_date' => now()->toDateString(),
        'subtotal' => 10,
        'final_amount' => 10,
    ]);
    PurchaseInvoiceItem::create([
        'purchase_invoice_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => '3.5',
        'unit_cost_price' => 10,
        'total_cost_price' => 35,
    ]);

    $this->actingAs($this->admin)
        ->putJson(route('admin.products.update', $product), fractionalProductPayload(['sku' => 'UNIT-GUARD-PUR', 'unit' => 'piece', 'is_active' => true]))
        ->assertStatus(422);

    expect($product->fresh()->unit?->value)->toBe('liter');
});

test('a product with only whole stock and lines can be switched to pieces', function () {
    $product = literProduct('UNIT-GUARD-OK', 4);
    sellOnInvoice($product, 'INV-GUARD-2', '2', '1');

    $this->actingAs($this->admin)
        ->putJson(route('admin.products.update', $product), fractionalProductPayload(['sku' => 'UNIT-GUARD-OK', 'unit' => 'piece', 'is_active' => true]))
        ->assertOk();

    expect($product->fresh()->unit?->value)->toBe('piece');
});
