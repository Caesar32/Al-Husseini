<?php

use App\Support\Quantity;

test('converts supported quantity inputs to milli-units without float parsing for strings', function () {
    expect(Quantity::toMilli(null))->toBe(0)
        ->and(Quantity::toMilli(3))->toBe(3000)
        ->and(Quantity::toMilli('3.5'))->toBe(3500)
        ->and(Quantity::toMilli('3.5000'))->toBe(3500)
        ->and(Quantity::toMilli(-1.25))->toBe(-1250)
        ->and(Quantity::toMilli(0.1 + 0.2))->toBe(300);
});

test('rejects invalid quantity precision and non-finite floats', function (mixed $value) {
    expect(fn () => Quantity::toMilli($value))->toThrow(\InvalidArgumentException::class);
})->with([
    '3.5001',
    '1e3',
    '',
    'abc',
    INF,
    NAN,
]);

test('converts milli-units back to whole integers or normalized fractions', function () {
    expect(Quantity::fromMilli(4000))->toBe(4)
        ->and(Quantity::fromMilli(125))->toBe(0.125)
        ->and(Quantity::fromMilli(-1500))->toBe(-1.5)
        ->and(Quantity::normalize('3.5000'))->toBe(3.5)
        ->and(Quantity::isWhole(4))->toBeTrue()
        ->and(Quantity::isWhole('4.000'))->toBeTrue()
        ->and(Quantity::isWhole('-1.5'))->toBeFalse();
});

test('reports valid precision without throwing for supported and unsupported values', function () {
    expect(Quantity::hasValidPrecision(4))->toBeTrue()
        ->and(Quantity::hasValidPrecision(0.1 + 0.2))->toBeTrue()
        ->and(Quantity::hasValidPrecision('3.5000'))->toBeTrue()
        ->and(Quantity::hasValidPrecision('3.5001'))->toBeFalse()
        ->and(Quantity::hasValidPrecision('1e3'))->toBeFalse()
        ->and(Quantity::hasValidPrecision(null))->toBeFalse()
        ->and(Quantity::hasValidPrecision(''))->toBeFalse()
        ->and(Quantity::hasValidPrecision('abc'))->toBeFalse()
        ->and(Quantity::hasValidPrecision(INF))->toBeFalse()
        ->and(Quantity::hasValidPrecision(NAN))->toBeFalse()
        ->and(Quantity::hasValidPrecision([]))->toBeFalse();
});

test('calculates line totals with one half-up rounding step in piasters', function () {
    expect(Quantity::lineTotal('3.5', '120.00'))->toBe(420.0)
        ->and(Quantity::lineTotal('0.333', '10'))->toBe(3.33)
        ->and(Quantity::lineTotal('0.125', '0.10'))->toBe(0.01)
        ->and(Quantity::lineTotal(2, '99.99'))->toBe(199.98)
        ->and(Quantity::lineTotal('-0.125', '0.10'))->toBe(-0.01);
});

test('rejects unit prices with non-zero precision beyond piasters', function () {
    expect(fn () => Quantity::lineTotal(1, '0.101'))->toThrow(\InvalidArgumentException::class);
});

test('guards line total integer multiplication overflow', function () {
    expect(fn () => Quantity::lineTotal(1000, intdiv(PHP_INT_MAX, 100)))->toThrow(\OverflowException::class);
});

test('formats normalized quantities without separators or trailing zeroes', function () {
    expect(Quantity::format(3.5))->toBe('3.5')
        ->and(Quantity::format(4))->toBe('4')
        ->and(Quantity::format('0.250'))->toBe('0.25')
        ->and(Quantity::format(-1.5))->toBe('-1.5')
        ->and(Quantity::format(null))->toBe('0');
});

test('refuses floats too large to scale instead of returning an undefined integer', function () {
    expect(fn () => Quantity::toMilli(1e20))->toThrow(\OverflowException::class)
        ->and(fn () => Quantity::toMilli(-1e20))->toThrow(\OverflowException::class)
        ->and(Quantity::hasValidPrecision(1e20))->toBeFalse()
        ->and(Quantity::toMilli(999999999.999))->toBe(Quantity::MAX_MILLI);
});

test('explains why a quantity is invalid with one shared set of messages', function (mixed $value, bool $wholeOnly, ?string $expected) {
    expect(Quantity::validationError($value, $wholeOnly, 'فلتر'))->toBe($expected);
})->with([
    'liter fraction' => ['2.5', false, null],
    'whole piece' => [3, true, null],
    'padded string' => [' 1.250 ', false, null],
    'not a number' => ['abc', false, 'الكمية يجب أن تكون رقماً.'],
    'array' => [[1], false, 'الكمية يجب أن تكون رقماً.'],
    'exponent string' => ['1e3', false, 'الكمية يجب أن تكون رقماً عشرياً عادياً.'],
    'too precise' => ['0.0001', false, 'الكمية تقبل 3 أرقام عشرية كحد أقصى.'],
    'huge float' => [1e20, false, 'الكمية تقبل 3 أرقام عشرية كحد أقصى.'],
    'zero' => [0, false, 'الكمية يجب أن تكون أكبر من صفر.'],
    'negative' => ['-1', false, 'الكمية يجب أن تكون أكبر من صفر.'],
    'above column limit' => ['1000000000', false, 'الكمية أكبر من الحد المسموح.'],
    'fraction of a piece' => ['1.5', true, 'الصنف (فلتر) يُباع بالقطعة؛ الكمية يجب أن تكون عدداً صحيحاً.'],
]);

test('milliOrFail returns milli-units or throws the validation message', function () {
    expect(Quantity::milliOrFail('2.5'))->toBe(2500)
        ->and(fn () => Quantity::milliOrFail('1.5', true, 'فلتر'))
        ->toThrow(\DomainException::class, 'الصنف (فلتر) يُباع بالقطعة؛ الكمية يجب أن تكون عدداً صحيحاً.');
});
