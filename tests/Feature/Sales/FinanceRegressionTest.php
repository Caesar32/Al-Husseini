<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Models\CreditLedgerEntry;
use App\Models\InvoicePayment;
use App\Contracts\Sales\PosOrderServiceInterface;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->posService = app(PosOrderServiceInterface::class);
    $this->branch = Branch::first();
    $this->cashier = User::first();
    $this->technician = \App\Models\Employee::first();
});

// R-01: Cash invoice
test('R-01 cash invoice creates paid invoice with zero remaining', function () {
    $customer = Customer::create(['name'=>'R01 Cash','phone'=>'01000000001','credit_limit'=>5000,'current_credit_balance'=>0,'is_active'=>true]);
    $product = Product::where('is_battery', true)->first();
    $product->update(['current_stock'=>10]);

    $inv = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,
        'customer_id'=>$customer->id,
        'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3200,'battery_serial'=>'SN-R01-001']],
        'payments'=>[['method'=>'cash','amount'=>3200]],
    ], $this->cashier->id);

    expect((float)$inv->final_amount)->toBe(3200.0)
        ->and((float)$inv->paid_amount)->toBe(3200.0)
        ->and((float)$inv->remaining_amount)->toBe(0.0)
        ->and($inv->status)->toBe('paid')
        ->and((float)$inv->paid_amount + (float)$inv->remaining_amount)->toBe((float)$inv->final_amount);
});

// R-02: Credit invoice
test('R-02 credit invoice creates debt and ledger', function () {
    $customer = Customer::create(['name'=>'R02 Credit','phone'=>'01000000002','credit_limit'=>5000,'current_credit_balance'=>0,'is_active'=>true]);
    $product = Product::first();
    $product->update(['current_stock'=>10]);

    $inv = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,
        'customer_id'=>$customer->id,
        'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3500,'battery_serial'=>'SN-R02-001']],
        'payments'=>[['method'=>'credit','amount'=>3500]],
    ], $this->cashier->id);

    $customer->refresh();
    expect((float)$customer->current_credit_balance)->toBe(3500.0);
    $this->assertDatabaseHas('credit_ledger_entries', ['customer_id'=>$customer->id,'entry_type'=>'invoice_debt','amount'=>3500]);
    expect($inv->status)->toBe('unpaid');
});

// R-03: FIFO partial + full settlement
test('R-03 FIFO settlement distributes correctly', function () {
    $customer = Customer::create(['name'=>'R03 FIFO','phone'=>'01000000003','credit_limit'=>10000,'current_credit_balance'=>0,'is_active'=>true]);
    $product = Product::first();
    $product->update(['current_stock'=>20]);

    // Create two credit invoices: 2000 and 1500
    $inv1 = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>2000,'battery_serial'=>'SN-R03-1']],
        'payments'=>[['method'=>'credit','amount'=>2000]],
    ], $this->cashier->id);
    $inv2 = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>1500,'battery_serial'=>'SN-R03-2']],
        'payments'=>[['method'=>'credit','amount'=>1500]],
    ], $this->cashier->id);

    $this->posService->settleCustomerDebt($customer->id, 2500, 'cash', $this->cashier->id, 'REC-R03-2500');

    $inv1->refresh(); $inv2->refresh(); $customer->refresh();
    expect((float)$inv1->remaining_amount)->toBe(0.0)->and($inv1->status)->toBe('paid');
    expect((float)$inv2->remaining_amount)->toBe(1000.0)->and($inv2->status)->toBe('partially_paid');
    expect((float)$customer->current_credit_balance)->toBe(1000.0);
    $this->assertDatabaseHas('invoice_payments', ['invoice_id'=>$inv1->id,'amount'=>2000]);
    $this->assertDatabaseHas('invoice_payments', ['invoice_id'=>$inv2->id,'amount'=>500]);
});

// R-05: Discount exceeding subtotal rejected
test('R-05 discount exceeding subtotal is rejected', function () {
    $product = Product::first();
    $product->update(['current_stock'=>10]);
    $customer = Customer::first();

    expect(fn() => $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,
        'customer_id'=>$customer->id ?? null,
        'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3200,'battery_serial'=>'SN-R05-001']],
        'discount_amount'=>4000,
        'payments'=>[['method'=>'cash','amount'=>0]],
    ], $this->cashier->id))->toThrow(\DomainException::class);
});

// R-07: Zero final (discount == subtotal) allowed
test('R-07 zero final with discount equals subtotal is allowed', function () {
    $product = Product::where('is_battery', false)->first() ?? Product::first();
    $product->update(['current_stock'=>10,'is_battery'=>false]);
    // Use non-battery to avoid serial requirement
    $inv = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,
        'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3200]],
        'discount_amount'=>3200,
        'payments'=>[['method'=>'cash','amount'=>0]],
    ], $this->cashier->id);
    expect((float)$inv->final_amount)->toBe(0.0)->and($inv->status)->toBe('paid');
});

