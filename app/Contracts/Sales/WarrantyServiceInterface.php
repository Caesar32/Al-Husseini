<?php

namespace App\Contracts\Sales;

use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Pagination\LengthAwarePaginator;

interface WarrantyServiceInterface
{
    public function getPaginatedClaims(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function verifyBatterySerial(string $serialNumber): array;

    public function issueWarranty(int $invoiceItemId, int $customerId, ?int $vehicleId, string $serial, int $months): Warranty;

    public function processInstantClaim(array $data, int $receivedByUserId): WarrantyClaim;

    public function settleClaimWithSupplier(int $claimId, string $action, array $resolutionData, int $userId): WarrantyClaim;
}
