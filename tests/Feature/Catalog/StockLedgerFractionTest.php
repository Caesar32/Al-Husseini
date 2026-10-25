<?php

use App\Contracts\Catalog\StockLedgerInterface;
use App\Enums\ProductUnit;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Quantity;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->ledger = app(StockLedgerInterface::class);
    $this->user = User::firstOrFail();
    $category = Category::firstOrCreate(['slug' => 'ledger-fractional'], ['name' => 'اختبار دفتر الكسور']);

    $this->piece = Product::create([
        'category_id' => $category->id,
        'sku' => 'LEDGER-FRAC-P',
        'name' => 'قطعة دفتر',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Piece->value,
        'cost_price' => 10,
        'retail_price' => 20,
        'current_stock' => 5,
        'is_battery' => false,
        'is_active' => true,
    ]);
    $this->liter = Product::create([
        'category_id' => $category->id,
        'sku' => 'LEDGER-FRAC-L',
        'name' => 'لتر دفتر',
        'brand' => 'اختبار',
        'unit' => ProductUnit::Liter->value,
        'cost_price' => 10,
        'retail_price' => 20,
        'current_stock' => '2.5',
        'is_battery' => false,
        'is_active' => true,
    ]);
});

test('ledger rejects fractional movement on a piece product', function () {
    expect(fn () => DB::transaction(fn () => $this->ledger->move(
        $this->piece->id,
        '0.5',
        StockMovementType::Sale,
        $this->user
    )))->toThrow(DomainException::class);

    expect(StockMovement::where('product_id', $this->piece->id)->exists())->toBeFalse();
});

test('ledger rejects a move that would make stock negative', function () {
    expect(fn () => DB::transaction(fn () => $this->ledger->move(
        $this->liter->id,
        '-2.501',
        StockMovementType::Sale,
        $this->user
    )))->toThrow(DomainException::class);

    expect($this->liter->fresh()->current_stock)->toBe(2.5)
        ->and(StockMovement::where('product_id', $this->liter->id)->exists())->toBeFalse();
});

test('setTo records fractional stock and its milli-correct adjustment', function () {
    $after = DB::transaction(fn () => $this->ledger->setTo(
        $this->liter->id,
        '7.25',
        $this->user
    ));
    $movement = StockMovement::where('product_id', $this->liter->id)->sole();

    expect($after)->toBe(7.25)
        ->and($this->liter->fresh()->current_stock)->toBe(7.25)
        ->and($movement->quantity_delta)->toBe(4.75)
        ->and($movement->stock_after)->toBe(7.25)
        ->and(Quantity::toMilli($movement->quantity_delta))->toBe(4750)
        ->and(Quantity::toMilli($movement->stock_after))->toBe(7250);
});

test('ledger refuses stock changes outside a transaction', function () {
    DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);

    expect(fn () => $this->ledger->move(
        $this->liter->id,
        '-0.5',
        StockMovementType::Sale,
        $this->user
    ))->toThrow(LogicException::class);
});
