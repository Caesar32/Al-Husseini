<?php

use App\Enums\ProductUnit;
use App\Http\Resources\Owner\InventoryAlertResource;
use App\Http\Resources\Owner\InvoiceItemResource;
use App\Http\Resources\Owner\ReturnResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Http\Request;

// The owner app reads liter/kg quantities as decimals; whole quantities stay JSON integers.

test('invoice item resource keeps fractional quantities', function () {
    $item = (new InvoiceItem)->forceFill([
        'id' => 1,
        'product_id' => 1,
        'quantity' => '2.500',
        'returned_quantity' => '0.500',
        'unit_price' => 120.5,
        'total_price' => 301.25,
    ]);
    $item->setRelation('product', new Product(['name' => 'زيت', 'unit' => ProductUnit::Liter->value]));

    $data = (new InvoiceItemResource($item))->toArray(Request::create('/'));

    expect($data['quantity'])->toBe(2.5)
        ->and($data['returned_quantity'])->toBe(0.5);

    $item->forceFill(['quantity' => '3.000', 'returned_quantity' => '0.000']);
    $data = (new InvoiceItemResource($item))->toArray(Request::create('/'));

    expect($data['quantity'])->toBe(3)
        ->and($data['returned_quantity'])->toBe(0);
});

test('return resource lists fractional returns with an exact refunded total', function () {
    $item = (new InvoiceItem)->forceFill([
        'quantity' => '2.500',
        'returned_quantity' => '0.333',
        'unit_price' => 10,
    ]);
    $item->setRelation('product', new Product(['name' => 'شحم']));
    $kept = (new InvoiceItem)->forceFill(['quantity' => '1.000', 'returned_quantity' => '0.000', 'unit_price' => 50]);
    $kept->setRelation('product', new Product(['name' => 'فلتر']));

    $invoice = (new Invoice)->forceFill(['id' => 7, 'refunded_amount' => 3.33, 'final_amount' => 75]);
    $invoice->setRelation('items', collect([$item, $kept]));

    $returned = array_values((new ReturnResource($invoice))->resolve(Request::create('/'))['returned_items']->all());

    expect($returned)->toHaveCount(1)
        ->and($returned[0]['returned_quantity'])->toBe(0.333)
        ->and($returned[0]['total_refunded'])->toBe(3.33);
});

test('inventory alert resource does not truncate fractional stock to zero', function () {
    $product = (new Product)->forceFill([
        'id' => 9,
        'name' => 'زيت',
        'sku' => 'OIL-1',
        'unit' => ProductUnit::Liter->value,
        'current_stock' => '0.500',
        'reorder_threshold' => '4.000',
        'cost_price' => 80,
        'retail_price' => 120,
        'is_battery' => false,
    ]);

    $data = (new InventoryAlertResource($product))->toArray(Request::create('/'));

    expect($data['current_stock'])->toBe(0.5)
        ->and($data['reorder_threshold'])->toBe(4)
        ->and($data['status'])->toBe('critical_low')
        ->and($data['depletion_ratio'])->toBe(0.13);
});
