<?php

namespace App\Services\Catalog;

use App\Contracts\Catalog\StockLedgerInterface;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class StockLedgerService implements StockLedgerInterface
{
    public function move(int $productId, int|float|string $delta, StockMovementType $type, Model $source, ?int $userId = null, ?float $unitCost = null): int|float
    {
        $product = $this->lockProduct($productId);
        $deltaMilli = $this->validatedMilli($product, $delta, 'التغيير');
        $currentMilli = Quantity::toMilli($product->current_stock);
        $afterMilli = $currentMilli + $deltaMilli;

        if ($afterMilli < 0) {
            throw new \DomainException("لا يمكن أن يصبح رصيد الصنف ({$product->name}) سالباً.");
        }

        return $this->write($product, $afterMilli, $deltaMilli, $type, $source, $userId, $unitCost);
    }

    public function setTo(int $productId, int|float|string $newStock, Model $source, ?int $userId = null): int|float
    {
        $product = $this->lockProduct($productId);
        $newStockMilli = $this->validatedMilli($product, $newStock, 'الرصيد الجديد');
        $currentMilli = Quantity::toMilli($product->current_stock);

        if ($newStockMilli < 0) {
            throw new \DomainException('لا يمكن أن يكون رصيد المخزون سالباً.');
        }

        return $this->write(
            $product,
            $newStockMilli,
            $newStockMilli - $currentMilli,
            StockMovementType::Adjustment,
            $source,
            $userId,
            null
        );
    }

    private function lockProduct(int $productId): Product
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Stock changes must run inside DB::transaction.');
        }

        // A soft-deleted product still holds physical stock (e.g. a return of an old sale).
        return Product::withTrashed()->lockForUpdate()->findOrFail($productId);
    }

    private function write(Product $product, int $afterMilli, int $deltaMilli, StockMovementType $type, Model $source, ?int $userId, ?float $unitCost): int|float
    {
        $after = Quantity::fromMilli($afterMilli);
        $delta = Quantity::fromMilli($deltaMilli);

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

    private function validatedMilli(Product $product, int|float|string $value, string $field): int
    {
        if (! Quantity::hasValidPrecision($value)) {
            throw new \DomainException('الكمية تقبل 3 أرقام عشرية كحد أقصى.');
        }

        $milli = Quantity::toMilli($value);
        if (! $product->allowsFraction() && $milli % Quantity::SCALE !== 0) {
            throw new \DomainException("الصنف ({$product->name}) يُباع بالقطعة؛ {$field} يجب أن يكون عدداً صحيحاً.");
        }

        return $milli;
    }
}
