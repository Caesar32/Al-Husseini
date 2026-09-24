<?php

namespace App\Contracts\Purchases;

use App\Models\PurchaseInvoice;
use App\Models\SupplierLedgerEntry;
use Illuminate\Pagination\LengthAwarePaginator;

interface PurchaseServiceInterface
{
    public function getPaginatedInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function createDirectPurchase(array $data, int $receivedByUserId): PurchaseInvoice;

    public function recordSupplierPayment(int $supplierId, float $amount, string $method, array $extra = []): SupplierLedgerEntry;

    public function processPurchaseReturn(int $purchaseInvoiceId, array $items, string $reason, int $userId): PurchaseInvoice;

    public function getSupplierLedgerStatement(int $supplierId, ?string $fromDate = null, ?string $toDate = null): array;
}
