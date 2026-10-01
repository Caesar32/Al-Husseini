<?php

namespace App\Services\Sales;

use App\Models\CreditLedgerEntry;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read model for credit (آجل) debt collections, sourced from the credit ledger
 * (entry_type = payment_collection) written by PosOrderService::settleCustomerDebt.
 */
class CreditCollectionService
{
    public function paginateCollections(int $perPage = 20): LengthAwarePaginator
    {
        return CreditLedgerEntry::query()
            ->where('entry_type', 'payment_collection')
            ->with(['customer:id,name,phone', 'collectedByUser:id,name'])
            ->latest('id')
            ->paginate($perPage, ['id', 'customer_id', 'amount', 'receipt_number', 'collected_by', 'notes', 'created_at'], 'payments_page');
    }

    public function collectedBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        return round((float) CreditLedgerEntry::query()
            ->where('entry_type', 'payment_collection')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount'), 2);
    }

    public function countCollections(): int
    {
        return CreditLedgerEntry::where('entry_type', 'payment_collection')->count();
    }
}
