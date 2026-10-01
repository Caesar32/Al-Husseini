<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'quantity' => 'integer',
            'warranty_duration_months' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The warranty issued when this line was sold. A warranty replacement creates a further
     * warranty on the same invoice item, so the original is the oldest one.
     */
    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class)->oldestOfMany();
    }

    /**
     * All warranties on this line: the original and any replacements issued by claims.
     */
    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }

    /**
     * The warranty currently in force for this line (the latest active one).
     */
    public function activeWarranty(): HasOne
    {
        return $this->hasOne(Warranty::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('status', 'active'));
    }
}
