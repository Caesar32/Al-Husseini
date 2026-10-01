<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use \App\Models\Concerns\BelongsToBranch;

    protected $fillable = [
        'invoice_number',
        'branch_id',
        'customer_id',
        'customer_vehicle_id',
        'technician_id',
        'cashier_id',
        'subtotal',
        'discount_amount',
        'scrap_deduction_amount',
        'tax_amount',
        'final_amount',
        'paid_amount',
        'remaining_amount',
        'refunded_amount',
        'payment_method',
        'status',
        'notes',
        'idempotency_key',
    ];

    /**
     * Net sale value after returns. Used by every sales/revenue aggregate so that
     * partially refunded invoices count for what the customer actually kept.
     */
    public const NET_AMOUNT_SQL = 'final_amount - refunded_amount';

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'scrap_deduction_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
        ];
    }

    /**
     * Invoices that count as sales (everything except cancelled and fully refunded).
     */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->whereNotIn($query->qualifyColumn('status'), InvoiceStatus::nonCountableValues());
    }

    /**
     * Sum of net sale value (final_amount - refunded_amount) for the given query.
     */
    public static function sumNetAmount(Builder $query): float
    {
        return (float) (clone $query)->sum(DB::raw(self::NET_AMOUNT_SQL));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerVehicle(): BelongsTo
    {
        return $this->belongsTo(CustomerVehicle::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scrapBattery(): HasOne
    {
        return $this->hasOne(ScrapBatteriesInventory::class);
    }

    public function creditEntries(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class);
    }

    public function technicianCommission(): HasOne
    {
        return $this->hasOne(TechnicianCommission::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
