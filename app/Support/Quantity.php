<?php

namespace App\Support;

final class Quantity
{
    public const SCALE = 1000;

    public const DECIMALS = 3;

    /** Largest quantity a decimal(12,3) column holds (999,999,999.999), in milli-units. */
    public const MAX_MILLI = 999_999_999_999;

    private const PRICE_SCALE = 100;

    private function __construct() {}

    public static function toMilli(int|float|string|null $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value)) {
            return self::multiplyInteger($value, self::SCALE);
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new \InvalidArgumentException('Quantity must be finite.');
            }

            // (int) of a float beyond PHP_INT_MAX is undefined, so refuse it before scaling.
            if (abs($value) >= intdiv(PHP_INT_MAX, self::SCALE)) {
                throw new \OverflowException('Decimal value overflow.');
            }

            return (int) round($value * self::SCALE);
        }

        return self::parseScaledString($value, self::SCALE, self::DECIMALS);
    }

    public static function fromMilli(int $milli): int|float
    {
        if ($milli % self::SCALE === 0) {
            return intdiv($milli, self::SCALE);
        }

        return round($milli / self::SCALE, self::DECIMALS);
    }

    public static function normalize(int|float|string|null $value): int|float
    {
        return self::fromMilli(self::toMilli($value));
    }

    public static function isWhole(int|float|string|null $value): bool
    {
        return self::toMilli($value) % self::SCALE === 0;
    }

    public static function hasValidPrecision(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return is_finite($value)
                && abs($value) < intdiv(PHP_INT_MAX, self::SCALE)
                && abs(round($value * self::SCALE) - $value * self::SCALE) < 1e-6;
        }

        if (! is_string($value)) {
            return false;
        }

        try {
            self::parseScaledString($value, self::SCALE, self::DECIMALS);

            return true;
        } catch (\InvalidArgumentException|\OverflowException) {
            return false;
        }
    }

    /**
     * Why a sale/purchase/return quantity is invalid, or null when it is valid: a number above zero,
     * at most 3 decimals, within the column limit, and whole when the item is sold by the piece.
     * The one source of these messages for ValidQuantityRule and the services.
     */
    public static function validationError(mixed $value, bool $wholeOnly = false, string $itemName = ''): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (! (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))) {
            return 'الكمية يجب أن تكون رقماً.';
        }

        if (! self::hasValidPrecision($value)) {
            return is_string($value) && preg_match('/^-?\d+(?:\.\d+)?$/D', $value) !== 1
                ? 'الكمية يجب أن تكون رقماً عشرياً عادياً.'
                : 'الكمية تقبل 3 أرقام عشرية كحد أقصى.';
        }

        $milli = self::toMilli($value);
        if ($milli <= 0) {
            return 'الكمية يجب أن تكون أكبر من صفر.';
        }

        if ($milli > self::MAX_MILLI) {
            return 'الكمية أكبر من الحد المسموح.';
        }

        if ($wholeOnly && $milli % self::SCALE !== 0) {
            return sprintf('الصنف (%s) يُباع بالقطعة؛ الكمية يجب أن تكون عدداً صحيحاً.', $itemName);
        }

        return null;
    }

    /**
     * The quantity in milli-units, or a DomainException carrying validationError()'s message.
     */
    public static function milliOrFail(mixed $value, bool $wholeOnly = false, string $itemName = ''): int
    {
        $error = self::validationError($value, $wholeOnly, $itemName);
        if ($error !== null) {
            throw new \DomainException($error);
        }

        return self::toMilli(is_string($value) ? trim($value) : $value);
    }

    public static function lineTotal(int|float|string $quantity, int|float|string $unitPrice): float
    {
        $milli = self::toMilli($quantity);
        $piasters = self::toPiasters($unitPrice);

        if ($piasters !== 0 && abs($milli) > intdiv(PHP_INT_MAX, abs($piasters))) {
            throw new \OverflowException('Quantity line total overflow.');
        }

        $product = $milli * $piasters;
        $sign = $product < 0 ? -1 : 1;
        $roundedPiasters = $sign * intdiv(abs($product) + 500, self::SCALE);

        return round($roundedPiasters / self::PRICE_SCALE, 2);
    }

    public static function format(int|float|string|null $value): string
    {
        $normalized = self::normalize($value);

        if (is_int($normalized)) {
            return (string) $normalized;
        }

        return rtrim(rtrim(number_format($normalized, self::DECIMALS, '.', ''), '0'), '.');
    }

    private static function toPiasters(int|float|string $value): int
    {
        if (is_string($value)) {
            return self::parseScaledString($value, self::PRICE_SCALE, 2);
        }

        if (is_int($value)) {
            return self::multiplyInteger($value, self::PRICE_SCALE);
        }

        if (! is_finite($value)) {
            throw new \InvalidArgumentException('Unit price must be finite.');
        }

        return (int) round($value * self::PRICE_SCALE);
    }

    private static function parseScaledString(string $value, int $scale, int $decimals): int
    {
        $value = trim($value);

        if (! preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            throw new \InvalidArgumentException('Decimal value is invalid.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        if (strlen($fraction) > $decimals && rtrim(substr($fraction, $decimals), '0') !== '') {
            throw new \InvalidArgumentException('Decimal value has too many significant decimals.');
        }

        $wholeValue = self::parseUnsignedInteger($whole);
        $scaledWhole = self::multiplyInteger($wholeValue, $scale);
        $fractionValue = (int) str_pad(substr($fraction, 0, $decimals), $decimals, '0');
        $result = $scaledWhole + $fractionValue * intdiv($scale, 10 ** $decimals);

        return $negative ? -$result : $result;
    }

    private static function parseUnsignedInteger(string $value): int
    {
        $number = 0;

        foreach (str_split($value) as $digit) {
            $digit = (int) $digit;

            if ($number > intdiv(PHP_INT_MAX - $digit, 10)) {
                throw new \OverflowException('Decimal value overflow.');
            }

            $number = $number * 10 + $digit;
        }

        return $number;
    }

    private static function multiplyInteger(int $value, int $multiplier): int
    {
        if ($value > intdiv(PHP_INT_MAX, $multiplier)
            || $value < -intdiv(PHP_INT_MAX, $multiplier)) {
            throw new \OverflowException('Decimal value overflow.');
        }

        return $value * $multiplier;
    }
}
