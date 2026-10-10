<?php

use App\Http\Requests\Admin\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Admin\Purchases\StorePurchaseInvoiceRequest;
use App\Http\Requests\Admin\Pos\StorePosInvoiceRequest;
use App\Http\Requests\Admin\Warranties\ProcessWarrantyClaimRequest;
use App\Http\Requests\Admin\Scrap\StoreScrapSaleBatchRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ScrapBatteriesInventory;
use App\Models\ScrapPricingTier;
use App\Models\Supplier;
use App\Models\Warranty;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('store supplier request validates valid data and catches duplicate phone', function () {
    $supplier = Supplier::first();
    $request = new StoreSupplierRequest();

    $validData = [
        'name'         => 'مورد جديد للتجربة',
        'company_name' => 'شركة النور للبطاريات',
        'phone'        => '01099887766',
        'credit_limit' => 50000,
    ];

    $validator = Validator::make($validData, $request->rules());
    expect($validator->passes())->toBeTrue();

    // Duplicate phone test
    $duplicateData = $validData;
    $duplicateData['phone'] = $supplier->phone;

    $validatorDuplicate = Validator::make($duplicateData, $request->rules());
    expect($validatorDuplicate->fails())->toBeTrue()
        ->and($validatorDuplicate->errors()->has('phone'))->toBeTrue();
});

