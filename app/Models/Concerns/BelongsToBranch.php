<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;

/**
 * Applies BranchScope to reads and forces the branch of new records for branch-confined users,
 * so a client-supplied branch_id cannot place data in another branch.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope());

        static::creating(function ($model) {
            $branchId = BranchScope::restrictedBranchId();

            if ($branchId !== null) {
                $model->branch_id = $branchId;
            }
        });
    }
}
