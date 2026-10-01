<?php

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Models\Branch;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->posService = app(PosOrderServiceInterface::class);
    $this->branch = Branch::first();
    $this->cashier = User::where('email', 'admin@alhusseini.com')->first();
    $this->technician = \App\Models\Employee::first();
    // Non-battery product: no serial / warranty side effects needed for these flows.
    $this->product = Product::where('is_battery', false)->first() ?? Product::first();
    $this->product->update(['current_stock' => 50, 'is_battery' => false]);
});

function p5Sale(array $overrides = []): Invoice
{
    $test = test();

    return $test->posService->processPosSale(array_merge([
        'branch_id'     => $test->branch->id,
        'technician_id' => $test->technician->id,
        'items'         => [['product_id' => $test->product->id, 'quantity' => 2, 'unit_price' => 1500]],
        'payments'      => [['method' => 'cash', 'amount' => 3000]],
    ], $overrides), $test->cashier->id);
}

test('two sequential partial returns cannot exceed the sold quantity', function () {
    $invoice = p5Sale(['items' => [['product_id' => $this->product->id, 'quantity' => 3, 'unit_price' => 1000]], 'payments' => [['method' => 'cash', 'amount' => 3000]]]);
    $line = $invoice->items->first();
    $stockAfterSale = $this->product->fresh()->current_stock;

    $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $line->id, 'quantity' => 2]], 'first', $this->cashier->id);

    // Only 1 unit is left to return; asking for 2 again must fail and change nothing.
    expect(fn () => $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $line->id, 'quantity' => 2]], 'second', $this->cashier->id))
        ->toThrow(DomainException::class);

    expect($line->fresh()->returned_quantity)->toBe(2)
        ->and($this->product->fresh()->current_stock)->toBe($stockAfterSale + 2)
        ->and((float) $invoice->fresh()->refunded_amount)->toBe(2000.0)
        ->and($invoice->fresh()->status)->toBe('partially_refunded');

    $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $line->id, 'quantity' => 1]], 'last unit', $this->cashier->id);

    expect($invoice->fresh()->status)->toBe('refunded')
        ->and((float) $invoice->fresh()->refunded_amount)->toBe(3000.0)
        ->and(fn () => $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $line->id, 'quantity' => 1]], 'again', $this->cashier->id))
        ->toThrow(DomainException::class);
});

test('cumulative refunds are capped at what the customer actually paid', function () {
    // 2 x 1500 with a 600 discount: the customer paid 2400, not 3000.
    $invoice = p5Sale(['discount_amount' => 600, 'payments' => [['method' => 'cash', 'amount' => 2400]]]);
    $line = $invoice->items->first();

    $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $line->id, 'quantity' => 1]], 'one', $this->cashier->id);
    $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $line->id, 'quantity' => 1]], 'two', $this->cashier->id);

    $invoice->refresh();
    $refundPayments = (float) InvoicePayment::where('invoice_id', $invoice->id)->where('amount', '<', 0)->sum('amount');

    expect($invoice->status)->toBe('refunded')
        ->and((float) $invoice->refunded_amount)->toBe(2400.0)
        ->and($refundPayments)->toBe(-2400.0)
        ->and((float) $invoice->paid_amount)->toBe(0.0);
});

test('partially refunded invoices count net of refunds in stats and revenue', function () {
    $kept = p5Sale();
    $partly = p5Sale();
    $this->posService->processSalesReturn($partly->id, [['invoice_item_id' => $partly->items->first()->id, 'quantity' => 1]], 'partial', $this->cashier->id);

    $stats = $this->posService->getInvoiceStats(['search' => null]);
    $expectedNet = (float) Invoice::countable()->get()->sum(fn ($i) => (float) $i->final_amount - (float) $i->refunded_amount);

    expect($partly->fresh()->status)->toBe('partially_refunded')
        ->and((float) $stats['total_sales'])->toBe(round($expectedNet, 2));

    // Payments of the partially refunded invoice stay in revenue; the refund is a negative payment.
    $partlyRevenue = (float) InvoicePayment::active()->cash()->where('invoice_id', $partly->id)->sum('amount');
    expect($partlyRevenue)->toBe(1500.0);
    expect((float) InvoicePayment::active()->cash()->where('invoice_id', $kept->id)->sum('amount'))->toBe(3000.0);
});

test('FIFO settlement allocates to partially refunded invoices and keeps their status', function () {
    $customer = Customer::create(['name' => 'P5 FIFO', 'phone' => '01050000001', 'credit_limit' => 10000, 'current_credit_balance' => 0, 'is_active' => true]);
    $invoice = p5Sale(['customer_id' => $customer->id, 'payments' => [['method' => 'credit', 'amount' => 3000]]]);

    $this->posService->processSalesReturn($invoice->id, [['invoice_item_id' => $invoice->items->first()->id, 'quantity' => 1]], 'partial', $this->cashier->id);
    expect((float) $invoice->fresh()->remaining_amount)->toBe(1500.0)
        ->and((float) $customer->fresh()->current_credit_balance)->toBe(1500.0);

    $this->posService->settleCustomerDebt($customer->id, 1500, 'cash', $this->cashier->id, 'REC-P5-FIFO');

    $invoice->refresh();
    expect((float) $invoice->remaining_amount)->toBe(0.0)
        ->and((float) $invoice->paid_amount)->toBe(1500.0)
        ->and($invoice->status)->toBe('partially_refunded')
        ->and((float) $customer->fresh()->current_credit_balance)->toBe(0.0);
    $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'amount' => 1500, 'transaction_reference' => 'REC-P5-FIFO']);
});

