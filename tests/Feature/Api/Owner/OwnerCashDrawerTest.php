<?php

use App\Models\Customer;
use App\Models\CreditLedgerEntry;
use App\Models\Invoice;
use App\Models\InvoicePayment;
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
        'name'                   => 'عميل كاشير',
        'phone'                  => '01099998888',
        'current_credit_balance' => 0,
        'credit_limit'           => 1000,
    ]);
});

test('cash drawer surveillance breaks down cash, card, and expected physical cash', function () {
    $invoice1 = Invoice::create([
        'invoice_number'   => 'INV-DRAWER-001',
        'branch_id'        => 1,
        'customer_id'      => $this->customer->id,
        'cashier_id'       => $this->owner->id,
        'subtotal'         => 3000,
        'final_amount'     => 3000,
        'paid_amount'      => 3000,
        'remaining_amount' => 0,
        'status'           => 'paid',
    ]);

    // Cash payment 2000, Card payment 1000
    InvoicePayment::create([
        'invoice_id'     => $invoice1->id,
        'payment_method' => 'cash',
        'amount'         => 2000,
    ]);
    InvoicePayment::create([
        'invoice_id'     => $invoice1->id,
        'payment_method' => 'card',
        'amount'         => 1000,
    ]);

    // Today credit cash collection 500
    CreditLedgerEntry::create([
        'customer_id'    => $this->customer->id,
        'entry_type'     => 'payment_collection',
        'amount'         => 500,
        'balance_before' => 500,
        'balance_after'  => 0,
        'collected_by'   => $this->owner->id,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.cash_drawer.shift'));

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'cash_sales'              => 2000.00,
                'card_sales'              => 1000.00,
                'credit_collections_cash' => 500.00,
                'expected_drawer_cash'    => 2500.00, // 2000 cash sales + 500 credit collection
            ],
        ])
        ->assertJsonStructure([
            'data' => [
                'cashier_name',
                'shift_status',
                'cash_sales',
                'card_sales',
                'credit_collections_cash',
                'expected_drawer_cash',
                'formatted' => [
                    'cash_sales',
                    'card_sales',
                    'expected_drawer_cash',
                ],
            ],
        ]);
});
