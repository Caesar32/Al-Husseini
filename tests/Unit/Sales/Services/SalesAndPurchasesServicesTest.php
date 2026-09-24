<?php

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Contracts\Purchases\SupplierServiceInterface;
use App\Contracts\Sales\PosOrderServiceInterface;
use App\Contracts\Sales\ScrapBatteryServiceInterface;
use App\Contracts\Sales\WarrantyServiceInterface;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\ScrapBatteriesInventory;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Models\Warranty;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('service container resolves all sales and purchases interfaces via SOLID provider', function () {
    expect(app(SupplierServiceInterface::class))->toBeInstanceOf(\App\Services\Purchases\SupplierService::class)
        ->and(app(PurchaseServiceInterface::class))->toBeInstanceOf(\App\Services\Purchases\PurchaseService::class)
        ->and(app(PosOrderServiceInterface::class))->toBeInstanceOf(\App\Services\Sales\PosOrderService::class)
        ->and(app(WarrantyServiceInterface::class))->toBeInstanceOf(\App\Services\Sales\WarrantyService::class)
        ->and(app(ScrapBatteryServiceInterface::class))->toBeInstanceOf(\App\Services\Sales\ScrapBatteryService::class);
});

test('supplier service manages suppliers and multi-supplier product catalog', function () {
    $supplierService = app(SupplierServiceInterface::class);

    $supplier = $supplierService->createSupplier([
        'name'         => 'محي الدين البشبيشي',
        'company_name' => 'شركة الدلتا للتوزيع',
        'phone'        => '01011223344',
        'credit_limit' => 200000,
    ]);

    expect($supplier->id)->not->toBeNull()
        ->and((float) $supplier->current_balance)->toBe(0.0);

    $product = Product::first();
    $supplierService->syncSupplierProducts($supplier->id, [
        [
            'product_id'          => $product->id,
            'supplier_sku'        => 'DLT-BAT-70',
            'last_purchase_price' => 1850.00,
            'is_primary_supplier' => true,
        ],
    ]);

    $supplier->load('products');
    expect($supplier->products)->toHaveCount(1)
        ->and($supplier->products->first()->pivot->supplier_sku)->toBe('DLT-BAT-70')
        ->and((float) $supplier->products->first()->pivot->last_purchase_price)->toBe(1850.00);
});

test('purchase service processes purchase with exact weighted average cost WAC formula and updates ledger', function () {
    $purchaseService = app(PurchaseServiceInterface::class);
    $supplier = Supplier::first();
    $user = User::first();
    $product = Product::first();

    // Set initial product stock and cost: 10 units @ 1000 EGP = 10,000 EGP
    $product->update([
        'current_stock' => 10,
        'cost_price'    => 1000.00,
    ]);

    // Receive new shipment: 10 units @ 1200 EGP = 12,000 EGP
    // Total stock will be 20, Total value = 22,000 EGP -> Expected WAC = 1100.00 EGP
    $invoice = $purchaseService->createDirectPurchase([
        'supplier_id'     => $supplier->id,
        'invoice_number'  => 'PUR-WAC-TEST-01',
        'invoice_date'    => '2026-09-23',
        'items'           => [
            [
                'product_id'      => $product->id,
                'quantity'        => 10,
                'unit_cost_price' => 1200.00,
                'supplier_sku'    => 'SKU-WAC-01',
            ],
        ],
        'tax_amount'      => 0,
        'discount_amount' => 0,
        'paid_amount'     => 5000.00, // 5000 paid, 7000 remaining on credit
        'payment_method'  => 'cash',
    ], $user->id);

    $product->refresh();
    expect($product->current_stock)->toBe(20)
        ->and((float) $product->cost_price)->toBe(1100.00);

    $supplier->refresh();
    // Supplier current balance should have increased by remaining_amount (7000)
    expect((float) $invoice->remaining_amount)->toBe(7000.00)
        ->and((float) $supplier->current_balance)->toBe(7000.00);

    // Verify supplier ledger entries
    $statement = $purchaseService->getSupplierLedgerStatement($supplier->id);
    expect($statement['entries']->count())->toBeGreaterThanOrEqual(1);

    // Test supplier payment settlement
    $paymentEntry = $purchaseService->recordSupplierPayment($supplier->id, 3000.00, 'cash', [
        'notes' => 'سداد جزء من المديونية',
    ]);

    $supplier->refresh();
    expect((float) $supplier->current_balance)->toBe(4000.00)
        ->and((float) $paymentEntry->balance_after)->toBe(4000.00);
});

