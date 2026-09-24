<?php

use App\Models\Branch;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->superAdmin = User::where('email', 'admin@alhusseini.com')->first();
    $this->branch = Branch::first();
    $this->product = Product::first();
});

test('credit index page is accessible and returns customer credit data', function () {
    $customer = Customer::create([
        'name'                   => 'الأسطى حامد الميكانيكي',
        'phone'                  => '01012345678',
        'credit_limit'           => 10000,
        'current_credit_balance' => 3500,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    // HTML View
    $this->actingAs($this->superAdmin)
        ->get(route('admin.credit.index'))
        ->assertOk()
        ->assertViewIs('admin.sales.credit');

    // JSON API
    $this->actingAs($this->superAdmin)
        ->getJson(route('admin.credit.index'))
        ->assertOk()
        ->assertJson([
            'total_outstanding' => 3500,
            'customers_count'   => 1,
        ])
        ->assertJsonFragment([
            'name' => 'الأسطى حامد الميكانيكي',
        ]);
});

test('settling customer debt decrements balance, creates ledger entry, and returns success json', function () {
    $customer = Customer::create([
        'name'                   => 'ورشة الأمل لصيانة السيارات',
        'phone'                  => '01123456789',
        'credit_limit'           => 15000,
        'current_credit_balance' => 5000,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    $payload = [
        'customer_id'    => $customer->id,
        'amount'         => 2000,
        'payment_method' => 'cash',
        'notes'          => 'دفعة نقدية بالخزينة',
    ];

    $response = $this->actingAs($this->superAdmin)
        ->postJson(route('admin.credit.settle'), $payload);

    $response->assertOk()
        ->assertJson([
            'success'     => true,
            'new_balance' => 3000,
        ]);

    $customer->refresh();
    expect((float) $customer->current_credit_balance)->toBe(3000.0);

    // Assert Credit Ledger entry recorded
    $this->assertDatabaseHas('credit_ledger_entries', [
        'customer_id'   => $customer->id,
        'entry_type'    => 'payment_collection',
        'amount'        => 2000,
        'balance_after' => 3000,
    ]);
});

test('settling debt with amount exceeding current debt is rejected with 422', function () {
    $customer = Customer::create([
        'name'                   => 'أحمد رضوان',
        'phone'                  => '01234567890',
        'current_credit_balance' => 1000,
        'credit_limit'           => 5000,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    $payload = [
        'customer_id'    => $customer->id,
        'amount'         => 1500, // Exceeds 1000
        'payment_method' => 'cash',
    ];

    $response = $this->actingAs($this->superAdmin)
        ->postJson(route('admin.credit.settle'), $payload);

    $response->assertStatus(422);

    $customer->refresh();
    expect((float) $customer->current_credit_balance)->toBe(1000.0);
});

test('customer statement page displays full ledger and active invoices', function () {
    $customer = Customer::create([
        'name'                   => 'شركة النور للنقل',
        'phone'                  => '01009988776',
        'credit_limit'           => 20000,
        'current_credit_balance' => 4000,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    CustomerVehicle::create([
        'customer_id'  => $customer->id,
        'car_brand'    => 'مرسيدس',
        'car_model'    => 'أكتروس',
        'plate_number' => 'ط ر ق 9876',
    ]);

    CreditLedgerEntry::create([
        'customer_id'    => $customer->id,
        'entry_type'     => 'invoice_debt',
        'amount'         => 4000,
        'balance_before' => 0,
        'balance_after'  => 4000,
        'collected_by'   => $this->superAdmin->id,
        'created_at'     => now(),
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.credit.statement', $customer))
        ->assertOk()
        ->assertViewIs('admin.credit.statement')
        ->assertSee('شركة النور للنقل')
        ->assertSee('ط ر ق 9876')
        ->assertSee('4,000.00');
});

test('sales invoice show page loads invoice details, sold products, and payment breakdown', function () {
    $customer = Customer::create([
        'name'                   => 'عمرو دياب للسيارات',
        'phone'                  => '01011223344',
        'credit_limit'           => 5000,
        'current_credit_balance' => 0,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    $invoice = Invoice::create([
        'invoice_number'          => 'INV-TEST-9988',
        'branch_id'               => $this->branch->id,
        'customer_id'             => $customer->id,
        'cashier_id'              => $this->superAdmin->id,
        'subtotal'                => 3200,
        'final_amount'            => 3200,
        'paid_amount'             => 3200,
        'remaining_amount'        => 0,
        'payment_status'          => 'paid',
        'payment_method'          => 'cash',
        'invoice_type'            => 'retail',
    ]);

    InvoiceItem::create([
        'invoice_id'   => $invoice->id,
        'product_id'   => $this->product->id,
        'quantity'     => 1,
        'unit_price'   => 3200,
        'total_price'  => 3200,
    ]);

    $this->actingAs($this->superAdmin)
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertViewIs('admin.invoices.show')
        ->assertSee('INV-TEST-9988')
        ->assertSee($this->product->name)
        ->assertSee('عمرو دياب للسيارات');
});

test('sales return on credit invoice deducts from customer debt and records refund in credit ledger', function () {
    $customer = Customer::create([
        'name'                   => 'مكتب الرحاب للمقاولات',
        'phone'                  => '01033445566',
        'current_credit_balance' => 3500,
        'credit_limit'           => 10000,
        'tier'                   => 'standard',
        'is_active'              => true,
    ]);

    // Update product stock to known quantity
    $initialStock = $this->product->current_stock;

    // Credit invoice
    $invoice = Invoice::create([
        'invoice_number'          => 'INV-CR-RETURN',
        'branch_id'               => $this->branch->id,
        'customer_id'             => $customer->id,
        'cashier_id'              => $this->superAdmin->id,
        'subtotal'                => 3500,
        'final_amount'            => 3500,
        'paid_amount'             => 0,
        'remaining_amount'        => 3500,
        'payment_status'          => 'unpaid',
        'payment_method'          => 'credit',
        'invoice_type'            => 'retail',
    ]);

    InvoiceItem::create([
        'invoice_id'  => $invoice->id,
        'product_id'  => $this->product->id,
        'quantity'    => 1,
        'unit_price'  => 3500,
        'total_price' => 3500,
    ]);

    $returnPayload = [
        'reason' => 'خلل في القياس واسترجاع الفاتورة الآجلة',
        'items'  => [
            [
                'product_id' => $this->product->id,
                'quantity'   => 1,
            ],
        ],
    ];

    $response = $this->actingAs($this->superAdmin)
        ->post(route('admin.invoices.return', $invoice), $returnPayload);

    $response->assertRedirect();

    $customer->refresh();
    // Balance was 3500, return amount was 3500 => new balance must be 0!
    expect((float) $customer->current_credit_balance)->toBe(0.0);

    // Stock returned to inventory: initialStock + 1
    $this->product->refresh();
    expect($this->product->current_stock)->toBe($initialStock + 1);

    // Assert refund ledger entry created
    $this->assertDatabaseHas('credit_ledger_entries', [
        'customer_id'   => $customer->id,
        'entry_type'    => 'refund',
        'amount'        => 3500,
        'balance_after' => 0,
    ]);
});
