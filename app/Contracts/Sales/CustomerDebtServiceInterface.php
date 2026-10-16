<?php

namespace App\Contracts\Sales;

use App\Models\CreditLedgerEntry;

interface CustomerDebtServiceInterface
{
    /**
     * تحصيل دفعة من عميل آجل وتحديث رصيده ودفتر الأستاذ.
     *
     * @throws \DomainException إذا كان المبلغ يتجاوز الرصيد المدين أو لم يكن العميل مديناً
     */
    public function settleCustomerDebt(
        int $customerId,
        float $amount,
        string $paymentMethod,
        int $collectedBy,
        ?string $receiptNumber = null,
        ?string $notes = null
    ): CreditLedgerEntry;
}
