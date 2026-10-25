<?php

namespace App\Models;

use App\Casts\QuantityCast;
use App\Enums\ProductUnit;
use App\Support\Quantity;
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
        'unit',
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

    protected $attributes = [
        'unit' => 'piece',
    ];

    protected function casts(): array
    {
        return [
            'warranty_months' => 'integer',
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'unit' => ProductUnit::class,
            'current_stock' => QuantityCast::class,
            'reorder_threshold' => QuantityCast::class,
            'is_battery' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function allowsFraction(): bool
    {
        return ($this->unit ?? ProductUnit::Piece)->allowsFraction();
    }

    /**
     * A sale/purchase/return quantity of this product in milli-units (see Quantity::validationError()).
     *
     * @throws \DomainException
     */
    public function quantityMilliOrFail(mixed $value): int
    {
        return Quantity::milliOrFail($value, ! $this->allowsFraction(), (string) $this->name);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function purchaseInvoiceItems(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function suppliers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->using(SupplierProduct::class)
            ->withPivot(['id', 'supplier_sku', 'last_purchase_price', 'min_order_qty', 'lead_time_days', 'is_primary_supplier'])
            ->withTimestamps();
    }

    public function primarySupplier(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->suppliers()->wherePivot('is_primary_supplier', true);
    }

    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class, 'replacement_product_id');
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
