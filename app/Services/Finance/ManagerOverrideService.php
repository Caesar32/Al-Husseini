<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ManagerOverrideService
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 300;

    /**
     * Validate a manager override code against the configured secret.
     *
     * Accepted sources, in order: finance.manager_override_hash (bcrypt/argon hash, preferred)
     * and finance.manager_override_code (plain secret compared in constant time).
     * There is no built-in default and no fallback: when neither is configured every code
     * is rejected (fail closed). Attempts are rate limited per user and IP.
     */
    public function isValid(?string $code, ?string $rateLimitKey = null): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        $key = $rateLimitKey ?? $this->defaultRateLimitKey();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        $configuredHash = config('finance.manager_override_hash');
        $configuredCode = config('finance.manager_override_code');

        $valid = (!empty($configuredHash) && Hash::check($code, (string) $configuredHash))
            || (!empty($configuredCode) && hash_equals((string) $configuredCode, $code));

        if ($valid) {
            RateLimiter::clear($key);

            return true;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return false;
    }

    public function isConfigured(): bool
    {
        return !empty(config('finance.manager_override_hash')) || !empty(config('finance.manager_override_code'));
    }

    private function defaultRateLimitKey(): string
    {
        return 'manager-override:' . (auth()->id() ?? 'guest') . '|' . request()->ip();
    }
}
