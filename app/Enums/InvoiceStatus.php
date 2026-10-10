<?php

namespace App\Enums;

/**
 * Sales invoice statuses. Values must match the invoices.status DB enum
 * (2026_09_21_160008 + 2026_09_30_000001).
 */
enum InvoiceStatus: string
{
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Unpaid = 'unpaid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    /**
     * Statuses whose invoices no longer represent a sale at all. Everything else
     * (including partially_refunded) counts toward sales and revenue, net of refunds.
     *
     * @return list<string>
     */
    public static function nonCountableValues(): array
    {
        return [self::Cancelled->value, self::Refunded->value];
    }

    /**
     * Statuses that can no longer receive a sales return.
     *
     * @return list<string>
     */
    public static function nonReturnableValues(): array
    {
        return [self::Cancelled->value, self::Refunded->value];
    }
}
