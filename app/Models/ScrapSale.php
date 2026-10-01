<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScrapSale extends Model
{
    protected $fillable = [
        'batch_number',
        'branch_id',
        'buyer_name',
        'buyer_phone',
        'payment_method',
        'batteries_count',
        'total_amount',
        'cost_value',
        'gross_profit',
        'sold_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'batteries_count' => 'integer',
            'total_amount' => 'decimal:2',
            'cost_value' => 'decimal:2',
            'gross_profit' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function batteries(): HasMany
    {
        return $this->hasMany(ScrapBatteriesInventory::class);
    }
}
