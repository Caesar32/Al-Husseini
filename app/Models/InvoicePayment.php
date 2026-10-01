<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_method',
        'amount',
        'transaction_reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Payments of invoices that still count as sales. Partially refunded invoices are
     * included: their refund is already recorded as a negative payment, so the sum is net.
     */
    public function scopeActive($query)
    {
        return $query->whereHas('invoice', fn($q) => $q->countable());
    }

    public function scopeCash($query)
    {
        return $query->where('payment_method', '!=', 'credit');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
