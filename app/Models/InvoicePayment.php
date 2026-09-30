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

    public function scopeActive($query)
    {
        return $query->whereHas('invoice', fn($q) => $q->whereNotIn('status', ['cancelled', 'refunded', 'partially_refunded']));
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
