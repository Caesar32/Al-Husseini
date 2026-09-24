<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapBatteriesInventory extends Model
{
    protected $table = 'scrap_batteries_inventory';

    protected $fillable = [
        'branch_id',
        'invoice_id',
        'capacity_ah',
        'scrap_value',
        'lead_weight_kg',
        'status',
        'batch_number',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'scrap_value' => 'decimal:2',
            'lead_weight_kg' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receivedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'received_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->receivedByEmployee();
    }
}
