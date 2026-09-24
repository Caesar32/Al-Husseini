<?php

namespace App\Contracts\Purchases;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SupplierServiceInterface
{
    public function getPaginatedSuppliers(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getAllActiveSuppliers(): Collection;

    public function createSupplier(array $data): Supplier;

    public function updateSupplier(int $supplierId, array $data): Supplier;

    public function deleteSupplier(int $supplierId): bool;

    public function syncSupplierProducts(int $supplierId, array $products): void;

    public function getSupplierStatistics(int $supplierId): array;
}
