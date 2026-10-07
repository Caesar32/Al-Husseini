<?php

namespace App\Support;

class MoneyHelper
{
    /**
     * Human-readable compact form of a currency amount (e.g. "26.78 مليون ج.م" instead of
     * "26781945.84 ج.م"), alongside the exact pound-by-pound value for a hover tooltip.
     *
     * Used for statistical/summary totals in page headers only — detailed line-item tables
     * (price × quantity rows) keep their precise figures; this is not a general money formatter.
     *
     * @return array{compact: string, exact: string}
     */
    public static function formatCompactCurrency(float $amount): array
    {
        if ($amount >= 1000000) {
            $compact = round($amount / 1000000, 2) . ' مليون ج.م';
        } elseif ($amount >= 1000) {
            $compact = round($amount / 1000, 1) . ' ألف ج.م';
        } else {
            $compact = number_format($amount, 0) . ' ج.م';
        }

        return [
            'compact' => $compact,
            'exact'   => number_format($amount, 2) . ' ج.م',
        ];
    }
}
