<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'company_name',
        'phone',
        'alt_phone',
        'email',
        'tax_number',
        'commercial_register',
        'address',
        'credit_limit',
        'current_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class)->orderByDesc('id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeWithBalanceDue(Builder $query): void
    {
        $query->where('current_balance', '>', 0);
    }
}
