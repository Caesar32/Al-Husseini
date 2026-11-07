<?php

namespace App\Contracts\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductServiceInterface
{
    public function getPaginatedProducts(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function getCatalogStats(): array;

    public function search(string $term, int $limit = 20): Collection;

    public function createProduct(array $data): Product;

    public function updateProduct(Product $product, array $data): Product;

    /** Turns a product sold in sealed packages into a liter product. */
    public function convertToLiters(Product $product, float $packageSize, ?int $userId = null): Product;

    public function deleteProduct(Product $product): void;
}
