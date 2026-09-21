<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'sku',
        'barcode',
        'name',
        'brand',
        'capacity_ah',
        'voltage',
        'terminal_type',
        'warranty_months',
        'cost_price',
        'retail_price',
        'wholesale_price',
        'current_stock',
        'reorder_threshold',
        'is_battery',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'warranty_months' => 'integer',
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'current_stock' => 'integer',
            'reorder_threshold' => 'integer',
            'is_battery' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeBatteriesOnly(Builder $query): void
    {
        $query->where('is_battery', true);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('current_stock', '<=', 'reorder_threshold');
    }
}
