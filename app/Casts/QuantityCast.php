<?php

namespace App\Casts;

use App\Support\Quantity;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class QuantityCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : Quantity::normalize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $milli = Quantity::toMilli($value);

        if ($milli % Quantity::SCALE === 0) {
            return intdiv($milli, Quantity::SCALE);
        }

        $sign = $milli < 0 ? '-' : '';
        $absolute = abs($milli);

        return $sign . intdiv($absolute, Quantity::SCALE) . '.'
            . str_pad((string) ($absolute % Quantity::SCALE), Quantity::DECIMALS, '0', STR_PAD_LEFT);
    }
}
