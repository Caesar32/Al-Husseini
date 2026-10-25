<?php

namespace App\Models;

use App\Casts\QuantityCast;
use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only journal of products.current_stock changes, written only by StockLedgerService.
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'type' => StockMovementType::class,
        'quantity_delta' => QuantityCast::class,
        'stock_after' => QuantityCast::class,
        'unit_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
