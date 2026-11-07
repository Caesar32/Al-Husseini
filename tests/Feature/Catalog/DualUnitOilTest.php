<?php

use App\Contracts\Catalog\ProductServiceInterface;
use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Oil is sold by the liters poured. A product sold in sealed jerrycans can be converted: its stock
// and prices become per liter, and its old document lines keep their meaning.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);

    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->actingAs($this->admin);

    $this->oil = Product::where('is_battery', false)->whereHas('category', fn ($q) => $q->where('slug', 'oils'))->firstOrFail();
    $this->oil->update([
        'unit' => 'piece', 'package_size' => null, 'current_stock' => 10, 'retail_price' => 975,
        'wholesale_price' => 900, 'cost_price' => 800, 'reorder_threshold' => 2,
    ]);

    $this->customer = Customer::create(['name' => 'عميل زيت', 'phone' => '01099990010', 'tier' => 'standard', 'is_active' => true]);

    $this->sell = fn (float $quantity, float $price, ?Product $product = null) => $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => Branch::first()->id,
        'technician_id' => Employee::first()->id,
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => ($product ?? $this->oil)->id, 'quantity' => $quantity, 'unit_price' => $price]],
        'payments' => [['method' => 'cash', 'amount' => round($quantity * $price, 2)]],
    ]);
});

function converted(Product $product, float $size = 5): Product
{
    return app(ProductServiceInterface::class)->convertToLiters($product, $size);
}

test('converting moves stock to liters through the ledger and prices to per liter', function () {
    $oil = converted($this->oil);
    $movement = StockMovement::where('product_id', $oil->id)->latest('id')->firstOrFail();

    expect($oil->unit)->toBe(ProductUnit::Liter)
        ->and((float) $oil->package_size)->toBe(5.0)
        ->and((float) $oil->current_stock)->toBe(50.0)
        ->and((float) $oil->retail_price)->toBe(195.0)
        ->and((float) $oil->wholesale_price)->toBe(180.0)
        ->and((float) $oil->cost_price)->toBe(160.0)
        ->and((float) $oil->reorder_threshold)->toBe(10.0)
        ->and($movement->type)->toBe(StockMovementType::Adjustment)
        ->and((float) $movement->quantity_delta)->toBe(40.0)
        ->and((float) $movement->stock_after)->toBe(50.0);
});

test('selling poured liters deducts them exactly and charges per liter', function () {
    $oil = converted($this->oil);

    ($this->sell)(3.5, 195, $oil)->assertSuccessful();

    $line = Invoice::latest('id')->firstOrFail()->items()->firstOrFail();
    expect((float) $oil->fresh()->current_stock)->toBe(46.5)
        ->and((float) $line->quantity)->toBe(3.5)
        ->and((float) $line->total_price)->toBe(682.5)
        ->and((float) $line->stock_factor)->toBe(1.0);
});

test('the receipt shows the transparent liter breakdown', function () {
    $oil = converted($this->oil);
    ($this->sell)(3.5, 195, $oil)->assertSuccessful();
    $invoice = Invoice::latest('id')->firstOrFail();

    $this->actingAs($this->admin)->get(route('admin.pos.receipt', $invoice))
        ->assertOk()
        ->assertSee('3.5 لتر × 195.00 ج.م = 682.50 ج.م');
});

test('a jerrycan sold before the conversion still returns as 5 liters, at its old price', function () {
    ($this->sell)(1, 975)->assertSuccessful();
    $invoice = Invoice::latest('id')->firstOrFail();
    $line = $invoice->items()->firstOrFail();
    expect((float) $this->oil->fresh()->current_stock)->toBe(9.0);

    $oil = converted($this->oil->fresh());
    expect((float) $oil->current_stock)->toBe(45.0)
        ->and((float) $line->fresh()->quantity)->toBe(1.0)
        ->and((float) $line->fresh()->total_price)->toBe(975.0)
        ->and((float) $line->fresh()->stock_factor)->toBe(5.0)
        ->and($line->fresh()->quantityUnitLabel())->toBe('جركن');

    app(App\Contracts\Sales\SalesReturnServiceInterface::class)->processSalesReturn($invoice->id, [
        ['product_id' => $oil->id, 'quantity' => 1],
    ], 'مرتجع', $this->cashier->id);

    expect((float) $oil->fresh()->current_stock)->toBe(50.0);
});

