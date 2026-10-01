<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'battery_serial_number',
        'warranty_duration_months',
        'returned_quantity',
    ];

    /**
     * Net line value after returned units (quantity - returned_quantity) at the line price.
     */
    public const NET_LINE_SQL = '(quantity - returned_quantity) * unit_price';

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'warranty_duration_months' => 'integer',
        ];
    }

    public function returnableQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->returned_quantity);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class);
    }
}
