<?php

namespace App\Contracts\Sales;

use App\Models\CreditLedgerEntry;
use App\Models\Invoice;
use Illuminate\Pagination\LengthAwarePaginator;

interface PosOrderServiceInterface
{
    public function getPaginatedInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function processPosSale(array $data, int $cashierUserId): Invoice;

    public function processSalesReturn(int $invoiceId, array $items, string $reason, int $cashierUserId): Invoice;

    public function calculateScrapDeduction(int $capacityAh, int $quantity = 1): float;

    public function getDailyCashierSummary(int $branchId, string $date): array;

    /**
     * تحصيل دفعة من عميل آجل وتحديث رصيده ودفتر الأستاذ.
     *
     * @throws \DomainException إذا كان المبلغ يتجاوز الرصيد المدين أو لم يكن العميل مديناً
     */
    public function settleCustomerDebt(
        int    $customerId,
        float  $amount,
        string $paymentMethod,
        int    $collectedBy,
        ?string $receiptNumber = null,
        ?string $notes         = null
    ): CreditLedgerEntry;
}
