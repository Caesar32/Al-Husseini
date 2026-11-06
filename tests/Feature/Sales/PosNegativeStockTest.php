<?php

use App\Contracts\Catalog\StockLedgerInterface;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalesAndPosDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// The allow_negative_stock setting lets the POS sell ahead of the stock until the physical count.
// Off (the default) keeps every stock check; on, only a POS sale may go below zero.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->seed(SalesAndPosDataSeeder::class);

    $this->cashier = User::where('email', 'cashier@alhusseini.com')->firstOrFail();
    $this->oil = Product::where('is_battery', false)->whereHas('category', fn ($q) => $q->where('slug', 'oils'))->firstOrFail();
    $this->oil->update(['current_stock' => 0, 'retail_price' => 500]);

    $this->customer = Customer::create([
        'name' => 'عميل اختبار الرصيد', 'phone' => '01099990001', 'credit_limit' => 100000,
        'current_credit_balance' => 0, 'tier' => 'standard', 'is_active' => true,
    ]);

    $this->sale = fn (float $quantity = 2) => [
        'branch_id' => Branch::first()->id,
        'technician_id' => Employee::first()->id,
        'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->oil->id, 'quantity' => $quantity, 'unit_price' => 500]],
        'payments' => [['method' => 'cash', 'amount' => 500 * $quantity]],
    ];
});

function postPosSale($test, array $payload)
{
    return $test->actingAs($test->cashier)->postJson(route('admin.pos.store'), $payload);
}

test('with the setting off, a sale above the stock is refused and nothing changes', function () {
    $invoices = Invoice::count();

    postPosSale($this, ($this->sale)())->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');

    expect((float) $this->oil->fresh()->current_stock)->toBe(0.0)
        ->and(Invoice::count())->toBe($invoices);
});

test('a missing setting counts as off', function () {
    Setting::where('key', 'allow_negative_stock')->delete();
    Illuminate\Support\Facades\Cache::forget('setting.allow_negative_stock');

    postPosSale($this, ($this->sale)())->assertStatus(422);
});

test('with the setting on, a sale goes below zero and is journaled as usual', function () {
    Setting::set('allow_negative_stock', '1', 'sales');
    $invoices = Invoice::count();

    postPosSale($this, ($this->sale)())->assertSuccessful();

    $movement = StockMovement::where('product_id', $this->oil->id)->latest('id')->firstOrFail();
    expect((float) $this->oil->fresh()->current_stock)->toBe(-2.0)
        ->and(Invoice::count())->toBe($invoices + 1)
        ->and($movement->type)->toBe(StockMovementType::Sale)
        ->and((float) $movement->quantity_delta)->toBe(-2.0)
        ->and((float) $movement->stock_after)->toBe(-2.0);
});

test('turning the setting off again restores the stock check at once', function () {
    Setting::set('allow_negative_stock', '1', 'sales');
    postPosSale($this, ($this->sale)())->assertSuccessful();

    Setting::set('allow_negative_stock', '0', 'sales');
    postPosSale($this, ($this->sale)())->assertStatus(422);

    expect((float) $this->oil->fresh()->current_stock)->toBe(-2.0);
});

test('the sale service itself keeps the check when the setting is off', function () {
    $service = app(App\Contracts\Sales\PosOrderServiceInterface::class);

    expect(fn () => $service->processPosSale(($this->sale)(), $this->cashier->id))
        ->toThrow(DomainException::class, 'ولا يكفي لصرف');
});

test('the ledger refuses a move below zero unless the caller asks, even with the setting on', function () {
    Setting::set('allow_negative_stock', '1', 'sales');
    $ledger = app(StockLedgerInterface::class);

    DB::transaction(function () use ($ledger) {
        expect(fn () => $ledger->move($this->oil->id, -1, StockMovementType::WarrantyReplacement, $this->oil))
            ->toThrow(DomainException::class);
    });

    expect((float) $this->oil->fresh()->current_stock)->toBe(0.0);
});

test('a physical count replaces a negative balance, with the difference in the journal', function () {
    Setting::set('allow_negative_stock', '1', 'sales');
    postPosSale($this, ($this->sale)())->assertSuccessful();

    DB::transaction(fn () => app(StockLedgerInterface::class)->setTo($this->oil->id, 10, $this->oil));

    $movement = StockMovement::where('product_id', $this->oil->id)->latest('id')->firstOrFail();
    expect((float) $this->oil->fresh()->current_stock)->toBe(10.0)
        ->and($movement->type)->toBe(StockMovementType::Adjustment)
        ->and((float) $movement->quantity_delta)->toBe(12.0);
});

test('a return brings a negative balance back up', function () {
    Setting::set('allow_negative_stock', '1', 'sales');
    postPosSale($this, ($this->sale)())->assertSuccessful();

    DB::transaction(fn () => app(StockLedgerInterface::class)->move($this->oil->id, 1, StockMovementType::SaleReturn, $this->oil));

    expect((float) $this->oil->fresh()->current_stock)->toBe(-1.0);
});

test('a decrease from a negative balance is still refused outside the POS sale', function () {
    Setting::set('allow_negative_stock', '1', 'sales');
    postPosSale($this, ($this->sale)())->assertSuccessful();

    DB::transaction(function () {
        expect(fn () => app(StockLedgerInterface::class)->move($this->oil->id, -1, StockMovementType::WarrantyReplacement, $this->oil))
            ->toThrow(DomainException::class);
    });

    expect((float) $this->oil->fresh()->current_stock)->toBe(-2.0);
});

test('the system audit keeps reporting negative stock while it exists', function () {
    Setting::set('allow_negative_stock', '1', 'sales');
    postPosSale($this, ($this->sale)())->assertSuccessful();

    $this->artisan('system:diagnose', ['--audit' => true])
        ->expectsOutputToContain('منتج برصيد سالب');
});

test('the POS page tells the browser whether selling below zero is on', function () {
    $this->actingAs($this->cashier)->get(route('admin.pos.index'))
        ->assertOk()
        ->assertSee('allowNegativeStock":false', false);

    Setting::set('allow_negative_stock', '1', 'sales');

    $this->actingAs($this->cashier)->get(route('admin.pos.index'))
        ->assertOk()
        ->assertSee('allowNegativeStock":true', false);
});
