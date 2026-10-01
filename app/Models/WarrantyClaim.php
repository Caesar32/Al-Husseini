<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends Model
{
    use \App\Models\Concerns\BelongsToBranch;

    protected $fillable = [
        'claim_number',
        'warranty_id',
        'customer_id',
        'branch_id',
        'defective_battery_serial',
        'technician_id',
        'claim_date',
        'battery_voltage_tested',
        'cca_tested',
        'issue_description',
        'decision',
        'rejection_reason',
        'replacement_invoice_id',
        'replacement_product_id',
        'replacement_battery_serial',
        'supplier_id',
        'supplier_resolution',
        'settlement_notes',
        'received_by_user_id',
        'settled_by_user_id',
        'received_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'battery_voltage_tested' => 'decimal:2',
            'cca_tested' => 'decimal:1',
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }

    public function replacementInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'replacement_invoice_id');
    }

    public function replacementProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'replacement_product_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }
}
