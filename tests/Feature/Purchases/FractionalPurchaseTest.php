<?php

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Enums\ProductUnit;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->service = app(PurchaseServiceInterface::class);
    $this->branch = Branch::firstOrFail();
    $this->user = User::firstOrFail();
    $this->supplier = Supplier::create([
        'name' => 'مورد الكسور',
        'company_name' => 'شركة مورد الكسور',
        'phone' => '01077770001',
        'credit_limit' => 0,
        'current_balance' => 0,
    ]);
    $category = Category::firstOrCreate(['slug' => 'fractional-purchases'], ['name' => 'اختبار مشتريات كسور']);
    $this->liter = Product::create([
        'category_id' => $category->id,
        'sku' => 'PUR-FRAC-L',
        'name' => 'لتر مشتريات',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Liter->value,
        'cost_price' => 10,
        'retail_price' => 20,
        'current_stock' => 10,
        'is_battery' => false,
        'is_active' => true,
    ]);
    $this->piece = Product::create([
        'category_id' => $category->id,
        'sku' => 'PUR-FRAC-P',
        'name' => 'قطعة مشتريات',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Piece->value,
        'cost_price' => 10,
        'retail_price' => 20,
        'current_stock' => 5,
        'is_battery' => false,
        'is_active' => true,
    ]);
});

function fractionalPurchasePayload(object $test, array $items, float|string $paidAmount): array
{
    return [
        'supplier_id' => $test->supplier->id,
        'branch_id' => $test->branch->id,
        'invoice_number' => 'PUR-FRAC-'.strtoupper(substr(md5(serialize($items).$paidAmount), 0, 8)),
        'invoice_date' => '2026-10-20',
        'items' => $items,
        'paid_amount' => $paidAmount,
        'payment_method' => 'cash',
    ];
}

test('purchases use milli quantities for stock and weighted average cost', function () {
    $invoice = $this->service->createDirectPurchase(fractionalPurchasePayload($this, [[
        'product_id' => $this->liter->id,
        'quantity' => '208.5',
        'unit_cost_price' => '12.25',
    ]], '2554.13'), $this->user->id);

    $expectedWac = round(((10 * 10) + (208.5 * 12.25)) / 218.5, 2);

    expect($this->liter->fresh()->current_stock)->toBe(218.5)
        ->and((float) $this->liter->fresh()->cost_price)->toBe($expectedWac)
        ->and($invoice->items->first()->quantity)->toBe(208.5)
        ->and((float) $invoice->items->first()->total_cost_price)->toBe(2554.13)
        ->and(StockMovement::where('product_id', $this->liter->id)->sole()->quantity_delta)->toBe(208.5);
});

test('purchase returns take fractional availability and value through lineTotal', function () {
    $invoice = $this->service->createDirectPurchase(fractionalPurchasePayload($this, [[
        'product_id' => $this->liter->id,
        'quantity' => '2.5',
        'unit_cost_price' => '12.25',
    ]], 0), $this->user->id);

    $this->service->processPurchaseReturn(
        $invoice->id,
        [['product_id' => $this->liter->id, 'quantity' => '0.5']],
        'مرتجع مورد',
        $this->user->id
    );

    $line = PurchaseInvoiceItem::where('purchase_invoice_id', $invoice->id)->sole();
    $moves = StockMovement::where('product_id', $this->liter->id)->orderBy('id')->get();

    expect($this->liter->fresh()->current_stock)->toBe(12)
        ->and($line->returned_quantity)->toBe(0.5)
        ->and($moves->last()->quantity_delta)->toBe(-0.5)
        ->and($moves->last()->stock_after)->toBe(12)
        ->and((float) $this->supplier->fresh()->current_balance)->toBe(24.5);
});

test('purchasing a fractional quantity for a piece product is rejected', function () {
    expect(fn () => $this->service->createDirectPurchase(
        fractionalPurchasePayload($this, [[
            'product_id' => $this->piece->id,
            'quantity' => '1.5',
            'unit_cost_price' => 20,
        ]], 30),
        $this->user->id
    ))->toThrow(DomainException::class);

    expect($this->piece->fresh()->current_stock)->toBe(5);
});
