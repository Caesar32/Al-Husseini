<?php

use App\Contracts\Sales\ScrapBatteryServiceInterface;
use App\Contracts\Sales\WarrantyServiceInterface;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ScrapBatteriesInventory;
use App\Models\ScrapSale;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->warranties = app(WarrantyServiceInterface::class);
    $this->user = User::first();
    $this->branch = Branch::first();
    $this->technician = Employee::first();
    $this->soldProduct = Product::where('is_battery', true)->first();
    $this->replacementProduct = Product::where('is_battery', true)->where('id', '!=', $this->soldProduct->id)->first();
    $this->replacementProduct->update(['current_stock' => 10, 'cost_price' => 1500]);
    $this->customer = Customer::create(['name' => 'عميل ضمان', 'phone' => '01055500011']);

    $this->makeWarranty = function (string $serial, string $status = 'active') {
        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'invoice_number' => 'INV-W-' . $serial, 'branch_id' => $this->branch->id, 'customer_id' => $this->customer->id,
            'cashier_id' => $this->user->id, 'subtotal' => 3000, 'final_amount' => 3000, 'paid_amount' => 3000, 'status' => 'paid',
        ]));
        $item = $invoice->items()->create([
            'product_id' => $this->soldProduct->id, 'quantity' => 1, 'unit_price' => 3000, 'total_price' => 3000,
            'battery_serial_number' => $serial, 'warranty_duration_months' => 12,
        ]);

        return Warranty::create([
            'invoice_item_id' => $item->id, 'customer_id' => $this->customer->id, 'serial_number' => $serial,
            'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(11)->toDateString(), 'status' => $status,
        ]);
    };

    $this->claimData = fn (string $serial, string $decision = 'replaced', array $extra = []) => array_merge([
        'defective_serial' => $serial,
        'branch_id' => $this->branch->id,
        'technician_id' => $this->technician->id,
        'battery_voltage_tested' => 10.1,
        'issue_description' => 'هبوط في الجهد تحت الحمل',
        'decision' => $decision,
    ], $decision === 'replaced' ? [
        'replacement_product_id' => $this->replacementProduct->id,
        'replacement_battery_serial' => 'REP-' . $serial,
    ] : [], $extra);
});

