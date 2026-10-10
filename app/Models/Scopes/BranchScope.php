<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Branch isolation (SEC-08): an authenticated user who belongs to a branch only sees and
 * resolves (route-model binding included) records of that branch.
 *
 * Unrestricted: guests and console/queue contexts (no authenticated user), super-admins, and
 * users without a branch (the codebase already treats a null branch as "all branches", e.g.
 * HR notification recipients). Disable with BRANCH_ISOLATION=false (config app.branch_isolation).
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = self::restrictedBranchId();

        if ($branchId !== null) {
            $builder->where($model->qualifyColumn('branch_id'), $branchId);
        }
    }

    /** The branch the current user is confined to, or null when unrestricted. */
    public static function restrictedBranchId(): ?int
    {
        if (!config('app.branch_isolation', true) || !Auth::hasUser()) {
            return null;
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->branch_id === null || $user->hasRole('super-admin')) {
            return null;
        }

        return (int) $user->branch_id;
    }
}
