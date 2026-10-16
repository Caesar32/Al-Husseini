<?php

use App\Contracts\Catalog\StockLedgerInterface;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->ledger = app(StockLedgerInterface::class);
    $this->user = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->product = Product::create([
        'category_id' => Category::firstOrCreate(['slug' => 'batteries'], ['name' => 'بطاريات'])->id,
        'sku' => 'LEDGER-1',
        'name' => 'منتج دفتر المخزون',
        'brand' => 'B',
        'cost_price' => 100,
        'retail_price' => 150,
        'current_stock' => 10,
    ]);
});

test('move applies signed deltas and journals each one with the stock after it', function () {
    $after1 = DB::transaction(fn () => $this->ledger->move($this->product->id, -2, StockMovementType::Sale, $this->user, $this->user->id));
    $after2 = DB::transaction(fn () => $this->ledger->move($this->product->id, 3, StockMovementType::Purchase, $this->user, $this->user->id, 99.5));

    expect($after1)->toBe(8)
        ->and($after2)->toBe(11)
        ->and($this->product->fresh()->current_stock)->toBe(11);

    $rows = StockMovement::with('source')->where('product_id', $this->product->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->type)->toBe(StockMovementType::Sale)
        ->and($rows[0]->quantity_delta)->toBe(-2)
        ->and($rows[0]->stock_after)->toBe(8)
        ->and($rows[0]->unit_cost)->toBeNull()
        ->and($rows[1]->type)->toBe(StockMovementType::Purchase)
        ->and($rows[1]->quantity_delta)->toBe(3)
        ->and($rows[1]->stock_after)->toBe(11)
        ->and($rows[1]->unit_cost)->toBe('99.50')
        ->and($rows[1]->user_id)->toBe($this->user->id)
        ->and($rows[1]->branch_id)->toBe($this->user->branch_id)
        ->and($rows[1]->source->is($this->user))->toBeTrue();
});

test('setTo records an adjustment with delta new minus old', function () {
    $after = DB::transaction(fn () => $this->ledger->setTo($this->product->id, 4, $this->product));

    $row = StockMovement::sole();
    expect($after)->toBe(4)
        ->and($this->product->fresh()->current_stock)->toBe(4)
        ->and($row->type)->toBe(StockMovementType::Adjustment)
        ->and($row->quantity_delta)->toBe(-6)
        ->and($row->stock_after)->toBe(4)
        ->and($row->branch_id)->toBeNull();
});

test('a stock change outside a transaction is refused', function () {
    // RefreshDatabase keeps the test inside a transaction, so simulate level 0.
    DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);

    expect(fn () => $this->ledger->move($this->product->id, -1, StockMovementType::Sale, $this->user))
        ->toThrow(LogicException::class);
});

test('a rolled back transaction leaves neither a movement nor a stock change', function () {
    try {
        DB::transaction(function () {
            $this->ledger->move($this->product->id, -5, StockMovementType::Sale, $this->user);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(StockMovement::count())->toBe(0)
        ->and($this->product->fresh()->current_stock)->toBe(10);
});
