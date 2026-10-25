<?php

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Enums\ProductUnit;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->posService = app(PosOrderServiceInterface::class);
    $this->branch = Branch::firstOrFail();
    $this->cashier = User::firstOrFail();
    $this->technician = Employee::firstOrFail();
    $this->category = Category::firstOrCreate(['slug' => 'fractional-test'], ['name' => 'اختبار كسور']);

    $this->liter = Product::create([
        'category_id' => $this->category->id,
        'sku' => 'FRAC-L',
        'name' => 'زيت اختباري',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Liter->value,
        'cost_price' => 80,
        'retail_price' => 120,
        'current_stock' => 10,
        'is_battery' => false,
        'is_active' => true,
    ]);
});

test('POS sells fractional liters through the ledger with an exact line total', function () {
    $invoice = $this->posService->processPosSale([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [['product_id' => $this->liter->id, 'quantity' => '3.5', 'unit_price' => '120.00']],
        'payments' => [['method' => 'cash', 'amount' => '420.00']],
    ], $this->cashier->id);

    $movement = StockMovement::where('product_id', $this->liter->id)->sole();

    expect($this->liter->fresh()->current_stock)->toBe(6.5)
        ->and($invoice->items->first()->quantity)->toBe(3.5)
        ->and((float) $invoice->items->first()->total_price)->toBe(420.0)
        ->and((float) $invoice->subtotal)->toBe(420.0)
        ->and((float) $invoice->final_amount)->toBe(420.0)
        ->and($movement->quantity_delta)->toBe(-3.5)
        ->and($movement->stock_after)->toBe(6.5);
});

test('POS sums repeated fractional lines in milli-units before checking stock', function () {
    $this->liter->update(['current_stock' => 2.5]);

    $invoice = $this->posService->processPosSale([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [
            ['product_id' => $this->liter->id, 'quantity' => '1.25', 'unit_price' => 120],
            ['product_id' => $this->liter->id, 'quantity' => '1.25', 'unit_price' => 120],
        ],
        'payments' => [['method' => 'cash', 'amount' => 300]],
    ], $this->cashier->id);

    expect($this->liter->fresh()->current_stock)->toBe(0)
        ->and($invoice->items)->toHaveCount(2)
        ->and((float) $invoice->subtotal)->toBe(300.0);
});