test('settlement beyond invoice-backed debt is recorded against the opening balance explicitly', function () {
    // Opening balance with no invoices (as seeded by SalesAndPosDataSeeder) must stay collectable.
    $customer = Customer::create(['name' => 'P5 Opening', 'phone' => '01050000002', 'credit_limit' => 10000, 'current_credit_balance' => 2000, 'is_active' => true]);

    $entry = $this->posService->settleCustomerDebt($customer->id, 500, 'cash', $this->cashier->id, 'REC-P5-OPEN');

    expect((float) $entry->balance_after)->toBe(1500.0)
        ->and($entry->notes)->toContain('رصيد افتتاحي')
        ->and(InvoicePayment::where('transaction_reference', 'REC-P5-OPEN')->count())->toBe(0);
});

test('invoice numbers are sequential and unique per day', function () {
    $numbers = collect(range(1, 12))->map(fn () => p5Sale(['items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100]], 'payments' => [['method' => 'cash', 'amount' => 100]]])->invoice_number);

    $prefix = 'INV-' . now()->format('Ymd') . '-';
    expect($numbers->unique()->count())->toBe(12)
        ->and($numbers->every(fn ($n) => preg_match('/^' . preg_quote($prefix, '/') . '\d{6}$/', $n) === 1))->toBeTrue();

    $sequence = $numbers->map(fn ($n) => (int) substr($n, -6))->values()->all();
    $sorted = $sequence;
    sort($sorted);
    expect($sequence)->toBe($sorted);
});

test('POS resubmission with the same idempotency key returns the original invoice without side effects', function () {
    $stockBefore = $this->product->fresh()->current_stock;
    $payload = [
        'branch_id'       => $this->branch->id,
        'technician_id'   => $this->technician->id,
        'idempotency_key' => 'checkout-p5-0001',
        'items'           => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => (float) $this->product->retail_price]],
        'payments'        => [['method' => 'cash', 'amount' => (float) $this->product->retail_price]],
    ];

    $first = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), $payload)->assertCreated();
    $second = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), $payload)->assertCreated();

    expect($second->json('invoice_id'))->toBe($first->json('invoice_id'))
        ->and(Invoice::where('idempotency_key', 'checkout-p5-0001')->count())->toBe(1)
        ->and(InvoicePayment::where('invoice_id', $first->json('invoice_id'))->count())->toBe(1)
        ->and($this->product->fresh()->current_stock)->toBe($stockBefore - 1);
});

test('return endpoint accepts invoice line ids, ignores unchecked rows and rejects over-returns', function () {
    $invoice = p5Sale();
    $line = $invoice->items->first();

    // Row 0 checked (has invoice_item_id), row 1 unchecked (quantity only) — the unchecked row must be ignored.
    $this->actingAs($this->cashier)->postJson(route('admin.invoices.return', $invoice), [
        'reason' => 'عيب مصنعي',
        'items'  => [
            ['invoice_item_id' => $line->id, 'quantity' => 1],
            ['quantity' => 5],
        ],
    ])->assertOk()->assertJson(['success' => true]);

    expect($line->fresh()->returned_quantity)->toBe(1);

    $this->actingAs($this->cashier)->postJson(route('admin.invoices.return', $invoice), [
        'reason' => 'مرة أخرى',
        'items'  => [['invoice_item_id' => $line->id, 'quantity' => 2]],
    ])->assertStatus(422);

    expect($line->fresh()->returned_quantity)->toBe(1);
});

test('legacy product_id returns are refused when the product appears on several lines', function () {
    $invoice = p5Sale([
        'items'    => [
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1000],
            ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 1200],
        ],
        'payments' => [['method' => 'cash', 'amount' => 2200]],
    ]);

    expect(fn () => $this->posService->processSalesReturn($invoice->id, [['product_id' => $this->product->id, 'quantity' => 1]], 'ambiguous', $this->cashier->id))
        ->toThrow(DomainException::class);
});

test('return endpoint requires the invoices.cancel permission', function () {
    $invoice = p5Sale();
    $cashier = User::where('email', 'cashier@alhusseini.com')->first();

    $this->actingAs($cashier)->postJson(route('admin.invoices.return', $invoice), [
        'reason' => 'x',
        'items'  => [['invoice_item_id' => $invoice->items->first()->id, 'quantity' => 1]],
    ])->assertForbidden();
});
