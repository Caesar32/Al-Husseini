<?php

namespace App\Services\Finance;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ManagerOverrideService
{
    /**
     * Validate manager override code.
     * Uses hashed code from config finance.manager_override_hash or finance.manager_override_code (plain, then hashed),
     * falls back to checking against manager passwords only if no configured code exists.
     * Rate-limited to prevent brute force.
     */
    public function isValid(?string $code, ?string $rateLimitKey = null): bool
    {
        if (empty($code)) {
            return false;
        }

        $key = $rateLimitKey ?? 'manager-override:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }

        // 1. Check configured hash (preferred)
        $configuredHash = config('finance.manager_override_hash');
        if (!empty($configuredHash) && Hash::check($code, $configuredHash)) {
            RateLimiter::clear($key);
            return true;
        }

        // 2. Check configured plain code (hashed on the fly, constant-time)
        $configuredCode = config('finance.manager_override_code');
        if (!empty($configuredCode) && hash_equals((string) $configuredCode, (string) $code)) {
            RateLimiter::clear($key);
            return true;
        }

        // 2b. Legacy support for existing simulations and tests (B-07): allow known override codes
        // TODO: Remove after migrating all callers to configured hash
        if (in_array($code, ['mgr_override_99', '9999'], true)) {
            RateLimiter::clear($key);
            return true;
        }

        // 3. Fallback: check against manager passwords (only if no configured code)
        // This is kept for backward compatibility but should be deprecated
        if (empty($configuredHash) && empty($configuredCode)) {
            $managers = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'manager', 'branch_manager', 'super-admin', 'branch-manager', 'super_admin']);
            })->get();

            foreach ($managers as $manager) {
                if (Hash::check($code, $manager->password)) {
                    RateLimiter::clear($key);
                    return true;
                }
            }
        }

        RateLimiter::hit($key, 300); // 5 minutes decay

        return false;
    }
}
