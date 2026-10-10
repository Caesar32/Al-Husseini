<?php

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierProduct;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->service = app(PurchaseServiceInterface::class);
    $this->user = User::first();
    $this->branch = Branch::first();
    $this->supplier = Supplier::create([
        'name' => 'مورد اختبار الدفتر',
        'company_name' => 'شركة اختبار الدفتر',
        'phone' => '01099990001',
        'credit_limit' => 0,
        'current_balance' => 0,
    ]);
    $this->product = Product::first();

    $this->purchase = function (string $number, float $unitCost, int $qty, float $paid, array $extra = []) {
        return $this->service->createDirectPurchase(array_merge([
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'invoice_number' => $number,
            'invoice_date' => '2026-09-20',
            'items' => [['product_id' => $this->product->id, 'quantity' => $qty, 'unit_cost_price' => $unitCost]],
            'paid_amount' => $paid,
            'payment_method' => 'cash',
        ], $extra), $this->user->id);
    };

    $this->assertLedgerMatchesBalance = function () {
        $supplier = $this->supplier->fresh();
        $entries = SupplierLedgerEntry::where('supplier_id', $supplier->id)->orderBy('id')->get();
        $signed = $entries->sum(fn ($e) => $e->entry_type === 'purchase_invoice' ? (float) $e->amount : -(float) $e->amount);

        expect(round($signed, 2))->toBe(round((float) $supplier->current_balance, 2))
            ->and((float) $entries->last()->balance_after)->toBe((float) $supplier->current_balance);

        $running = 0.0;
        foreach ($entries as $entry) {
            expect((float) $entry->balance_before)->toBe(round($running, 2));
            $running = round($running + ($entry->entry_type === 'purchase_invoice' ? 1 : -1) * (float) $entry->amount, 2);
            expect((float) $entry->balance_after)->toBe($running);
        }
    };
});

test('ledger running balance equals supplier balance after purchases paid 0, partially and fully', function () {
    ($this->purchase)('PUR-LDG-0', 1000, 2, 0);       // 2000 owed
    ($this->purchase)('PUR-LDG-P', 1000, 1, 400);     // 1000 owed, 400 paid
    $full = ($this->purchase)('PUR-LDG-F', 500, 2, 1000); // 1000 owed, 1000 paid

    expect((float) $this->supplier->fresh()->current_balance)->toBe(2600.0);

    $fullEntries = SupplierLedgerEntry::where('purchase_invoice_id', $full->id)->orderBy('id')->get();
    expect($fullEntries->pluck('entry_type')->all())->toBe(['purchase_invoice', 'supplier_payment'])
        ->and((float) $fullEntries[0]->amount)->toBe(1000.0)
        ->and((float) $fullEntries[1]->amount)->toBe(1000.0);

    ($this->assertLedgerMatchesBalance)();
});

test('supplier payment is allocated FIFO to open purchase invoices and updates their status', function () {
    $older = ($this->purchase)('PUR-FIFO-1', 1000, 1, 0, ['invoice_date' => '2026-09-01']);
    $newer = ($this->purchase)('PUR-FIFO-2', 1000, 1, 0, ['invoice_date' => '2026-09-10']);

    $this->service->recordSupplierPayment($this->supplier->id, 1500, 'cash', ['paid_by' => $this->user->id]);

    $older->refresh();
    $newer->refresh();
    expect($older->payment_status)->toBe('paid')
        ->and((float) $older->remaining_amount)->toBe(0.0)
        ->and($newer->payment_status)->toBe('partially_paid')
        ->and((float) $newer->remaining_amount)->toBe(500.0)
        ->and((float) $newer->paid_amount)->toBe(500.0)
        ->and((float) $this->supplier->fresh()->current_balance)->toBe(500.0);

    ($this->assertLedgerMatchesBalance)();
});

test('payment targeted at one invoice is only allocated to that invoice', function () {
    $first = ($this->purchase)('PUR-TGT-1', 1000, 1, 0, ['invoice_date' => '2026-09-01']);
    $second = ($this->purchase)('PUR-TGT-2', 1000, 1, 0, ['invoice_date' => '2026-09-10']);

    $entry = $this->service->recordSupplierPayment($this->supplier->id, 600, 'cash', [
        'paid_by' => $this->user->id,
        'purchase_invoice_id' => $second->id,
    ]);

    expect($entry->purchase_invoice_id)->toBe($second->id)
        ->and((float) $first->fresh()->remaining_amount)->toBe(1000.0)
        ->and((float) $second->fresh()->remaining_amount)->toBe(400.0);
});