test('POS rejects non-whole, over-precise, and zero quantities for piece products', function (mixed $quantity) {
    $piece = Product::create([
        'category_id' => $this->category->id,
        'sku' => 'FRAC-P',
        'name' => 'قطعة اختبارية',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Piece->value,
        'cost_price' => 50,
        'retail_price' => 100,
        'current_stock' => 10,
        'is_battery' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), [
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [['product_id' => $piece->id, 'quantity' => $quantity, 'unit_price' => 100]],
        'payments' => [['method' => 'cash', 'amount' => 100]],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
    expect(Invoice::whereHas('items', fn ($query) => $query->where('product_id', $piece->id))->exists())->toBeFalse();
})->with([
    'fractional piece' => 1.5,
    'over-precise quantity' => '2.5555',
    'zero quantity' => 0,
]);

test('returns use milli quantities and lineTotal for fractional liters', function () {
    $invoice = $this->posService->processPosSale([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [['product_id' => $this->liter->id, 'quantity' => '3.5', 'unit_price' => 120]],
        'payments' => [['method' => 'cash', 'amount' => 420]],
    ], $this->cashier->id);
    $line = $invoice->items->first();

    $partial = $this->posService->processSalesReturn(
        $invoice->id,
        [['invoice_item_id' => $line->id, 'quantity' => '1.5']],
        'إرجاع جزئي',
        $this->cashier->id
    );

    expect($partial->status)->toBe('partially_refunded')
        ->and($line->fresh()->returned_quantity)->toBe(1.5)
        ->and($this->liter->fresh()->current_stock)->toBe(8)
        ->and((float) $partial->refunded_amount)->toBe(180.0);

    $complete = $this->posService->processSalesReturn(
        $invoice->id,
        [['invoice_item_id' => $line->id, 'quantity' => 2]],
        'إرجاع كامل',
        $this->cashier->id
    );

    expect($complete->status)->toBe('refunded')
        ->and($line->fresh()->returned_quantity)->toBe(3.5)
        ->and($this->liter->fresh()->current_stock)->toBe(10)
        ->and((float) $complete->refunded_amount)->toBe(420.0);
});

test('fractional return on a piece invoice line is rejected by the request', function () {
    $piece = Product::create([
        'category_id' => $this->category->id,
        'sku' => 'FRAC-RET-P',
        'name' => 'قطعة مرتجع',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Piece->value,
        'cost_price' => 50,
        'retail_price' => 100,
        'current_stock' => 5,
        'is_battery' => false,
        'is_active' => true,
    ]);
    $invoice = $this->posService->processPosSale([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [['product_id' => $piece->id, 'quantity' => 2, 'unit_price' => 100]],
        'payments' => [['method' => 'cash', 'amount' => 200]],
    ], $this->cashier->id);

    $response = $this->actingAs($this->cashier)->postJson(route('admin.invoices.return', $invoice), [
        'reason' => 'مرتجع',
        'items' => [['invoice_item_id' => $invoice->items->first()->id, 'quantity' => '1.5']],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
    expect($piece->fresh()->current_stock)->toBe(3)
        ->and($invoice->items->first()->fresh()->returned_quantity)->toBe(0);
});

test('three fractional returns round independently without exceeding the refundable total', function () {
    $this->liter->update(['current_stock' => 1]);
    $invoice = $this->posService->processPosSale([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [['product_id' => $this->liter->id, 'quantity' => '0.333', 'unit_price' => '10.00']],
        'payments' => [['method' => 'cash', 'amount' => '3.33']],
    ], $this->cashier->id);
    $line = $invoice->items->first();

    foreach (range(1, 3) as $returnNumber) {
        $this->posService->processSalesReturn(
            $invoice->id,
            [['invoice_item_id' => $line->id, 'quantity' => '0.111']],
            "إرجاع {$returnNumber}",
            $this->cashier->id
        );
    }

    $refundPayments = InvoicePayment::where('invoice_id', $invoice->id)->where('amount', '<', 0)->get();

    expect($invoice->fresh()->status)->toBe('refunded')
        ->and($line->fresh()->returned_quantity)->toBe(0.333)
        ->and($refundPayments)->toHaveCount(3)
        ->and($refundPayments->sum(fn ($payment) => (float) $payment->amount))->toBe(-3.33)
        ->and((float) $invoice->fresh()->refunded_amount)->toBe(3.33);
});

test('an over-return names the quantity still returnable', function () {
    $invoice = $this->posService->processPosSale([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [['product_id' => $this->liter->id, 'quantity' => '3.5', 'unit_price' => 120]],
        'payments' => [['method' => 'cash', 'amount' => 420]],
    ], $this->cashier->id);
    $line = $invoice->items->first();
    $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $line->id, 'quantity' => 2]], 'إرجاع جزئي', $this->cashier->id);

    expect(fn () => $this->posService->processSalesReturn(
        $invoice->id,
        [['invoice_item_id' => $line->id, 'quantity' => 2]],
        'إرجاع زائد',
        $this->cashier->id
    ))->toThrow(DomainException::class, 'الكمية المتاحة للإرجاع: 1.5 من أصل 3.5 (سبق إرجاع 2).');
});

test('the cashier summary counts batteries sold net of returns with one SQL sum', function () {
    $battery = Product::create([
        'category_id' => $this->category->id,
        'sku' => 'FRAC-BAT',
        'name' => 'بطارية اختبار',
        'brand' => 'اختبار',
        'cost_price' => 1000,
        'retail_price' => 1500,
        'current_stock' => 5,
        'is_battery' => true,
        'is_active' => true,
    ]);
    $invoice = $this->posService->processPosSale([
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'items' => [
            ['product_id' => $battery->id, 'quantity' => 1, 'unit_price' => 1500, 'battery_serial' => 'SN-FRAC-1'],
            ['product_id' => $battery->id, 'quantity' => 1, 'unit_price' => 1500, 'battery_serial' => 'SN-FRAC-2'],
            ['product_id' => $this->liter->id, 'quantity' => '2.5', 'unit_price' => 120],
        ],
        'payments' => [['method' => 'cash', 'amount' => 3300]],
    ], $this->cashier->id);
    $this->posService->processSalesReturn(
        $invoice->id,
        [['invoice_item_id' => $invoice->items->firstWhere('battery_serial_number', 'SN-FRAC-1')->id, 'quantity' => 1]],
        'إرجاع بطارية',
        $this->cashier->id
    );

    $summary = $this->posService->getDailyCashierSummary($this->branch->id, now()->toDateString());

    expect($summary['batteries_sold_count'])->toBe(1);
});
