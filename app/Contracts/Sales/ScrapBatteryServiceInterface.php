<?php

namespace App\Contracts\Sales;

use Illuminate\Pagination\LengthAwarePaginator;

interface ScrapBatteryServiceInterface
{
    public function getPaginatedInventory(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getInventoryMetrics(int $branchId): array;

    public function dispatchScrapSaleBatch(array $data, int $authorizedByUserId): array;

    public function updatePricingTiers(array $tiers): void;
}
