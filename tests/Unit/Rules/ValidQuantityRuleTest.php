<?php

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Rules\ValidQuantityRule;

function quantityRuleErrors(ValidQuantityRule $rule, mixed $value): array
{
    $errors = [];
    $rule->validate('items.0.quantity', $value, function (string $message) use (&$errors) {
        $errors[] = $message;
    });

    return $errors;
}

test('accepts fractions without a product and rejects invalid quantities', function () {
    $rule = new ValidQuantityRule();

    expect(quantityRuleErrors($rule, '0.5'))->toBe([])
        ->and(quantityRuleErrors($rule, '0'))->toBe(['الكمية يجب أن تكون أكبر من صفر.'])
        ->and(quantityRuleErrors($rule, '1.2345'))->toBe(['الكمية تقبل 3 أرقام عشرية كحد أقصى.']);
});

test('requires whole quantities for a piece product only', function () {
    $piece = new Product(['name' => 'فلتر', 'unit' => ProductUnit::Piece->value]);
    $liter = new Product(['name' => 'زيت', 'unit' => ProductUnit::Liter->value]);

    expect(quantityRuleErrors(new ValidQuantityRule($piece), '1.5'))
        ->toBe(['الصنف (فلتر) يُباع بالقطعة؛ الكمية يجب أن تكون عدداً صحيحاً.'])
        ->and(quantityRuleErrors(new ValidQuantityRule($piece), 2))->toBe([])
        ->and(quantityRuleErrors(new ValidQuantityRule($liter), '1.5'))->toBe([]);
});
