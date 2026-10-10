<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// POS payment rules enforced by the server (the browser validates too, but is never trusted):
// every payment > 0 with at most 2 decimals, the payments never exceed what is due, and what is
// left is exactly the amount put on credit.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);

    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();

    $this->oil = Product::where('is_battery', false)->whereHas('category', fn ($q) => $q->where('slug', 'oils'))->firstOrFail();
    $this->oil->update(['current_stock' => 10, 'retail_price' => 500]);

    $this->customer = Customer::create([
        'name' => 'عميل اختبار الدفع', 'phone' => '01099990000', 'credit_limit' => 100000,
        'current_credit_balance' => 0, 'tier' => 'standard', 'is_active' => true,
    ]);

    $this->invoicesBefore = Invoice::count(); // the seeder already creates sample invoices

    $this->sale = fn (array $payments, array $extra = []) => array_merge([
        'branch_id'     => Branch::first()->id,
        'technician_id' => Employee::first()->id,
        'customer_id'   => $this->customer->id,
        'items'         => [['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500]],
        'payments'      => $payments,
    ], $extra);
});

function postSale($test, array $payload, ?User $as = null)
{
    return $test->actingAs($as ?? $test->cashier)->postJson(route('admin.pos.store'), $payload);
}

// ── Full payment / exact payment ─────────────────────────────────────────────

test('full payment: paying exactly the amount due leaves nothing remaining', function (string $method) {
    $response = postSale($this, ($this->sale)([['method' => $method, 'amount' => 500]]))->assertStatus(201);

    $invoice = Invoice::findOrFail($response->json('invoice_id'));
    expect($invoice->status)->toBe('paid')
        ->and((float) $invoice->paid_amount)->toBe(500.0)
        ->and((float) $invoice->remaining_amount)->toBe(0.0)
        ->and($this->oil->fresh()->current_stock)->toBe(9);
})->with(['cash', 'card', 'bank_transfer']);

// ── Partial payment and the remaining amount ─────────────────────────────────

test('partial payment: the unpaid part is exactly the credit leg and lands on the customer account', function () {
    $response = postSale($this, ($this->sale)([
        ['method' => 'cash', 'amount' => 200],
        ['method' => 'credit', 'amount' => 300],
    ]))->assertStatus(201);

    $invoice = Invoice::findOrFail($response->json('invoice_id'));
    expect($invoice->status)->toBe('partially_paid')
        ->and((float) $invoice->paid_amount)->toBe(200.0)
        ->and((float) $invoice->remaining_amount)->toBe(300.0)
        ->and((float) $this->customer->fresh()->current_credit_balance)->toBe(300.0);
});

test('remaining only: nothing paid now puts the whole amount on credit', function () {
    $invoice = Invoice::findOrFail(
        postSale($this, ($this->sale)([['method' => 'credit', 'amount' => 500]]))->assertStatus(201)->json('invoice_id')
    );

    expect($invoice->status)->toBe('unpaid')
        ->and((float) $invoice->paid_amount)->toBe(0.0)
        ->and((float) $invoice->remaining_amount)->toBe(500.0);
});

