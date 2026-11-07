<?php

namespace App\Services\Catalog;

use App\Contracts\Catalog\ProductServiceInterface;
use App\Contracts\Catalog\StockLedgerInterface;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Support\Quantity;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductService implements ProductServiceInterface
{
    /**
     * Fields a user may set through the catalog module.
     * current_stock is deliberately absent: stock only moves through purchases, sales,
     * returns and warranty claims. cost_price is the weighted average cost maintained by
     * purchases, so it can only be given as the initial cost when a product is created.
     */
    private const EDITABLE_FIELDS = [
        'category_id', 'sku', 'barcode', 'name', 'brand', 'capacity_ah', 'voltage',
        'terminal_type', 'warranty_months', 'retail_price', 'wholesale_price',
        'reorder_threshold', 'unit', 'package_size', 'is_battery', 'is_active',
    ];

    public function __construct(private StockLedgerInterface $stock) {}

    public function getPaginatedProducts(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Product::with('category:id,name,slug');

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('brand', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhere('barcode', 'like', "%{$term}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->whereHas('category', fn($q) => $q->where('slug', $filters['category']));
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function getCatalogStats(): array
    {
        $rows = Product::query()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->groupBy('categories.slug')
            ->selectRaw('categories.slug as slug, COUNT(*) as products_count, COALESCE(SUM(products.current_stock), 0) as stock_total')
            ->get()
            ->keyBy('slug');

        $stat = function (string $slug, string $field): int|float {
            $value = $rows[$slug]->{$field} ?? 0;

            return $field === 'stock_total' ? Quantity::normalize($value) : (int) $value;
        };

        return [
            'batteries_stock'  => $stat('batteries', 'stock_total'),
            'batteries_count'  => $stat('batteries', 'products_count'),
            'oils_stock'       => $stat('oils', 'stock_total'),
            'greases_stock'    => $stat('greases', 'stock_total'),
            'services_count'   => $stat('services', 'products_count'),
            'low_stock_count'  => Product::active()->lowStock()->count(),
        ];
    }

    public function search(string $term, int $limit = 20): Collection
    {
        $term = trim($term);

        return Product::with('category:id,name,slug')
            ->where('is_active', true)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('brand', 'like', "%{$term}%")
                      ->orWhere('sku', 'like', "%{$term}%")
                      ->orWhere('barcode', $term);
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $attributes = $this->onlyEditable($data);
            $attributes['cost_price'] = round((float) ($data['cost_price'] ?? 0), 2);
            $attributes['current_stock'] = 0;
            $attributes['unit'] ??= ProductUnit::Piece->value;

            // fresh(): return database defaults (voltage, terminal_type, ...) for omitted columns.
            return Product::create($attributes)->fresh('category:id,name,slug');
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $attributes = $this->onlyEditable($data);
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            if (($attributes['unit'] ?? null) === ProductUnit::Piece->value && $product->allowsFraction()) {
                $this->assertNoFractionalQuantities($product);
            }

            // A package size on a product still sold by the piece means "these are sealed packages,
            // sell them by the liter": its figures change meaning, so the form's prices do not apply.
            $size = (float) ($attributes['package_size'] ?? 0);
            if ($size > 0 && ($attributes['unit'] ?? null) === ProductUnit::Liter->value && $product->unit !== ProductUnit::Liter) {
                $product->update(array_diff_key($attributes, array_flip(['unit', 'package_size', 'retail_price', 'wholesale_price', 'reorder_threshold'])));
                $this->convertToLiters($product->fresh(), $size);

                return $product->fresh('category:id,name,slug');
            }

            $product->update($attributes);

            return $product->fresh('category:id,name,slug');
        });
    }

    /**
     * Turns a product sold in sealed packages into a liter product. Stock moves through the ledger;
     * prices and cost become per liter. Old document lines keep their quantity and amounts, with a
     * stock factor so that returning one still moves the liters it really was.
     */
    public function convertToLiters(Product $product, float $packageSize, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($product, $packageSize, $userId) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($product->is_battery) {
                throw new DomainException('البطاريات تُباع بالقطعة فقط.');
            }
            if ($product->unit !== ProductUnit::Piece) {
                throw new DomainException("الصنف ({$product->name}) ليس بالقطعة، فلا يمكن تحويله.");
            }
            if ($packageSize <= 0) {
                throw new DomainException('حجم العبوة يجب أن يكون أكبر من الصفر.');
            }
            $stockMilli = Quantity::toMilli($product->current_stock);
            if ($stockMilli < 0) {
                throw new DomainException("رصيد الصنف ({$product->name}) سالب: صحّحه بالجرد قبل التحويل.");
            }

            $product->update([
                'unit' => ProductUnit::Liter->value,
                'package_size' => $packageSize,
                'retail_price' => round((float) $product->retail_price / $packageSize, 2),
                'wholesale_price' => round((float) $product->wholesale_price / $packageSize, 2),
                'cost_price' => round((float) $product->cost_price / $packageSize, 2),
                'reorder_threshold' => Quantity::fromMilli((int) round(Quantity::toMilli($product->reorder_threshold) * $packageSize)),
            ]);

            foreach (['invoice_items', 'purchase_invoice_items'] as $table) {
                DB::table($table)->where('product_id', $product->id)->update(['stock_factor' => $packageSize]);
            }
            DB::table('supplier_products')->where('product_id', $product->id)->update([
                'last_purchase_price' => DB::raw('ROUND(last_purchase_price / '.$packageSize.', 2)'),
            ]);

            if ($stockMilli > 0) {
                $this->stock->setTo($product->id, Quantity::fromMilli((int) round($stockMilli * $packageSize)), $product, $userId ?? auth()->id());
            }

            return $product->fresh('category:id,name,slug');
        });
    }

    /**
     * A product may only become a piece product when its stock and every sale/purchase line are
     * whole numbers; otherwise returns of those lines and later stock moves would be rejected.
     */
    private function assertNoFractionalQuantities(Product $product): void
    {
        $fractional = fn (string $column) => "ROUND({$column}, 0) <> {$column}";

        if (Quantity::toMilli($product->current_stock) % Quantity::SCALE !== 0) {
            throw new DomainException('لا يمكن تحويل الصنف إلى البيع بالقطعة ورصيده كسري (' . Quantity::format($product->current_stock) . ').');
        }

        $hasFractionalLines = $product->invoiceItems()
                ->where(fn ($q) => $q->whereRaw($fractional('quantity'))->orWhereRaw($fractional('returned_quantity')))
                ->exists()
            || $product->purchaseInvoiceItems()
                ->where(fn ($q) => $q->whereRaw($fractional('quantity'))->orWhereRaw($fractional('returned_quantity')))
                ->exists();

        if ($hasFractionalLines) {
            throw new DomainException('لا يمكن تحويل الصنف إلى البيع بالقطعة: له فواتير بيع أو شراء بكميات كسرية.');
        }
    }

    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            // Compared in thousandths: an (int) cast would let a product holding 0.5 L be deleted.
            if (Quantity::toMilli($product->current_stock) > 0) {
                throw new DomainException('لا يمكن حذف صنف له رصيد بالمخزن (' . Quantity::format($product->current_stock) . '). يمكنك إيقاف تفعيله بدلاً من ذلك.');
            }

            if ($product->invoiceItems()->exists() || $product->purchaseInvoiceItems()->exists() || $product->warrantyClaims()->exists()) {
                throw new DomainException('لا يمكن حذف صنف مرتبط بفواتير بيع أو شراء أو مطالبات ضمان. يمكنك إيقاف تفعيله بدلاً من ذلك.');
            }

            $product->delete();
        });
    }

    private function onlyEditable(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));

        // These columns are NOT NULL with database defaults: an empty value means "keep / use the default".
        foreach (['voltage', 'terminal_type', 'reorder_threshold', 'unit'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] === null) {
                unset($attributes[$column]);
            }
        }

        // Batteries are sold by the piece; the request rejects another unit only when one is sent.
        if (! empty($attributes['is_battery'])) {
            $attributes['unit'] = ProductUnit::Piece->value;
        }

        // A package size only means something for a liter product.
        if (array_key_exists('unit', $attributes) && $attributes['unit'] !== ProductUnit::Liter->value) {
            $attributes['package_size'] = null;
        }

        return $attributes;
    }
}
