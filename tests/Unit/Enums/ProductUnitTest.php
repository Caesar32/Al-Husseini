<?php

use App\Enums\ProductUnit;

test('product units expose their Arabic labels and quantity behavior', function (ProductUnit $unit, string $label, bool $allowsFraction, int|float $increment) {
    expect($unit->label())->toBe($label)
        ->and($unit->allowsFraction())->toBe($allowsFraction)
        ->and($unit->stepperIncrement())->toBe($increment);
})->with([
    'piece' => [ProductUnit::Piece, 'قطعة', false, 1],
    'liter' => [ProductUnit::Liter, 'لتر', true, 0.25],
    'kg' => [ProductUnit::Kg, 'كجم', true, 0.25],
    'meter' => [ProductUnit::Meter, 'متر', true, 0.25],
]);
