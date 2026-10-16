<?php

namespace App\Services\Catalog;

use App\Contracts\Catalog\StockLedgerInterface;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class StockLedgerService implements StockLedgerInterface
{
    public function move(int $productId, int $delta, StockMovementType $type, Model $source, ?int $userId = null, ?float $unitCost = null): int
    {
        $product = $this->lockProduct($productId);

        return $this->write($product, (int) $product->current_stock + $delta, $delta, $type, $source, $userId, $unitCost);
    }

    public function setTo(int $productId, int $newStock, Model $source, ?int $userId = null): int
    {
        $product = $this->lockProduct($productId);

        return $this->write($product, $newStock, $newStock - (int) $product->current_stock, StockMovementType::Adjustment, $source, $userId, null);
    }

    private function lockProduct(int $productId): Product
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Stock changes must run inside DB::transaction.');
        }

        // A soft-deleted product still holds physical stock (e.g. a return of an old sale).
        return Product::withTrashed()->lockForUpdate()->findOrFail($productId);
    }

    private function write(Product $product, int $after, int $delta, StockMovementType $type, Model $source, ?int $userId, ?float $unitCost): int
    {
        $product->current_stock = $after;
        $product->save();

        StockMovement::create([
            'product_id' => $product->id,
            'branch_id' => $source->getAttribute('branch_id'),
            'type' => $type,
            'quantity_delta' => $delta,
            'stock_after' => $after,
            'unit_cost' => $unitCost,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'user_id' => $userId,
        ]);

        return $after;
    }
}