// R-08: Duplicate receipt fails
test('R-08 duplicate receipt_number fails on second settlement', function () {
    $customer = Customer::create(['name'=>'R08 Dup','phone'=>'01000000008','credit_limit'=>5000,'current_credit_balance'=>2000,'is_active'=>true]);
    // Create ledger and invoice to have balance
    Invoice::withoutEvents(fn() => Invoice::create([
        'invoice_number'=>'INV-R08-01','branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'cashier_id'=>$this->cashier->id,
        'subtotal'=>2000,'final_amount'=>2000,'paid_amount'=>0,'remaining_amount'=>2000,'status'=>'unpaid','payment_method'=>'credit'
    ]));

    $this->posService->settleCustomerDebt($customer->id, 500, 'cash', $this->cashier->id, 'REC-DUP-001');
    expect(fn() => $this->posService->settleCustomerDebt($customer->id, 300, 'cash', $this->cashier->id, 'REC-DUP-001'))->toThrow(\DomainException::class);
});

// R-10: Cash return full
test('R-10 cash return full creates negative payment and refunded status', function () {
    $customer = Customer::create(['name'=>'R10 CashReturn','phone'=>'01000000010','credit_limit'=>5000,'current_credit_balance'=>0,'is_active'=>true]);
    $product = Product::first();
    $product->update(['current_stock'=>10]);
    $inv = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3200,'battery_serial'=>'SN-R10-001']],
        'payments'=>[['method'=>'cash','amount'=>3200]],
    ], $this->cashier->id);
    $stockAfterSale = $product->fresh()->current_stock;

    $returned = $this->posService->processSalesReturn($inv->id, [['product_id'=>$product->id,'quantity'=>1]], 'عميل غير راض', $this->cashier->id);

    expect($returned->status)->toBe('refunded')
        ->and((float)$returned->paid_amount)->toBe(0.0);
    $this->assertDatabaseHas('invoice_payments', ['invoice_id'=>$inv->id,'amount'=>-3200]);
    expect($product->fresh()->current_stock)->toBe($stockAfterSale + 1);
});

// R-11: Credit return partial
test('R-11 credit return partial creates partially_refunded', function () {
    $customer = Customer::create(['name'=>'R11 Partial','phone'=>'01000000011','credit_limit'=>10000,'current_credit_balance'=>0,'is_active'=>true]);
    $product = Product::first();
    $product->update(['current_stock'=>10]);
    // 2 items at 1500 each = 3000
    $inv = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>2,'unit_price'=>1500,'battery_serial'=>'SN-R11-001']],
        'payments'=>[['method'=>'credit','amount'=>3000]],
    ], $this->cashier->id);

    $returned = $this->posService->processSalesReturn($inv->id, [['product_id'=>$product->id,'quantity'=>1]], 'جزئي', $this->cashier->id);
    $customer->refresh();
    expect($returned->status)->toBe('partially_refunded')
        ->and((float)$returned->remaining_amount)->toBe(1500.0)
        ->and((float)$customer->current_credit_balance)->toBe(1500.0);
});

// R-13: Statement shows final_amount not total_amount
test('R-13 statement displays final_amount', function () {
    $customer = Customer::create(['name'=>'R13 Statement','phone'=>'01000000013','credit_limit'=>5000,'current_credit_balance'=>0,'is_active'=>true]);
    $inv = Invoice::create([
        'invoice_number'=>'INV-R13-001','branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'cashier_id'=>$this->cashier->id,
        'subtotal'=>3500,'final_amount'=>3500,'paid_amount'=>0,'remaining_amount'=>3500,'status'=>'unpaid','payment_method'=>'credit'
    ]);
    InvoiceItem::create(['invoice_id'=>$inv->id,'product_id'=>Product::first()->id,'quantity'=>1,'unit_price'=>3500,'total_price'=>3500]);
    $customer->update(['current_credit_balance'=>3500]);
    CreditLedgerEntry::create(['customer_id'=>$customer->id,'invoice_id'=>$inv->id,'entry_type'=>'invoice_debt','amount'=>3500,'balance_before'=>0,'balance_after'=>3500,'collected_by'=>$this->cashier->id]);

    $response = $this->actingAs($this->cashier)->get(route('admin.credit.statement', $customer));
    $response->assertOk()->assertSee('3,500.00');
});

