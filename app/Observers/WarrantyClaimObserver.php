<?php

namespace App\Observers;

use App\Models\WarrantyClaim;

class WarrantyClaimObserver
{
    public function creating(WarrantyClaim $claim): void
    {
        if (empty($claim->claim_number)) {
            $prefix = 'CLM-' . now()->format('Ym') . '-';
            $nextSequence = WarrantyClaim::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count() + 1;

            $claim->claim_number = $prefix . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
        }

        if (empty($claim->received_at)) {
            $claim->received_at = now();
        }
    }
}