test('remaining is calculated exactly for decimal amounts (due - paid)', function () {
    $this->oil->update(['retail_price' => 1999.99]);

    $invoice = Invoice::findOrFail(postSale($this, ($this->sale)([
        ['method' => 'cash', 'amount' => 700.25],
        ['method' => 'credit', 'amount' => 1299.74],
    ], ['items' => [['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 1999.99]]]))->assertStatus(201)->json('invoice_id'));

    expect(number_format((float) $invoice->final_amount, 2, '.', ''))->toBe('1999.99')
        ->and(number_format((float) $invoice->paid_amount, 2, '.', ''))->toBe('700.25')
        ->and(number_format((float) $invoice->remaining_amount, 2, '.', ''))->toBe('1299.74');
});

// ── Payments above what is due ───────────────────────────────────────────────

test('a payment greater than the amount due is rejected with a clear message and nothing is saved', function () {
    $response = postSale($this, ($this->sale)([['method' => 'cash', 'amount' => 600]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payments']);

    expect($response->json('errors.payments.0'))->toContain('يتجاوز المبلغ المستحق');
    expect(Invoice::count())->toBe($this->invoicesBefore)
        ->and($this->oil->fresh()->current_stock)->toBe(10);
});

test('a payment plus a credit leg that together exceed the amount due is rejected', function () {
    // paid now 400 + remaining 400 = 800 against 500 due: the "remaining" can never be inflated
    $response = postSale($this, ($this->sale)([
        ['method' => 'cash', 'amount' => 400],
        ['method' => 'credit', 'amount' => 400],
    ]))->assertStatus(422)->assertJsonValidationErrors(['payments']);

    expect($response->json('errors.payments.0'))->toContain('يتجاوز المبلغ المستحق');
    expect(Invoice::count())->toBe($this->invoicesBefore)
        ->and((float) $this->customer->fresh()->current_credit_balance)->toBe(0.0);
});

test('payments that do not cover the amount due are rejected', function () {
    $response = postSale($this, ($this->sale)([['method' => 'cash', 'amount' => 300]]))
        ->assertStatus(422)->assertJsonValidationErrors(['payments']);

    expect($response->json('errors.payments.0'))->toContain('أقل من المبلغ المستحق');
    expect(Invoice::count())->toBe($this->invoicesBefore);
});

// ── Zero / negative / malformed amounts ──────────────────────────────────────

test('zero and negative payments are rejected', function (float $amount) {
    postSale($this, ($this->sale)([['method' => 'cash', 'amount' => $amount]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payments.0.amount']);

    expect(Invoice::count())->toBe($this->invoicesBefore);
})->with([0, -50, -0.01]);

test('currency amounts allow at most two decimals', function () {
    postSale($this, ($this->sale)([['method' => 'cash', 'amount' => 499.995], ['method' => 'credit', 'amount' => 0.005]]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payments.0.amount']);

    expect(Invoice::count())->toBe($this->invoicesBefore);
});

test('non numeric payment amounts are rejected', function () {
    postSale($this, ($this->sale)([['method' => 'cash', 'amount' => 'abc']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payments.0.amount']);
});

test('amount due = 0: no payment can be valid (nothing may be paid, and a zero payment is refused)', function () {
    // the whole price discounted away (super-admin may discount): due is 0
    foreach ([0.01, 0] as $amount) {
        postSale($this, ($this->sale)([['method' => 'cash', 'amount' => $amount]], ['discount_amount' => 500]), $this->admin)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payments' . ($amount === 0 ? '.0.amount' : '')]);
    }

    expect(Invoice::count())->toBe($this->invoicesBefore);
});

// ── Double submission ────────────────────────────────────────────────────────

test('submitting the same checkout twice creates one invoice and takes stock once', function () {
    $payload = ($this->sale)([['method' => 'cash', 'amount' => 500]], ['idempotency_key' => 'pay-double-0001']);

    $first = postSale($this, $payload)->assertStatus(201);
    $second = postSale($this, $payload);

    $second->assertSuccessful();
    expect($second->json('invoice_id'))->toBe($first->json('invoice_id'));
    expect(Invoice::count())->toBe($this->invoicesBefore + 1)
        ->and($this->oil->fresh()->current_stock)->toBe(9);
});

// ── Repeated partial payments (collections on the remaining balance) ─────────

function collect_payment($test, float|string $amount)
{
    return $test->actingAs($test->admin)->postJson(route('admin.credit.settle'), [
        'customer_id' => $test->customer->id, 'amount' => $amount, 'payment_method' => 'cash',
    ]);
}

test('repeated partial payments reduce the remaining balance step by step and never below zero', function () {
    $invoiceId = postSale($this, ($this->sale)([
        ['method' => 'cash', 'amount' => 200],
        ['method' => 'credit', 'amount' => 300],
    ]))->assertStatus(201)->json('invoice_id');

    $balance = fn () => (float) $this->customer->fresh()->current_credit_balance;
    $remaining = fn () => (float) Invoice::find($invoiceId)->remaining_amount;

    collect_payment($this, 100)->assertSuccessful();
    expect($balance())->toBe(200.0)->and($remaining())->toBe(200.0)
        ->and(Invoice::find($invoiceId)->status)->toBe('partially_paid');

    collect_payment($this, 100.50)->assertSuccessful();
    expect($balance())->toBe(99.5)->and($remaining())->toBe(99.5);

    // more than what is still owed: rejected, balance untouched
    collect_payment($this, 100)->assertStatus(422);
    expect($balance())->toBe(99.5)->and($remaining())->toBe(99.5);

    // exact remainder closes the invoice
    collect_payment($this, 99.50)->assertSuccessful();
    expect($balance())->toBe(0.0)->and($remaining())->toBe(0.0)
        ->and(Invoice::find($invoiceId)->status)->toBe('paid')
        ->and((float) Invoice::find($invoiceId)->paid_amount)->toBe(500.0);

    // nothing left to collect: remaining = 0 cannot be paid again
    collect_payment($this, 1)->assertStatus(422);
    expect($balance())->toBe(0.0);
});

test('collections reject zero, negative and over-precise amounts', function (float|string $amount) {
    postSale($this, ($this->sale)([['method' => 'credit', 'amount' => 500]]))->assertStatus(201);

    collect_payment($this, $amount)->assertStatus(422)->assertJsonValidationErrors(['amount']);
    expect((float) $this->customer->fresh()->current_credit_balance)->toBe(500.0);
})->with([0, -10, 10.123, 'abc']);

// ── The payment section the cashier sees ─────────────────────────────────────

test('the POS shows the three payment modes and no hard-coded deposit', function () {
    $html = $this->actingAs($this->cashier)->get(route('admin.pos.index'))->assertOk()->getContent();

    expect($html)
        ->toContain('كامل الدفع')
        ->toContain('جزء من المبلغ')
        ->toContain('باقي المبلغ')
        ->toContain('id="paidNowInput"')
        ->toContain('id="paymentRemainingOutput"')
        ->toContain('setPaymentMode(')
        ->not->toContain('creditDepositInput')
        ->not->toContain('value="500"');
});
