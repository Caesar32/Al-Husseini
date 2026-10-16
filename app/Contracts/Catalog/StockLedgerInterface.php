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
     * @return int stock after the move
     */
    public function move(int $productId, int $delta, StockMovementType $type, Model $source, ?int $userId = null, ?float $unitCost = null): int;

    /**
     * Set an absolute stock level, recorded as an Adjustment with delta = new - old.
     *
     * @return int stock after the adjustment
     */
    public function setTo(int $productId, int $newStock, Model $source, ?int $userId = null): int;
}
