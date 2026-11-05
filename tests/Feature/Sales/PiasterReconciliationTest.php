<?php

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\ScrapBatteriesInventory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->pos = app(PosOrderServiceInterface::class);
    $this->cashier = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->sale = [
        'branch_id' => Branch::firstOrFail()->id,
        'technician_id' => Employee::firstOrFail()->id,
    ];
    $this->product = Product::where('is_battery', false)->first() ?? Product::firstOrFail();
    $this->product->update(['current_stock' => 50, 'is_battery' => false]);
});

test('a refund of exactly one piaster reaches the payments and the paid amount', function () {
    $invoice = $this->pos->processPosSale($this->sale + [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 0.01]],
        'payments' => [['method' => 'cash', 'amount' => 0.01]],
    ], $this->cashier->id);

    $this->pos->processSalesReturn($invoice->id, [['invoice_item_id' => $invoice->items->first()->id, 'quantity' => 1]], 'اختبار', $this->cashier->id);

    $invoice->refresh();
    expect((float) $invoice->refunded_amount)->toBe(0.01)
        ->and((float) $invoice->paid_amount)->toBe(0.0)
        ->and((float) InvoicePayment::where('invoice_id', $invoice->id)->sum('amount'))->toBe(0.0);
});

test('scrap values on stock add up to the deduction taken on the invoice', function (float $deduction, int $count, array $values) {
    $invoice = $this->pos->processPosSale($this->sale + [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 3000]],
        'has_scrap' => true,
        'scrap_capacity_ah' => 70,
        'scrap_count' => $count,
        'scrap_deduction_amount' => $deduction,
        'payments' => [['method' => 'cash', 'amount' => round(3000 - $deduction, 2)]],
    ], $this->cashier->id);

    $stored = ScrapBatteriesInventory::where('invoice_id', $invoice->id)->orderBy('id')->pluck('scrap_value')
        ->map(fn ($value) => (float) $value)->all();

    expect($stored)->toBe($values)
        ->and(round(array_sum($stored), 2))->toBe((float) $invoice->scrap_deduction_amount);
})->with([
    'remainder on the last battery' => [100.0, 3, [33.33, 33.33, 33.34]],
    'even split' => [150.0, 3, [50.0, 50.0, 50.0]],
    'one battery' => [99.99, 1, [99.99]],
]);
