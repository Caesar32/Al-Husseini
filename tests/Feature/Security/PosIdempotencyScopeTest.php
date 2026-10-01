<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);

    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->otherCashier = User::factory()->create(['branch_id' => Branch::first()->id]);
    $this->otherCashier->assignRole('cashier');

    $this->battery = Product::where('is_battery', true)->firstOrFail();
    $this->battery->update(['current_stock' => 10, 'retail_price' => 3000]);

    $this->payload = fn (string $serial) => [
        'branch_id'       => Branch::first()->id,
        'technician_id'   => Employee::first()->id,
        'idempotency_key' => 'checkout-key-0001',
        'items'           => [[
            'product_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => 3000, 'battery_serial' => $serial,
        ]],
        'payments'        => [['method' => 'cash', 'amount' => 3000]],
    ];
});

test('a retry by the same cashier returns the original invoice without a second sale', function () {
    $first = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), ($this->payload)('SN-IDEM-1'))->assertStatus(201);
    $retry = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), ($this->payload)('SN-IDEM-1'));

    $retry->assertSuccessful();
    expect($retry->json('invoice_id') ?? $retry->json('data.invoice_id'))
        ->toBe($first->json('invoice_id') ?? $first->json('data.invoice_id'));
    expect(Invoice::where('idempotency_key', 'checkout-key-0001')->count())->toBe(1)
        ->and($this->battery->fresh()->current_stock)->toBe(9);
});

test("another cashier cannot resolve or reuse someone else's idempotency key", function () {
    $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), ($this->payload)('SN-IDEM-2'))->assertStatus(201);

    $this->actingAs($this->otherCashier)
        ->postJson(route('admin.pos.store'), ($this->payload)('SN-IDEM-3'))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['idempotency_key']);

    $keyed = Invoice::where('idempotency_key', 'checkout-key-0001')->get();
    expect($keyed)->toHaveCount(1)
        ->and($keyed->first()->cashier_id)->toBe($this->cashier->id)
        ->and($this->battery->fresh()->current_stock)->toBe(9);
});
