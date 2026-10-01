<?php

use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// FE-04: credit page collections come from the server ledger, not the browser mock store.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->accountant = User::where('email', 'accountant@alhusseini.com')->firstOrFail();

    $this->customer = Customer::create([
        'name' => 'ورشة النور', 'phone' => '01055500011', 'national_id' => '29812121234567',
        'credit_limit' => 10000, 'current_credit_balance' => 4000, 'tier' => 'fleet',
    ]);
});

test('settling debt records a collection that the payments endpoint returns', function () {
    $this->actingAs($this->accountant)
        ->postJson(route('admin.credit.settle'), [
            'customer_id' => $this->customer->id,
            'amount' => 1500,
            'payment_method' => 'cash',
            'receipt_number' => 'RC-0001',
        ])
        ->assertOk();

    $response = $this->actingAs($this->accountant)
        ->getJson(route('admin.credit.payments'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.receipt_number', 'RC-0001')
        ->assertJsonPath('data.0.customer.id', $this->customer->id)
        ->assertJsonPath('data.0.collected_by', $this->accountant->name);

    expect((float) $response->json('data.0.amount'))->toBe(1500.0)
        ->and((float) $response->json('collected_this_month'))->toBe(1500.0)
        ->and(CreditLedgerEntry::where('entry_type', 'payment_collection')->count())->toBe(1);
});

test('payments endpoint requires credit.view', function () {
    $cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();

    $this->actingAs($cashier)->getJson(route('admin.credit.payments'))->assertForbidden();
});

test('credit page embeds trimmed customer rows without national ids or mock store usage', function () {
    $html = $this->actingAs($this->accountant)
        ->get(route('admin.credit.index'))
        ->assertOk()
        ->assertDontSee('29812121234567', false)
        ->assertDontSee('national_id', false)
        ->assertSee(json_encode(route('admin.credit.payments')), false)
        ->getContent();

    $script = substr($html, strpos($html, 'const CREDIT_DATA'));
    expect($script)->not->toContain('AlHusseiniSales')
        ->and($script)->not->toContain('/admin/credit/${');
});

test('credit index json exposes the trimmed rows and collected total', function () {
    $this->actingAs($this->accountant)
        ->getJson(route('admin.credit.index'))
        ->assertOk()
        ->assertJsonPath('customers.0.id', $this->customer->id)
        ->assertJsonPath('customers.0.tier', 'fleet')
        ->assertJsonMissingPath('customers.0.national_id')
        ->assertJsonPath('collected_this_month', 0);
});