test('store purchase invoice request verifies paid amount does not exceed final amount', function () {
    $supplier = Supplier::first();
    $product = Product::first();

    $request = new StorePurchaseInvoiceRequest();
    $request->merge([
        'supplier_id'             => $supplier->id,
        'invoice_number'          => 'PUR-TEST-999',
        'invoice_date'            => '2026-09-23',
        'items'                   => [
            [
                'product_id'      => $product->id,
                'quantity'        => 5,
                'unit_cost_price' => 2000,
            ],
        ],
        'tax_amount'              => 0,
        'discount_amount'         => 0,
        'paid_amount'             => 15000, // Exceeds 5 * 2000 = 10000
        'payment_method'          => 'cash',
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('paid_amount'))->toBeTrue();
});

test('store pos invoice request enforces battery serial for battery products and rejects duplicates', function () {
    $battery = Product::where('is_battery', true)->first();

    $request = new StorePosInvoiceRequest();
    $request->merge([
        'branch_id' => Branch::first()->id,
        'technician_id' => \App\Models\Employee::first()->id,
        'items' => [
            [
                'product_id' => $battery->id,
                'quantity'   => 1,
                'battery_serial' => '', // Missing serial!
            ],
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => $battery->retail_price],
        ],
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('items.0.battery_serial'))->toBeTrue();
});

test('store pos invoice request rejects payments sum mismatch with invoice total', function () {
    $battery = Product::where('is_battery', true)->first();

    $request = new StorePosInvoiceRequest();
    $request->merge([
        'branch_id' => Branch::first()->id,
        'technician_id' => \App\Models\Employee::first()->id,
        'items' => [
            [
                'product_id'     => $battery->id,
                'quantity'       => 1,
                'unit_price'     => 3000,
                'battery_serial' => 'SN-UNIQUE-991',
            ],
        ],
        'discount_amount' => 0,
        'tax_amount'      => 0,
        'payments'        => [
            ['method' => 'cash', 'amount' => 2500], // Mismatch: 2500 != 3000
        ],
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('payments'))->toBeTrue();
});

test('store pos invoice request calculates scrap deduction and accepts split payment matching net', function () {
    $battery = Product::where('is_battery', true)->first();
    // Ensure sufficient stock for test
    $battery->update(['current_stock' => 10]);

    // 70Ah scrap tier is 800 EGP in ScrapPricingTiersSeeder
    $tier = ScrapPricingTier::findPriceForCapacity(70);
    $scrapValue = (float) $tier->default_scrap_price; // 800

    $retail = (float) $battery->retail_price;
    $netExpected = max(0, $retail - $scrapValue);

    $cashPart = round($netExpected / 2, 2);
    $cardPart = $netExpected - $cashPart;

    $request = new StorePosInvoiceRequest();
    $request->merge([
        'branch_id' => Branch::first()->id,
        'items' => [
            [
                'product_id'     => $battery->id,
                'quantity'       => 1,
                'unit_price'     => $retail,
                'battery_serial' => 'SN-SPLIT-992',
            ],
        ],
        'technician_id'     => \App\Models\Employee::first()->id,
        'has_scrap'         => true,
        'scrap_capacity_ah' => 70,
        'scrap_count'       => 1,
        'payments'          => [
            ['method' => 'cash', 'amount' => $cashPart],
            ['method' => 'card', 'amount' => $cardPart, 'reference' => 'TXN-VISA-01'],
        ],
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->passes())->toBeTrue();
});

test('store pos invoice request rejects invoice when technician is missing', function () {
    $battery = Product::where('is_battery', true)->first();
    $request = new StorePosInvoiceRequest();
    $request->merge([
        'branch_id' => Branch::first()->id,
        'items' => [
            [
                'product_id'     => $battery->id,
                'quantity'       => 1,
                'unit_price'     => 3000,
                'battery_serial' => 'SN-TECH-REQ-99',
            ],
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => 3000],
        ],
        // technician_id is missing
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('technician_id'))->toBeTrue();
});

test('store pos invoice request requires manager override code when credit limit exceeded', function () {
    $battery = Product::where('is_battery', true)->first();
    $battery->update(['current_stock' => 10]);

    $customer = Customer::create([
        'name'                   => 'عميل آجل تجريبي',
        'phone'                  => '01055554444',
        'credit_limit'           => 1000,
        'current_credit_balance' => 800,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    // Transaction of 1500 on credit will push balance to 800 + 1500 = 2300 > 1000 limit
    $request = new StorePosInvoiceRequest();
    $request->merge([
        'branch_id' => Branch::first()->id,
        'technician_id' => \App\Models\Employee::first()->id,
        'customer_id' => $customer->id,
        'items' => [
            [
                'product_id'     => $battery->id,
                'quantity'       => 1,
                'unit_price'     => 1500,
                'battery_serial' => 'SN-CREDIT-993',
            ],
        ],
        'payments' => [
            ['method' => 'credit', 'amount' => 1500],
        ],
        'manager_override_code' => 'wrong-code',
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('manager_override_code'))->toBeTrue();
});

test('process warranty claim request catches invalid or duplicate replacement serial', function () {
    $branch = Branch::first();
    $cashier = User::first();
    $customer = Customer::first() ?? Customer::create([
        'name'                   => 'عميل اختبار',
        'phone'                  => '01011112222',
        'tier'                   => 'standard',
        'credit_limit'           => 5000,
        'current_credit_balance' => 0,
        'is_active'              => true,
    ]);
    $product = Product::where('is_battery', true)->first();
    $product->update(['current_stock' => 5]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-001',
        'branch_id'      => $branch->id,
        'customer_id'    => $customer->id,
        'cashier_id'     => $cashier->id,
        'subtotal'       => 3000,
        'final_amount'   => 3000,
        'paid_amount'    => 3000,
    ]);

    $item = InvoiceItem::create([
        'invoice_id'              => $invoice->id,
        'product_id'              => $product->id,
        'quantity'                => 1,
        'unit_price'              => 3000,
        'total_price'             => 3000,
        'battery_serial_number'   => 'DEFECTIVE-SN-001',
        'warranty_duration_months'=> 12,
    ]);

    $activeWarranty = Warranty::create([
        'invoice_item_id' => $item->id,
        'customer_id'     => $customer->id,
        'serial_number'   => 'DEFECTIVE-SN-001',
        'start_date'      => now()->subMonths(2),
        'end_date'        => now()->addMonths(10),
        'status'          => 'active',
    ]);

    $employee = \App\Models\Employee::first();

    $request = new ProcessWarrantyClaimRequest();
    $request->merge([
        'defective_serial'           => 'DEFECTIVE-SN-001',
        'technician_id'              => $employee->id,
        'battery_voltage_tested'     => 10.2,
        'cca_tested'                 => 180,
        'issue_description'          => 'هبوط حاد في الجهد وخلايا داخلية تالفة',
        'decision'                   => 'replaced',
        'replacement_product_id'     => $product->id,
        'replacement_battery_serial' => 'DEFECTIVE-SN-001', // Cannot be identical to defective serial!
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('replacement_battery_serial'))->toBeTrue();
});

test('store scrap sale batch request rejects already sold scrap batteries', function () {
    $branch = Branch::first();
    $employee = \App\Models\Employee::first();

    $scrapSold = ScrapBatteriesInventory::create([
        'branch_id'      => $branch->id,
        'capacity_ah'    => '70Ah',
        'scrap_value'    => 800,
        'status'         => 'sold_to_factory', // Already sold!
        'received_by'    => $employee->id,
    ]);

    $request = new StoreScrapSaleBatchRequest();
    $request->merge([
        'buyer_name'        => 'مصنع الشرق لتدوير الرصاص',
        'total_amount'      => 850,
        'payment_method'    => 'cash',
        'scrap_battery_ids' => [$scrapSold->id],
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('scrap_battery_ids'))->toBeTrue();
});
