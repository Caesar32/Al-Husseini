<?php

namespace App\Contracts\Sales;

use App\Models\Invoice;

interface SalesReturnServiceInterface
{
    /**
     * Return units of an invoice. Each item row is ['invoice_item_id' => int] or
     * ['product_id' => int] (only when that product is on one line), plus 'quantity'.
     */
    public function processSalesReturn(int $invoiceId, array $items, string $reason, int $cashierUserId): Invoice;
}