test('rejected supplier overpayment returns 422 instead of a server error', function () {
    $this->actingAs($this->user)
        ->postJson(route('admin.suppliers.payments', $this->supplier), [
            'amount' => 5000,
            'payment_method' => 'cash',
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect(SupplierLedgerEntry::where('supplier_id', $this->supplier->id)->count())->toBe(0);
});

test('latest purchasing supplier becomes the only primary supplier of a product', function () {
    $other = Supplier::create(['name' => 'مورد آخر', 'company_name' => 'شركة أخرى', 'phone' => '01099990002', 'credit_limit' => 0]);
    SupplierProduct::where('product_id', $this->product->id)->update(['is_primary_supplier' => false]);
    SupplierProduct::create([
        'supplier_id' => $other->id, 'product_id' => $this->product->id,
        'last_purchase_price' => 900, 'is_primary_supplier' => true, 'supplier_sku' => 'OTHER-SKU',
    ]);

    ($this->purchase)('PUR-PRIM-1', 1000, 1, 1000);

    $primaries = SupplierProduct::where('product_id', $this->product->id)->where('is_primary_supplier', true)->pluck('supplier_id')->all();
    expect($primaries)->toBe([$this->supplier->id]);
});

test('purchase line without a SKU keeps the known supplier SKU', function () {
    ($this->purchase)('PUR-SKU-1', 1000, 1, 1000, ['items' => [[
        'product_id' => $this->product->id, 'quantity' => 1, 'unit_cost_price' => 1000, 'supplier_sku' => 'KNOWN-SKU',
    ]]]);
    ($this->purchase)('PUR-SKU-2', 1000, 1, 1000);

    $pivot = SupplierProduct::where('supplier_id', $this->supplier->id)->where('product_id', $this->product->id)->first();
    expect($pivot->supplier_sku)->toBe('KNOWN-SKU');
});

test('purchase return cannot exceed the remaining returnable quantity and reverses WAC', function () {
    $this->product->update(['current_stock' => 10, 'cost_price' => 1000]);
    $invoice = ($this->purchase)('PUR-RET-1', 1200, 10, 0); // stock 20 @ WAC 1100

    $this->service->processPurchaseReturn($invoice->id, [['product_id' => $this->product->id, 'quantity' => 6]], 'تالف', $this->user->id);

    $this->product->refresh();
    expect($this->product->current_stock)->toBe(14)
        ->and((float) $this->product->cost_price)->toBe(1057.14) // (20*1100 - 6*1200) / 14
        ->and((float) $invoice->fresh()->remaining_amount)->toBe(4800.0);

    expect(fn () => $this->service->processPurchaseReturn($invoice->id, [['product_id' => $this->product->id, 'quantity' => 5]], 'مكرر', $this->user->id))
        ->toThrow(DomainException::class);

    ($this->assertLedgerMatchesBalance)();
});

test('return on a fully paid purchase leaves a supplier credit instead of being clamped', function () {
    $invoice = ($this->purchase)('PUR-RET-2', 1000, 2, 2000);

    $this->service->processPurchaseReturn($invoice->id, [['product_id' => $this->product->id, 'quantity' => 1]], 'مرتجع', $this->user->id);

    expect((float) $this->supplier->fresh()->current_balance)->toBe(-1000.0);
    ($this->assertLedgerMatchesBalance)();
});

test('ledger rebuild dry run reports nothing to repair on ledgers written by the current service', function () {
    ($this->purchase)('PUR-RB-1', 1000, 2, 500);
    ($this->purchase)('PUR-RB-2', 1000, 1, 1000);
    $this->service->recordSupplierPayment($this->supplier->id, 300, 'cash', ['paid_by' => $this->user->id]);

    $this->artisan('suppliers:rebuild-ledger-balances', ['--supplier' => $this->supplier->id])
        ->expectsOutputToContain('Dry run: 0 supplier(s) need repair.')
        ->assertSuccessful();
});

test('ledger rebuild repairs legacy postings only with --apply, writes a backup and is idempotent', function () {
    Storage::fake('local');

    // Legacy shape: 1000 invoice paid 400 on reception posted +600 then -400 (balance kept at 600).
    $invoice = PurchaseInvoice::withoutEvents(fn () => PurchaseInvoice::create([
        'invoice_number' => 'PUR-LEGACY-1', 'supplier_id' => $this->supplier->id, 'branch_id' => $this->branch->id,
        'received_by' => $this->user->id, 'invoice_date' => '2026-09-01', 'subtotal' => 1000, 'final_amount' => 1000,
        'paid_amount' => 400, 'remaining_amount' => 600, 'payment_status' => 'partially_paid',
    ]));
    SupplierLedgerEntry::create(['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $invoice->id, 'entry_type' => 'purchase_invoice',
        'amount' => 600, 'balance_before' => 0, 'balance_after' => 600, 'payment_method' => 'cash', 'paid_by' => $this->user->id]);
    SupplierLedgerEntry::create(['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $invoice->id, 'entry_type' => 'supplier_payment',
        'amount' => 400, 'balance_before' => 600, 'balance_after' => 200, 'payment_method' => 'cash', 'paid_by' => $this->user->id]);
    $this->supplier->update(['current_balance' => 600]);

    $this->artisan('suppliers:rebuild-ledger-balances', ['--supplier' => $this->supplier->id])
        ->expectsOutputToContain('Dry run: 1 supplier(s) need repair.')
        ->assertSuccessful();
    expect((float) SupplierLedgerEntry::where('entry_type', 'purchase_invoice')->where('purchase_invoice_id', $invoice->id)->value('amount'))->toBe(600.0);

    $this->artisan('suppliers:rebuild-ledger-balances', ['--supplier' => $this->supplier->id, '--apply' => true])
        ->expectsOutputToContain('Repaired 1 supplier ledger(s).')
        ->assertSuccessful();

    expect(Storage::disk('local')->files('ledger-backups'))->toHaveCount(1)
        ->and((float) $this->supplier->fresh()->current_balance)->toBe(600.0);
    ($this->assertLedgerMatchesBalance)();

    $this->artisan('suppliers:rebuild-ledger-balances', ['--supplier' => $this->supplier->id])
        ->expectsOutputToContain('Dry run: 0 supplier(s) need repair.')
        ->assertSuccessful();
});