test('a purchase from before the conversion returns in liters, with cost per liter', function () {
    $supplier = Supplier::create(['name' => 'مورد زيت', 'company_name' => 'مورد زيت', 'phone' => '01099990011', 'credit_limit' => 0, 'current_balance' => 0, 'is_active' => true]);
    $purchase = PurchaseInvoice::create([
        'invoice_number' => 'PUR-OIL-1', 'supplier_id' => $supplier->id, 'branch_id' => Branch::first()->id,
        'received_by' => $this->admin->id, 'invoice_date' => now()->toDateString(), 'subtotal' => 800,
        'final_amount' => 800, 'paid_amount' => 0, 'remaining_amount' => 800, 'payment_status' => 'unpaid',
    ]);
    DB::table('purchase_invoice_items')->insert([
        'purchase_invoice_id' => $purchase->id, 'product_id' => $this->oil->id, 'quantity' => 1,
        'unit_cost_price' => 800, 'total_cost_price' => 800, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $oil = converted($this->oil);
    app(App\Contracts\Purchases\PurchaseServiceInterface::class)->processPurchaseReturn($purchase->id, [
        ['product_id' => $oil->id, 'quantity' => 1],
    ], 'مرتجع', $this->admin->id);

    $movement = StockMovement::where('product_id', $oil->id)->latest('id')->firstOrFail();
    expect((float) $oil->fresh()->current_stock)->toBe(45.0)
        ->and((float) $movement->quantity_delta)->toBe(-5.0)
        ->and((float) $movement->unit_cost)->toBe(160.0)
        ->and((float) $oil->fresh()->cost_price)->toBe(160.0);
});

test('stock reads as liters and the packages they equal', function () {
    $oil = converted($this->oil);
    DB::table('products')->where('id', $oil->id)->update(['current_stock' => 931.5]);

    expect($oil->fresh()->stockReadout())->toBe('931.5 لتر (ما يعادل 186 جركن و 1.5 لتر)');

    DB::table('products')->where('id', $oil->id)->update(['current_stock' => 10]);
    expect($oil->fresh()->stockReadout())->toBe('10 لتر (ما يعادل 2 جركن)');

    DB::table('products')->where('id', $oil->id)->update(['current_stock' => 3]);
    expect($oil->fresh()->stockReadout())->toBe('3 لتر');

    DB::table('products')->where('id', $oil->id)->update(['package_size' => 208, 'current_stock' => 416]);
    expect($oil->fresh()->stockReadout())->toBe('416 لتر (ما يعادل 2 برميل)');
});

test('a piece product reads as before and cannot be sold in fractions', function () {
    expect($this->oil->stockReadout())->toBe('10 قطعة');

    ($this->sell)(1.5, 975)->assertStatus(422);
});

test('the product form converts when a piece product gets a package size, ignoring its typed prices', function () {
    $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->oil), [
        'category_id' => $this->oil->category_id, 'sku' => $this->oil->sku, 'name' => 'زيت اسم جديد', 'brand' => 'ماركة',
        'retail_price' => 975, 'wholesale_price' => 900, 'warranty_months' => 0,
        'unit' => 'liter', 'package_size' => 5, 'is_battery' => false, 'is_active' => true,
    ])->assertSuccessful();

    $oil = $this->oil->fresh();
    expect($oil->name)->toBe('زيت اسم جديد')
        ->and($oil->unit)->toBe(ProductUnit::Liter)
        ->and((float) $oil->retail_price)->toBe(195.0)
        ->and((float) $oil->current_stock)->toBe(50.0);
});

