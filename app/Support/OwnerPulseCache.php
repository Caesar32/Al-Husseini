<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

final class OwnerPulseCache
{
    /**
     * Cache TTL in seconds.
     * Short flat TTLs are used because CACHE_STORE=database does not support Cache::tags().
     */
    public const LIVE_TTL = 15;     // 15 seconds
    public const PERIODS_TTL = 30;  // 30 seconds

    /**
     * Remember live pulse data.
     */
    public static function live(?int $branchId, Closure $resolver): array
    {
        return Cache::remember(self::key('live', $branchId), self::LIVE_TTL, $resolver);
    }

    /**
     * Remember periods metric data.
     */
    public static function periods(string $period, ?int $branchId, Closure $resolver): array
    {
        return Cache::remember(self::key("periods:{$period}", $branchId), self::PERIODS_TTL, $resolver);
    }

    /**
     * Invalidate pulse cache for a branch (or all branches).
     */
    public static function forgetLive(?int $branchId = null): void
    {
        Cache::forget(self::key('live', $branchId));
        if ($branchId !== null) {
            Cache::forget(self::key('live', null));
        }
    }

    /**
     * Construct flat cache key.
     */
    private static function key(string $suffix, ?int $branchId): string
    {
        return 'owner:pulse:' . $suffix . ':branch:' . ($branchId !== null ? (string)$branchId : 'all');
    }
}
