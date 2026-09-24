<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Pivot
{
    protected $table = 'supplier_products';
    public $incrementing = true;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'last_purchase_price',
        'min_order_qty',
        'lead_time_days',
        'is_primary_supplier',
        'notes',
    ];

    protected $casts = [
        'last_purchase_price' => 'decimal:2',
        'min_order_qty'       => 'integer',
        'lead_time_days'      => 'integer',
        'is_primary_supplier' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