test('editing a converted product does not convert it again', function () {
    $oil = converted($this->oil);

    $this->actingAs($this->admin)->putJson(route('admin.products.update', $oil), [
        'category_id' => $oil->category_id, 'sku' => $oil->sku, 'name' => $oil->name, 'brand' => 'ماركة',
        'retail_price' => 200, 'wholesale_price' => 180, 'warranty_months' => 0,
        'unit' => 'liter', 'package_size' => 5, 'is_battery' => false, 'is_active' => true,
    ])->assertSuccessful();

    expect((float) $oil->fresh()->retail_price)->toBe(200.0)
        ->and((float) $oil->fresh()->current_stock)->toBe(50.0);
});

test('a package size is dropped when the unit is not liters', function () {
    $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->oil), [
        'category_id' => $this->oil->category_id, 'sku' => $this->oil->sku, 'name' => $this->oil->name, 'brand' => 'ماركة',
        'retail_price' => 975, 'wholesale_price' => 900, 'warranty_months' => 0,
        'unit' => 'piece', 'package_size' => 5, 'is_battery' => false, 'is_active' => true,
    ])->assertSuccessful();

    expect($this->oil->fresh()->package_size)->toBeNull()
        ->and((float) $this->oil->fresh()->current_stock)->toBe(10.0);
});

test('converting refuses a battery, a product already in liters, and a negative stock', function () {
    $battery = Product::where('is_battery', true)->firstOrFail();
    expect(fn () => converted($battery))->toThrow(DomainException::class);

    $oil = converted($this->oil);
    expect(fn () => converted($oil))->toThrow(DomainException::class);

    $other = Product::where('is_battery', false)->where('id', '!=', $oil->id)->firstOrFail();
    DB::table('products')->where('id', $other->id)->update(['current_stock' => -1, 'unit' => 'piece']);
    expect(fn () => converted($other->fresh()))->toThrow(DomainException::class);
});

test('the command is a dry run by default, and never takes a filter from the oils category', function () {
    $oils = $this->oil->category_id;
    DB::table('products')->where('id', $this->oil->id)->update(['name' => 'شيل اصفر 5 لتر']);
    $filter = Product::create(['category_id' => $oils, 'sku' => 'OIL-FILTER', 'name' => 'فلتر زيت 5 لتر', 'brand' => 'b', 'warranty_months' => 0, 'cost_price' => 10, 'retail_price' => 20, 'wholesale_price' => 15, 'current_stock' => 3, 'is_battery' => false, 'is_active' => true]);
    $noSize = Product::create(['category_id' => $oils, 'sku' => 'OIL-NOSIZE', 'name' => 'شيل الترا 5/40', 'brand' => 'b', 'warranty_months' => 0, 'cost_price' => 10, 'retail_price' => 20, 'wholesale_price' => 15, 'current_stock' => 3, 'is_battery' => false, 'is_active' => true]);

    $this->artisan('products:convert-oils-to-liters')->assertSuccessful();
    expect($this->oil->fresh()->unit)->toBe(ProductUnit::Piece)
        ->and((float) $this->oil->fresh()->current_stock)->toBe(10.0);

    $reports = glob(storage_path('app/oil-conversion-*.json'));
    $this->artisan('products:convert-oils-to-liters', ['--apply' => true])->assertSuccessful();

    expect($this->oil->fresh()->unit)->toBe(ProductUnit::Liter)
        ->and((float) $this->oil->fresh()->current_stock)->toBe(50.0)
        ->and($filter->fresh()->unit)->toBe(ProductUnit::Piece)
        ->and($noSize->fresh()->unit)->toBe(ProductUnit::Piece);

    foreach (array_diff(glob(storage_path('app/oil-conversion-*.json')), $reports) as $file) {
        @unlink($file);
    }
});

test('the POS tells the browser the package size', function () {
    converted($this->oil);

    $response = $this->actingAs($this->admin)->getJson(route('admin.pos.products.search', ['q' => $this->oil->sku]));

    expect((float) collect($response->json('data'))->firstWhere('id', $this->oil->id)['package_size'])->toBe(5.0);
});
