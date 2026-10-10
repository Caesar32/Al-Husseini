<?php

namespace App\Services\Catalog;

use App\Contracts\Catalog\ProductServiceInterface;
use App\Models\Product;
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
        'reorder_threshold', 'is_battery', 'is_active',
    ];

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

        $stat = fn(string $slug, string $field) => (int) ($rows[$slug]->{$field} ?? 0);

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

            // fresh(): return database defaults (voltage, terminal_type, ...) for omitted columns.
            return Product::create($attributes)->fresh('category:id,name,slug');
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($this->onlyEditable($data));

            return $product->fresh('category:id,name,slug');
        });
    }

    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ((int) $product->current_stock > 0) {
                throw new DomainException('لا يمكن حذف صنف له رصيد بالمخزن (' . $product->current_stock . '). يمكنك إيقاف تفعيله بدلاً من ذلك.');
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
        foreach (['voltage', 'terminal_type', 'reorder_threshold'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] === null) {
                unset($attributes[$column]);
            }
        }

        return $attributes;
    }
}
