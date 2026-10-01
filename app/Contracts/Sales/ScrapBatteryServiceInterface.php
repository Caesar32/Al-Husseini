<?php

namespace App\Contracts\Sales;

use Illuminate\Pagination\LengthAwarePaginator;

interface ScrapBatteryServiceInterface
{
    public function getPaginatedInventory(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * In-stock scrap metrics for one branch, or for all branches when $branchId is null.
     */
    public function getInventoryMetrics(?int $branchId): array;

    public function dispatchScrapSaleBatch(array $data, int $authorizedByUserId): array;

    public function updatePricingTiers(array $tiers): void;
}
