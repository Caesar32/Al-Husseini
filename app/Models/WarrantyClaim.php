<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends Model
{
    protected $fillable = [
        'warranty_id',
        'technician_id',
        'claim_date',
        'battery_voltage_tested',
        'cca_tested',
        'issue_description',
        'decision',
        'replacement_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
            'battery_voltage_tested' => 'decimal:2',
            'cca_tested' => 'decimal:1',
        ];
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }

    public function replacementInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'replacement_invoice_id');
    }
}
