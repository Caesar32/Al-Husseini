<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ScrapPricingTier extends Model
{
    protected $fillable = [
        'capacity_min_ah',
        'capacity_max_ah',
        'tier_name',
        'default_scrap_price',
        'is_active',
    ];

    protected $casts = [
        'capacity_min_ah'     => 'integer',
        'capacity_max_ah'     => 'integer',
        'default_scrap_price' => 'decimal:2',
        'is_active'           => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * استرجاع كائن شريحة التسعير لسعة أمبير محددة
     */
    public static function findPriceForCapacity(int $capacityAh): ?self
    {
        return static::active()
            ->where('capacity_min_ah', '<=', $capacityAh)
            ->where('capacity_max_ah', '>=', $capacityAh)
            ->first();
    }

    /**
     * استرجاع السعر المرجعي الصارم لسعة أمبير محددة
     */
    public static function getPriceForCapacity(int $capacityAh): ?float
    {
        $tier = static::findPriceForCapacity($capacityAh);

        return $tier ? (float) $tier->default_scrap_price : null;
    }
}
