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

// Phase 4: the POS endpoint contract the browser now relies on.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);

    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->battery = Product::where('is_battery', true)->firstOrFail();
    $this->battery->update(['current_stock' => 2, 'retail_price' => 3000]);
    $this->oil = Product::where('is_battery', false)->whereHas('category', fn ($q) => $q->where('slug', 'oils'))->firstOrFail();
    $this->oil->update(['current_stock' => 1, 'retail_price' => 500]);

    $this->base = fn (array $items, float $amount, array $extra = []) => array_merge([
        'branch_id'     => Branch::first()->id,
        'technician_id' => Employee::first()->id,
        'items'         => $items,
        'payments'      => [['method' => 'cash', 'amount' => $amount]],
    ], $extra);
});

test('a non-numeric product id is rejected', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('admin.pos.store'), ($this->base)([
            ['product_id' => 'PROD-101', 'quantity' => 1, 'unit_price' => 500],
        ], 500))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.product_id']);
});

test('a battery line without a serial is rejected', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('admin.pos.store'), ($this->base)([
            ['product_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => 3000],
        ], 3000))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.battery_serial']);
});

test('a battery line must cover exactly one unit', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('admin.pos.store'), ($this->base)([
            ['product_id' => $this->battery->id, 'quantity' => 2, 'unit_price' => 3000, 'battery_serial' => 'SN-QTY-2'],
        ], 6000))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity']);

    expect($this->battery->fresh()->current_stock)->toBe(2);
});

test('stock is checked against the total requested across lines of the same product', function () {
    // Stock 1, two separate lines of the same oil.
    $invoicesBefore = Invoice::count();

    $this->actingAs($this->cashier)
        ->postJson(route('admin.pos.store'), ($this->base)([
            ['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500],
            ['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500],
        ], 1000))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity']);

    expect($this->oil->fresh()->current_stock)->toBe(1)
        ->and(Invoice::count())->toBe($invoicesBefore);
});

test('the service also refuses an oversell split across lines', function () {
    $service = app(\App\Contracts\Sales\PosOrderServiceInterface::class);

    expect(fn () => $service->processPosSale(($this->base)([
        ['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500],
        ['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500],
    ], 1000), $this->cashier->id))->toThrow(DomainException::class);

    expect($this->oil->fresh()->current_stock)->toBe(1);
});

test('two battery units sold as two lines with distinct serials consume both units', function () {
    $this->actingAs($this->cashier)
        ->postJson(route('admin.pos.store'), ($this->base)([
            ['product_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => 3000, 'battery_serial' => 'SN-UNIT-A'],
            ['product_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => 3000, 'battery_serial' => 'SN-UNIT-B'],
        ], 6000))
        ->assertStatus(201);

    expect($this->battery->fresh()->current_stock)->toBe(0)
        ->and(\App\Models\Warranty::whereIn('serial_number', ['SN-UNIT-A', 'SN-UNIT-B'])->count())->toBe(2);
});

test('a repeated idempotency key returns the same invoice without a second stock deduction', function () {
    $payload = ($this->base)([
        ['product_id' => $this->oil->id, 'quantity' => 1, 'unit_price' => 500],
    ], 500, ['idempotency_key' => 'pos-retry-key-1']);

    $first = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), $payload)->assertStatus(201);
    $second = $this->actingAs($this->cashier)->postJson(route('admin.pos.store'), $payload);

    $second->assertSuccessful();
    expect($second->json('invoice_id'))->toBe($first->json('invoice_id'))
        ->and(Invoice::where('idempotency_key', 'pos-retry-key-1')->count())->toBe(1)
        ->and($this->oil->fresh()->current_stock)->toBe(0);
});

test('sale and return lines are journaled in stock_movements and add up to the stock change (Phase 2)', function () {
    \Illuminate\Support\Facades\Queue::fake();
    $initial = $this->battery->fresh()->current_stock;

    $invoiceId = $this->actingAs($this->cashier)
        ->postJson(route('admin.pos.store'), ($this->base)([
            ['product_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => 3000, 'battery_serial' => 'SN-JRN-A'],
            ['product_id' => $this->battery->id, 'quantity' => 1, 'unit_price' => 3000, 'battery_serial' => 'SN-JRN-B'],
        ], 6000))
        ->assertStatus(201)
        ->json('invoice_id');

    // Two lines of the same product: the 1 -> 0 alert fires once, on the second line.
    \Illuminate\Support\Facades\Queue::assertPushed(
        \App\Jobs\DispatchOwnerPushNotification::class,
        fn ($job) => ($job->data['type'] ?? null) === 'critical_stock_zero' && $job->data['product_id'] === $this->battery->id
    );
    \Illuminate\Support\Facades\Queue::assertPushedTimes(\App\Jobs\DispatchOwnerPushNotification::class, 1);

    app(\App\Contracts\Sales\PosOrderServiceInterface::class)
        ->processSalesReturn($invoiceId, [['invoice_item_id' => Invoice::findOrFail($invoiceId)->items()->value('id'), 'quantity' => 1]], 'مرتجع', $this->cashier->id);

    $moves = \App\Models\StockMovement::where('product_id', $this->battery->id)->orderBy('id')->get();
    expect($moves->pluck('type')->map->value->all())->toBe(['sale', 'sale', 'sale_return'])
        ->and($moves->pluck('quantity_delta')->all())->toBe([-1, -1, 1])
        ->and($moves->pluck('stock_after')->all())->toBe([1, 0, 1])
        ->and($moves->every(fn ($m) => $m->source_type === (new Invoice)->getMorphClass() && $m->source_id === $invoiceId))->toBeTrue()
        ->and($moves->sum('quantity_delta'))->toBe($this->battery->fresh()->current_stock - $initial);
});
