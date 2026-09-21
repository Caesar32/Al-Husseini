<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'national_id',
        'credit_limit',
        'current_credit_balance',
        'tier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_credit_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditLedgers(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class)->orderByDesc('id');
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }

    public function scopeInDebt(Builder $query): void
    {
        $query->where('current_credit_balance', '>', 0);
    }

    public function scopeExceededLimit(Builder $query): void
    {
        $query->whereColumn('current_credit_balance', '>', 'credit_limit')
              ->where('credit_limit', '>', 0);
    }
}
