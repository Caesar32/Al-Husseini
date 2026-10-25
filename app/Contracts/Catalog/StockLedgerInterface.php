<?php

namespace App\Contracts\Catalog;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;

/**
 * The only writer of products.current_stock. Every change appends a stock_movements row.
 */
interface StockLedgerInterface
{
    /**
     * Apply a signed quantity change. Must run inside DB::transaction; locks the product row.
     *
     * @return int|float stock after the move, normalized to three decimals
     */
    public function move(int $productId, int|float|string $delta, StockMovementType $type, Model $source, ?int $userId = null, ?float $unitCost = null): int|float;

    /**
     * Set an absolute stock level, recorded as an Adjustment with delta = new - old.
     *
     * @return int|float stock after the adjustment, normalized to three decimals
     */
    public function setTo(int $productId, int|float|string $newStock, Model $source, ?int $userId = null): int|float;
}