test('claim on an already claimed warranty is rejected by the service and the request', function () {
    ($this->makeWarranty)('W-CLAIMED-1');
    $this->warranties->processInstantClaim(($this->claimData)('W-CLAIMED-1'), $this->user->id);

    expect(fn () => $this->warranties->processInstantClaim(($this->claimData)('W-CLAIMED-1', 'recharged'), $this->user->id))
        ->toThrow(DomainException::class);

    $this->actingAs($this->user)
        ->postJson(route('admin.warranties.claims.store'), ($this->claimData)('W-CLAIMED-1', 'recharged'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('defective_serial');

    expect(WarrantyClaim::where('defective_battery_serial', 'W-CLAIMED-1')->count())->toBe(1);
});

test('rejection reason is persisted on rejected claims', function () {
    ($this->makeWarranty)('W-REJECT-1');

    $claim = $this->warranties->processInstantClaim(
        ($this->claimData)('W-REJECT-1', 'rejected', ['rejection_reason' => 'سوء استخدام: كسر في الغطاء']),
        $this->user->id
    );

    expect($claim->fresh()->rejection_reason)->toBe('سوء استخدام: كسر في الغطاء');
});

test('claim supplier is the primary supplier of the battery that was sold, not of the replacement', function () {
    $soldSupplier = Supplier::create(['name' => 'مورد المباع', 'company_name' => 'شركة المباع', 'phone' => '01055500021']);
    $replacementSupplier = Supplier::create(['name' => 'مورد البديل', 'company_name' => 'شركة البديل', 'phone' => '01055500022']);
    SupplierProduct::whereIn('product_id', [$this->soldProduct->id, $this->replacementProduct->id])->update(['is_primary_supplier' => false]);
    SupplierProduct::create(['supplier_id' => $soldSupplier->id, 'product_id' => $this->soldProduct->id, 'last_purchase_price' => 1000, 'is_primary_supplier' => true]);
    SupplierProduct::create(['supplier_id' => $replacementSupplier->id, 'product_id' => $this->replacementProduct->id, 'last_purchase_price' => 1000, 'is_primary_supplier' => true]);

    ($this->makeWarranty)('W-SUP-1');
    $claim = $this->warranties->processInstantClaim(($this->claimData)('W-SUP-1'), $this->user->id);

    expect($claim->supplier_id)->toBe($soldSupplier->id);
});

test('supplier settlement cannot be applied twice', function () {
    $supplier = Supplier::create(['name' => 'مورد', 'company_name' => 'شركة', 'phone' => '01055500031', 'current_balance' => 5000]);
    ($this->makeWarranty)('W-SETTLE-1');
    $claim = $this->warranties->processInstantClaim(($this->claimData)('W-SETTLE-1'), $this->user->id);
    $claim->update(['supplier_id' => $supplier->id]);

    $this->warranties->settleClaimWithSupplier($claim->id, 'settled_credit_note', ['credit_amount' => 1200, 'notes' => 'إشعار رقم 77'], $this->user->id);

    expect(fn () => $this->warranties->settleClaimWithSupplier($claim->id, 'settled_credit_note', ['credit_amount' => 1200], $this->user->id))
        ->toThrow(DomainException::class);
    expect(fn () => $this->warranties->settleClaimWithSupplier($claim->id, 'settled_replacement', [], $this->user->id))
        ->toThrow(DomainException::class);

    expect((float) $supplier->fresh()->current_balance)->toBe(3800.0)
        ->and(SupplierLedgerEntry::where('supplier_id', $supplier->id)->where('entry_type', 'adjustment')->count())->toBe(1)
        ->and($claim->fresh()->settlement_notes)->toBe('إشعار رقم 77');

    $this->actingAs($this->user)
        ->postJson(route('admin.warranties.claims.settle', $claim), ['action' => 'settled_credit_note', 'credit_amount' => 1200])
        ->assertStatus(422);
});

test('replacement settlement requires a replaced claim and adds stock once', function () {
    ($this->makeWarranty)('W-REPL-1');
    $recharged = $this->warranties->processInstantClaim(($this->claimData)('W-REPL-1', 'recharged'), $this->user->id);

    expect(fn () => $this->warranties->settleClaimWithSupplier($recharged->id, 'settled_replacement', [], $this->user->id))
        ->toThrow(DomainException::class);

    ($this->makeWarranty)('W-REPL-2');
    $replaced = $this->warranties->processInstantClaim(($this->claimData)('W-REPL-2'), $this->user->id);
    $stockAfterClaim = $this->replacementProduct->fresh()->current_stock;

    $this->warranties->settleClaimWithSupplier($replaced->id, 'sent_to_supplier', [], $this->user->id);
    $this->warranties->settleClaimWithSupplier($replaced->id, 'settled_replacement', [], $this->user->id);

    expect($this->replacementProduct->fresh()->current_stock)->toBe($stockAfterClaim + 1);
});

test('credit note without a supplier is refused instead of silently settling', function () {
    ($this->makeWarranty)('W-NOSUP-1');
    $claim = $this->warranties->processInstantClaim(($this->claimData)('W-NOSUP-1', 'recharged'), $this->user->id);

    expect(fn () => $this->warranties->settleClaimWithSupplier($claim->id, 'settled_credit_note', ['credit_amount' => 500], $this->user->id))
        ->toThrow(DomainException::class);
    expect($claim->fresh()->supplier_resolution)->toBe('pending');
});

test('claim numbers are unique and sequential within a month', function () {
    $numbers = [];
    foreach (range(1, 6) as $i) {
        ($this->makeWarranty)("W-SEQ-{$i}");
        $numbers[] = $this->warranties->processInstantClaim(($this->claimData)("W-SEQ-{$i}", 'recharged'), $this->user->id)->claim_number;
    }

    $prefix = 'CLM-' . now()->format('Ym') . '-';
    expect(array_unique($numbers))->toHaveCount(6)
        ->and($numbers)->toBe(array_map(fn ($n) => $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT), range(1, 6)));
});

test('the original sale warranty stays the invoice line warranty after a replacement', function () {
    $original = ($this->makeWarranty)('W-ORIG-1');
    $this->warranties->processInstantClaim(($this->claimData)('W-ORIG-1'), $this->user->id);

    $item = $original->invoiceItem()->first();
    expect($item->warranty->serial_number)->toBe('W-ORIG-1')
        ->and($item->warranties)->toHaveCount(2)
        ->and($item->activeWarranty->serial_number)->toBe('REP-W-ORIG-1');
});

test('scrap batch sale is persisted with buyer, amounts and gross profit', function () {
    $a = ScrapBatteriesInventory::create(['branch_id' => $this->branch->id, 'capacity_ah' => '70Ah', 'scrap_value' => 800, 'lead_weight_kg' => 11.9, 'status' => 'in_stock', 'received_by' => $this->technician->id]);
    $b = ScrapBatteriesInventory::create(['branch_id' => $this->branch->id, 'capacity_ah' => '70Ah', 'scrap_value' => 700, 'lead_weight_kg' => 11.9, 'status' => 'in_stock', 'received_by' => $this->technician->id]);

    $result = app(ScrapBatteryServiceInterface::class)->dispatchScrapSaleBatch([
        'scrap_battery_ids' => [$a->id, $b->id],
        'buyer_name' => 'مصنع الرصاص',
        'buyer_phone' => '01055500041',
        'payment_method' => 'bank_transfer',
        'total_amount' => 2000,
        'notes' => 'شحنة أكتوبر',
    ], $this->user->id);

    $sale = ScrapSale::findOrFail($result['scrap_sale_id']);
    expect($sale->batch_number)->toBe($result['batch_number'])
        ->and($sale->batch_number)->toStartWith('SCRAP-BATCH-' . now()->format('Ymd') . '-')
        ->and((float) $sale->total_amount)->toBe(2000.0)
        ->and((float) $sale->cost_value)->toBe(1500.0)
        ->and((float) $sale->gross_profit)->toBe(500.0)
        ->and($sale->payment_method)->toBe('bank_transfer')
        ->and($sale->branch_id)->toBe($this->branch->id)
        ->and($sale->sold_by)->toBe($this->user->id)
        ->and($a->fresh()->scrap_sale_id)->toBe($sale->id)
        ->and($b->fresh()->status)->toBe('sold_to_factory');
});

test('scrap, warranty and supplier ledger pages render after the integrity changes', function () {
    $supplier = Supplier::create(['name' => 'مورد', 'company_name' => 'شركة', 'phone' => '01055500051']);
    ($this->makeWarranty)('W-PAGE-1');
    $this->warranties->processInstantClaim(($this->claimData)('W-PAGE-1', 'rejected', ['rejection_reason' => 'سبب']), $this->user->id);

    $this->actingAs($this->user)->get(route('admin.scrap.index'))->assertOk();
    $this->actingAs($this->user)->get(route('admin.warranties.index'))->assertOk()->assertSee('name="rejection_reason"', false);
    $this->actingAs($this->user)->get(route('admin.warranties.verify', ['view' => 1]))->assertOk()->assertSee('name="rejection_reason"', false);
    $this->actingAs($this->user)->get(route('admin.suppliers.ledger', $supplier))->assertOk()->assertSee('تسويات وإشعارات خصم');
});

test('scrap inventory list follows the same branch scope as its metrics', function () {
    $other = Branch::create(['name' => 'فرع آخر', 'code' => 'OTHER']);
    ScrapBatteriesInventory::create(['branch_id' => $this->branch->id, 'capacity_ah' => '70Ah', 'scrap_value' => 800, 'status' => 'in_stock', 'received_by' => $this->technician->id]);
    ScrapBatteriesInventory::create(['branch_id' => $other->id, 'capacity_ah' => '70Ah', 'scrap_value' => 800, 'status' => 'in_stock', 'received_by' => $this->technician->id]);

    $response = $this->actingAs($this->user)
        ->getJson(route('admin.scrap.index', ['branch_id' => $other->id]))
        ->assertOk();

    $branchIds = collect($response->json('inventory.data'))->pluck('branch_id')->unique()->values()->all();
    expect($branchIds)->toBe([$other->id])
        ->and($response->json('metrics.total_units'))->toBe(1);
});