test('pos order service processes sale with scrap trade-in, stock decrement, warranty and technician commission', function () {
    $posService = app(PosOrderServiceInterface::class);
    $branch = Branch::first();
    $cashier = User::first();
    $technician = Employee::first();

    $customer = Customer::create([
        'name'                   => 'أحمد كمال المحجوب',
        'phone'                  => '01022334455',
        'credit_limit'           => 10000,
        'current_credit_balance' => 0,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    $battery = Product::where('is_battery', true)->first();
    $battery->update(['current_stock' => 15, 'retail_price' => 3000.00]);

    // Customer buys 1 battery (3000 EGP), trades in 70Ah scrap (-800 EGP).
    // Net to pay = 2200 EGP. Paid via split payment: 1200 Cash + 1000 Card.
    $invoice = $posService->processPosSale([
        'branch_id'             => $branch->id,
        'customer_id'           => $customer->id,
        'technician_id'         => $technician->id,
        'items'                 => [
            [
                'product_id'     => $battery->id,
                'quantity'       => 1,
                'unit_price'     => 3000.00,
                'battery_serial' => 'SN-POS-TEST-888',
            ],
        ],
        'has_scrap'             => true,
        'scrap_capacity_ah'     => 70,
        'scrap_count'           => 1,
        'payments'              => [
            ['method' => 'cash', 'amount' => 1200.00],
            ['method' => 'card', 'amount' => 1000.00, 'reference' => 'TXN-9988'],
        ],
    ], $cashier->id);

    // 1. Verify invoice totals
    expect((float) $invoice->subtotal)->toBe(3000.00)
        ->and((float) $invoice->scrap_deduction_amount)->toBe(800.00)
        ->and((float) $invoice->final_amount)->toBe(2200.00)
        ->and($invoice->payment_method)->toBe('split');

    // 2. Verify stock decremented
    $battery->refresh();
    expect($battery->current_stock)->toBe(14);

    // 3. Verify electronic warranty created
    $warranty = Warranty::where('serial_number', 'SN-POS-TEST-888')->first();
    expect($warranty)->not->toBeNull()
        ->and($warranty->status)->toBe('active')
        ->and($warranty->customer_id)->toBe($customer->id);

    // 4. Verify scrap deposited in inventory
    $scrap = ScrapBatteriesInventory::where('invoice_id', $invoice->id)->first();
    expect($scrap)->not->toBeNull()
        ->and($scrap->capacity_ah)->toBe('70Ah')
        ->and((float) $scrap->scrap_value)->toBe(800.00)
        ->and($scrap->status)->toBe('in_stock');

    // 5. Verify technician commission created (25 EGP)
    $commission = \App\Models\TechnicianCommission::where('invoice_id', $invoice->id)->first();
    expect($commission)->not->toBeNull()
        ->and($commission->employee_id)->toBe($technician->id)
        ->and((float) $commission->commission_amount)->toBe(25.00);
});

test('warranty service verifies serial, processes instant replacement and settles with supplier', function () {
    $warrantyService = app(WarrantyServiceInterface::class);
    $user = User::first();
    $technician = Employee::first();
    $customer = Customer::first() ?? Customer::create([
        'name' => 'عميل ضمان', 'phone' => '01033445566', 'tier' => 'standard', 'credit_limit' => 5000, 'current_credit_balance' => 0, 'is_active' => true,
    ]);

    $product = Product::where('is_battery', true)->first();
    $product->update(['current_stock' => 5]);
    // Issue initial warranty with invoice item
    $branch = Branch::first();
    $invoice = \App\Models\Invoice::withoutEvents(function () use ($branch, $customer, $user) {
        return \App\Models\Invoice::create([
            'invoice_number' => 'INV-WARR-TEST-01',
            'branch_id'      => $branch->id,
            'customer_id'    => $customer->id,
            'cashier_id'     => $user->id,
            'subtotal'       => 3000,
            'final_amount'   => 3000,
            'paid_amount'    => 3000,
        ]);
    });

    $item = \App\Models\InvoiceItem::create([
        'invoice_id'              => $invoice->id,
        'product_id'              => $product->id,
        'quantity'                => 1,
        'unit_price'              => 3000,
        'total_price'             => 3000,
        'battery_serial_number'   => 'DEFECT-SERIAL-777',
        'warranty_duration_months'=> 12,
    ]);

    $initialWarranty = Warranty::create([
        'invoice_item_id' => $item->id,
        'customer_id'     => $customer->id,
        'serial_number'   => 'DEFECT-SERIAL-777',
        'start_date'      => now()->subMonths(3)->toDateString(),
        'end_date'        => now()->addMonths(9)->toDateString(),
        'status'          => 'active',
    ]);

    // 1. Verify Serial
    $verification = $warrantyService->verifyBatterySerial('DEFECT-SERIAL-777');
    expect($verification['is_valid'])->toBeTrue()
        ->and($verification['days_remaining'])->toBeGreaterThan(0);

    // 2. Process instant claim (replacement from stock)
    $claim = $warrantyService->processInstantClaim([
        'defective_serial'           => 'DEFECT-SERIAL-777',
        'technician_id'              => $technician->id,
        'battery_voltage_tested'     => 10.4,
        'cca_tested'                 => 160,
        'issue_description'          => 'فقدان سعة الشحن وهبوط الجهد تحت الحمل',
        'decision'                   => 'replaced',
        'replacement_product_id'     => $product->id,
        'replacement_battery_serial' => 'NEW-REPLACEMENT-SN-777',
    ], $user->id);

    expect($claim->id)->not->toBeNull()
        ->and($claim->claim_number)->toStartWith('CLM-')
        ->and($claim->decision)->toBe('replaced');

    // Stock for product should have decremented from 5 to 4
    $product->refresh();
    expect($product->current_stock)->toBe(4);

    // New replacement warranty should be active
    $newWarranty = Warranty::where('serial_number', 'NEW-REPLACEMENT-SN-777')->first();
    expect($newWarranty)->not->toBeNull()
        ->and($newWarranty->status)->toBe('active');

    // Defective warranty should be claimed
    $initialWarranty->refresh();
    expect($initialWarranty->status)->toBe('claimed');

    // 3. Settle claim with supplier via credit note
    $supplier = Supplier::first();
    $supplier->update(['current_balance' => 5000]);
    $claim->update(['supplier_id' => $supplier->id]);

    $settledClaim = $warrantyService->settleClaimWithSupplier($claim->id, 'settled_credit_note', [
        'credit_amount' => 1500.00,
    ], $user->id);

    expect($settledClaim->supplier_resolution)->toBe('settled_credit_note');
    $supplier->refresh();
    expect((float) $supplier->current_balance)->toBe(3500.00);
});

test('scrap battery service reports metrics and dispatches bulk sales', function () {
    $scrapService = app(ScrapBatteryServiceInterface::class);
    $branch = Branch::first();
    $user = User::first();
    $employee = Employee::first();

    // Create 3 scrap batteries in stock
    $b1 = ScrapBatteriesInventory::create([
        'branch_id'      => $branch->id,
        'capacity_ah'    => '70Ah',
        'scrap_value'    => 800,
        'lead_weight_kg' => 11.9,
        'status'         => 'in_stock',
        'received_by'    => $employee->id,
    ]);
    $b2 = ScrapBatteriesInventory::create([
        'branch_id'      => $branch->id,
        'capacity_ah'    => '70Ah',
        'scrap_value'    => 800,
        'lead_weight_kg' => 11.9,
        'status'         => 'in_stock',
        'received_by'    => $employee->id,
    ]);
    $b3 = ScrapBatteriesInventory::create([
        'branch_id'      => $branch->id,
        'capacity_ah'    => '50Ah',
        'scrap_value'    => 600,
        'lead_weight_kg' => 8.5,
        'status'         => 'in_stock',
        'received_by'    => $employee->id,
    ]);

    $metrics = $scrapService->getInventoryMetrics($branch->id);
    expect($metrics['total_units'])->toBe(3)
        ->and((float) $metrics['total_scrap_value'])->toBe(2200.00)
        ->and((float) $metrics['total_lead_weight_kg'])->toBe(32.3);

    // Dispatch bulk sale of 2 batteries to factory
    $dispatch = $scrapService->dispatchScrapSaleBatch([
        'scrap_battery_ids' => [$b1->id, $b2->id],
        'buyer_name'        => 'مصنع الدلتا لتدوير الرصاص',
        'total_amount'      => 1900.00, // Cost was 1600, sale 1900 -> profit 300
    ], $user->id);

    expect($dispatch['batch_number'])->toStartWith('SCRAP-BATCH-')
        ->and($dispatch['batteries_count'])->toBe(2)
        ->and((float) $dispatch['gross_profit'])->toBe(300.00);

    $b1->refresh();
    expect($b1->status)->toBe('sold_to_factory');
});
