<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->owner = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->token = $this->owner->createToken('Device', ['owner:monitor'])->plainTextToken;
    $this->headers = ['Authorization' => 'Bearer ' . $this->token];

    $this->customer = Customer::create([
        'name'                   => 'محمد عبد الله',
        'phone'                  => '01012345678',
        'current_credit_balance' => 0,
        'credit_limit'           => 5000,
    ]);

    $category = \App\Models\Category::firstOrCreate(['slug' => 'batteries'], ['name' => 'بطاريات']);

    $this->product = Product::create([
        'category_id'       => $category->id,
        'name'              => 'بطارية فارتا 70 أمبير',
        'sku'               => 'BAT-VARTA-70',
        'brand'             => 'Varta',
        'cost_price'        => 2000,
        'retail_price'      => 2500,
        'current_stock'     => 10,
        'reorder_threshold' => 3,
        'is_battery'        => true,
        'is_active'         => true,
    ]);
});

test('recent invoices feed returns paginated countable invoices and clamps per_page', function () {
    // Create 3 active invoices
    for ($i = 1; $i <= 3; $i++) {
        Invoice::create([
            'invoice_number'   => "INV-COUNTABLE-{$i}",
            'branch_id'        => 1,
            'customer_id'      => $this->customer->id,
            'cashier_id'       => $this->owner->id,
            'subtotal'         => 1000 * $i,
            'final_amount'     => 1000 * $i,
            'paid_amount'      => 1000 * $i,
            'remaining_amount' => 0,
            'status'           => 'paid',
            'idempotency_key'  => "idemp-key-{$i}",
        ]);
    }

    // Create 1 cancelled invoice (must NOT appear in countable feed)
    Invoice::create([
        'invoice_number'   => 'INV-CANCELLED-001',
        'branch_id'        => 1,
        'customer_id'      => $this->customer->id,
        'cashier_id'       => $this->owner->id,
        'subtotal'         => 500,
        'final_amount'     => 500,
        'paid_amount'      => 0,
        'remaining_amount' => 500,
        'status'           => 'cancelled',
        'idempotency_key'  => 'idemp-cancel',
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.sales.recent', ['per_page' => 9999]));

    $response->assertOk()
        ->assertJson([
            'status'     => 'success',
            'pagination' => [
                'per_page' => 50, // Clamped from 9999 to 50
                'total'    => 3,  // Only countable invoices
            ],
        ]);

    $items = $response->json('data');
    expect(count($items))->toBe(3);

    // Verify internal idempotency_key is never exposed in the response
    $response->assertJsonMissing(['idempotency_key' => 'idemp-key-1']);
});

test('show invoice returns detailed receipt breakdown and returns 404 for missing invoice', function () {
    $invoice = Invoice::create([
        'invoice_number'         => 'INV-DETAIL-001',
        'branch_id'              => 1,
        'customer_id'            => $this->customer->id,
        'cashier_id'             => $this->owner->id,
        'subtotal'               => 2500.00,
        'discount_amount'        => 100.00,
        'scrap_deduction_amount' => 200.00,
        'final_amount'           => 2200.00,
        'paid_amount'            => 2200.00,
        'remaining_amount'       => 0.00,
        'payment_method'         => 'cash',
        'status'                 => 'paid',
        'notes'                  => 'ملاحظات تجريبية',
    ]);

    InvoiceItem::create([
        'invoice_id'               => $invoice->id,
        'product_id'               => $this->product->id,
        'quantity'                 => 1,
        'unit_price'               => 2500.00,
        'total_price'              => 2500.00,
        'battery_serial_number'    => 'SER-12345',
        'warranty_duration_months' => 12,
    ]);

    // Test valid invoice
    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.sales.show', $invoice->id));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'id'             => $invoice->id,
                'invoice_number' => 'INV-DETAIL-001',
                'customer'       => [
                    'id'   => $this->customer->id,
                    'name' => 'محمد عبد الله',
                ],
                'financials'     => [
                    'final_amount' => [
                        'raw'       => 2200.00,
                        'formatted' => '2,200.00 ج.م',
                    ],
                ],
            ],
        ]);

    expect($response->json('data.items.0.battery_serial_number'))->toBe('SER-12345');

    // Test 404 for non-existent invoice returns JSON 404 (due to ForceJsonResponse)
    $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.sales.show', 999999))
        ->assertStatus(404);
});

test('returns feed only includes refunded or partially refunded invoices', function () {
    // 1 Normal paid invoice
    Invoice::create([
        'invoice_number'   => 'INV-PAID-001',
        'branch_id'        => 1,
        'customer_id'      => $this->customer->id,
        'cashier_id'       => $this->owner->id,
        'subtotal'         => 1000,
        'final_amount'     => 1000,
        'paid_amount'      => 1000,
        'remaining_amount' => 0,
        'status'           => 'paid',
    ]);

    // 1 Refunded invoice
    Invoice::create([
        'invoice_number'   => 'INV-REFUNDED-001',
        'branch_id'        => 1,
        'customer_id'      => $this->customer->id,
        'cashier_id'       => $this->owner->id,
        'subtotal'         => 1000,
        'final_amount'     => 1000,
        'paid_amount'      => 0,
        'remaining_amount' => 0,
        'refunded_amount'  => 1000,
        'status'           => 'refunded',
    ]);

    // 1 Partially refunded invoice
    Invoice::create([
        'invoice_number'   => 'INV-PARTIAL-REFUND-001',
        'branch_id'        => 1,
        'customer_id'      => $this->customer->id,
        'cashier_id'       => $this->owner->id,
        'subtotal'         => 1500,
        'final_amount'     => 1500,
        'paid_amount'      => 1000,
        'remaining_amount' => 0,
        'refunded_amount'  => 500,
        'status'           => 'partially_refunded',
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.sales.returns'));

    $response->assertOk()
        ->assertJson([
            'status'     => 'success',
            'pagination' => [
                'total' => 2, // Only the 2 refunded ones
            ],
        ]);

    $invoiceNumbers = collect($response->json('data'))->pluck('invoice_number')->all();
    expect($invoiceNumbers)->toContain('INV-REFUNDED-001')
        ->and($invoiceNumbers)->toContain('INV-PARTIAL-REFUND-001')
        ->and($invoiceNumbers)->not->toContain('INV-PAID-001');
});
