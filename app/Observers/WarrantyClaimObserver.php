<?php

namespace App\Observers;

use App\Models\WarrantyClaim;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;

class WarrantyClaimObserver
{
    public function creating(WarrantyClaim $claim): void
    {
        if (empty($claim->claim_number)) {
            // Lock-protected monthly sequence (replaces "count of this month's claims + 1",
            // which collided under concurrency or after deletions).
            $prefix = 'CLM-' . now()->format('Ym');
            $numbers = app(DocumentNumberService::class);

            $claim->claim_number = DB::transactionLevel() > 0
                ? $numbers->nextFormatted($prefix, 4)
                : DB::transaction(fn () => $numbers->nextFormatted($prefix, 4));
        }

        if (empty($claim->received_at)) {
            $claim->received_at = now();
        }
    }
}