// R-14: Entry_type badges
test('R-14 ledger entry badges use invoice_debt not sale_on_credit', function () {
    $customer = Customer::create(['name'=>'R14 Badge','phone'=>'01000000014','credit_limit'=>5000,'current_credit_balance'=>0,'is_active'=>true]);
    CreditLedgerEntry::create(['customer_id'=>$customer->id,'entry_type'=>'invoice_debt','amount'=>1000,'balance_before'=>0,'balance_after'=>1000,'collected_by'=>$this->cashier->id, 'notes'=>'test']);
    $response = $this->actingAs($this->cashier)->get(route('admin.credit.statement', $customer));
    $response->assertSee('فاتورة بيع بالآجل');
});

// R-17: Search by serial
test('R-17 search by battery serial finds invoice', function () {
    $customer = Customer::create(['name'=>'R17 Search','phone'=>'01000000017','credit_limit'=>5000,'current_credit_balance'=>0,'is_active'=>true]);
    $product = Product::where('is_battery', true)->first();
    $product->update(['current_stock'=>10]);
    $inv = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3000,'battery_serial'=>'SN-R17-SEARCH-999']],
        'payments'=>[['method'=>'cash','amount'=>3000]],
    ], $this->cashier->id);

    $paginator = $this->posService->getPaginatedInvoices(['search'=>'SN-R17-SEARCH-999']);
    expect($paginator->total())->toBeGreaterThanOrEqual(1);
    expect($paginator->items()[0]->invoice_number)->toBe($inv->invoice_number);
});

// R-18: Dashboard excludes refunded
test('R-18 dashboard sales excludes refunded invoices', function () {
    $product = Product::first();
    $product->update(['current_stock'=>10]);
    $customer = Customer::create(['name'=>'R18 Dash','phone'=>'01000000018','credit_limit'=>5000,'current_credit_balance'=>0,'is_active'=>true]);
    $invPaid = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3200,'battery_serial'=>'SN-R18-PAID']],
        'payments'=>[['method'=>'cash','amount'=>3200]],
    ], $this->cashier->id);
    $invToRefund = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3500,'battery_serial'=>'SN-R18-REF']],
        'payments'=>[['method'=>'cash','amount'=>3500]],
    ], $this->cashier->id);
    $this->posService->processSalesReturn($invToRefund->id, [['product_id'=>$product->id,'quantity'=>1]], 'test', $this->cashier->id);

    $stats = $this->posService->getInvoiceStats([]);
    // total_sales should not include refunded invoice
    $expected = Invoice::whereNotIn('status',['cancelled','refunded','partially_refunded'])->sum('final_amount');
    expect((float)$stats['total_sales'])->toBe((float)$expected);
});

// R-19: Revenue not double counted
test('R-19 revenue not double counted on credit settlement', function () {
    $customer = Customer::create(['name'=>'R19 Revenue','phone'=>'01000000019','credit_limit'=>10000,'current_credit_balance'=>0,'is_active'=>true]);
    $product = Product::first();
    $product->update(['current_stock'=>10]);
    $inv = $this->posService->processPosSale([
        'branch_id'=>$this->branch->id,'customer_id'=>$customer->id,'technician_id'=>$this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3500,'battery_serial'=>'SN-R19-001']],
        'payments'=>[['method'=>'credit','amount'=>3500]],
    ], $this->cashier->id);
    $this->posService->settleCustomerDebt($customer->id, 2000, 'cash', $this->cashier->id, 'REC-R19-001');

    $revenue = \App\Models\InvoicePayment::active()->cash()->sum('amount');
    $ledger = CreditLedgerEntry::where('entry_type','payment_collection')->sum('amount');
    // Revenue should be 2000 (cash payment), ledger also 2000, but dashboard revenue should be 2000 not 4000
    expect((float)$revenue)->toBe(2000.0);
    expect((float)$ledger)->toBeGreaterThanOrEqual(2000.0);
});

// R-20: Branch isolation on sale
test('R-20 branch isolation falls back to auth branch when branch_id missing', function () {
    $product = Product::first();
    $product->update(['current_stock'=>10]);
    $request = new \App\Http\Requests\Admin\Pos\StorePosInvoiceRequest();
    $request->merge([
        'technician_id'=> $this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3000,'battery_serial'=>'SN-R20-001']],
        'payments'=>[['method'=>'cash','amount'=>3000]],
        // branch_id missing — should be allowed (nullable) and fallback to auth branch in service
    ]);
    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $request->rules());
    expect($validator->passes())->toBeTrue();

    // Service should fallback to cashier's branch
    $inv = $this->posService->processPosSale([
        'technician_id'=> $this->technician->id,
        'items'=>[['product_id'=>$product->id,'quantity'=>1,'unit_price'=>3000,'battery_serial'=>'SN-R20-FALLBACK']],
        'payments'=>[['method'=>'cash','amount'=>3000]],
    ], $this->cashier->id);
    expect($inv->branch_id)->toBe($this->branch->id);
});
