<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Casts\QuantityCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'product_id',
        'quantity',
        'returned_quantity',
        'unit_cost_price',
        'total_cost_price',
        'batch_number',
        'production_date',
    ];

    /** Stock this quantity moves, in thousandths: a line from before a liter conversion counts in packages. */
    public function stockMilli(int $quantityMilli): int
    {
        return (int) round($quantityMilli * (float) ($this->stock_factor ?? 1));
    }

    /** The unit this line's quantity is in, which stays the package for a line from before a conversion. */
    public function quantityUnitLabel(): string
    {
        if ((float) ($this->stock_factor ?? 1) > 1 && $this->product) {
            return $this->product->packageLabel();
        }

        return ($this->product?->unit ?? ProductUnit::Piece)->label();
    }

    protected function casts(): array
    {
        return [
            'quantity' => QuantityCast::class,
            'returned_quantity' => QuantityCast::class,
            'unit_cost_price' => 'decimal:2',
            'total_cost_price' => 'decimal:2',
            'production_date' => 'date',
        ];
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
