# 🧩 المرحلة 2: طبقة النماذج والعلاقات والمراقبين (Models & Observers)
### Phase 02: Eloquent Models, Relations, Scopes & Event Observers

> **الهدف الفني:**
> بناء نماذج Eloquent المعيارية مع إعداد الخصائص المصبوبة (Casts)، العلاقات متعددة الأطراف (Multi-Supplier Relations)، النطاقات المحلية (Local Scopes)، ومراقبي الأحداث (Observers) لأتمتة الحسابات المالية والمخزنية دون تلويث وحدات التحكم.

---

## 🏛️ 1. النماذج الجديدة والمعدلة (Models Specification):

### 1. نموذج كتالوج المورد والمنتج (`App\Models\SupplierProduct`):
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Pivot
{
    protected $table = 'supplier_products';
    public $incrementing = true;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'last_purchase_price',
        'min_order_qty',
        'lead_time_days',
        'is_primary_supplier',
        'notes',
    ];

    protected $casts = [
        'last_purchase_price' => 'decimal:2',
        'min_order_qty'       => 'integer',
        'lead_time_days'      => 'integer',
        'is_primary_supplier' => 'boolean',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
```

---

### 2. نموذج جدول تسعير الكهنة (`App\Models\ScrapPricingTier`):
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ScrapPricingTier extends Model
{
    protected $fillable = [
        'capacity_min_ah',
        'capacity_max_ah',
        'tier_name',
        'default_scrap_price',
        'is_active',
    ];

    protected $casts = [
        'capacity_min_ah'     => 'integer',
        'capacity_max_ah'     => 'integer',
        'default_scrap_price' => 'decimal:2',
        'is_active'           => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * استرجاع الشريحة والسعر المرجعي لسعة أمبير محددة
     */
    public static function getPriceForCapacity(int $capacityAh): ?float
    {
        $tier = static::active()
            ->where('capacity_min_ah', '<=', $capacityAh)
            ->where('capacity_max_ah', '>=', $capacityAh)
            ->first();

        return $tier ? (float) $tier->default_scrap_price : null;
    }
}
```

---

### 3. نموذج تفصيل الدفع المجزأ للفاتورة (`App\Models\InvoicePayment`):
```php
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

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
```

---

### 4. نموذج تذاكر عهدة وضمان البطاريات المعيبة (`App\Models\WarrantyClaim`):
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends Model
{
    protected $fillable = [
        'claim_number',
        'warranty_id',
        'customer_id',
        'branch_id',
        'defective_battery_serial',
        'replacement_product_id',
        'replacement_battery_serial',
        'supplier_id',
        'status',
        'technician_inspection_notes',
        'received_by_user_id',
        'settled_by_user_id',
        'received_at',
        'resolved_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function warranty(): BelongsTo { return $this->belongsTo(Warranty::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function replacementProduct(): BelongsTo { return $this->belongsTo(Product::class, 'replacement_product_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function receivedByUser(): BelongsTo { return $this->belongsTo(User::class, 'received_by_user_id'); }
    public function settledByUser(): BelongsTo { return $this->belongsTo(User::class, 'settled_by_user_id'); }
}
```

---

### 5. ترقية النماذج القائمة (Existing Models Enhancements):

* **في نموذج `Product`:**
  - إضافة علاقة الموردين:
    ```php
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->using(SupplierProduct::class)
            ->withPivot(['supplier_sku', 'last_purchase_price', 'min_order_qty', 'lead_time_days', 'is_primary_supplier'])
            ->withTimestamps();
    }
    ```
  - إضافة دالة المورد الأساسي `primarySupplier()`.

* **في نموذج `Supplier`:**
  - إضافة علاقة المنتجات:
    ```php
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'supplier_products')
            ->using(SupplierProduct::class)
            ->withPivot(['supplier_sku', 'last_purchase_price', 'min_order_qty', 'lead_time_days', 'is_primary_supplier'])
            ->withTimestamps();
    }
    ```
  - علاقة مطالبات الضمان `warrantyClaims()`.

* **في نموذج `Invoice`:**
  - إضافة علاقة الدفعات المجزأة: `public function payments(): HasMany { return $this->hasMany(InvoicePayment::class); }`.

---

## ⚡ 2. مراقبو الأحداث (Observers & Event Automations):

1. **`PurchaseInvoiceObserver` (أتمتة ما بعد التوريد):**
   - استدعاء خدمة احتساب المتوسط المرجح (WAC) لكل بند في الفاتورة.
   - تحديث سجل `supplier_products` بأحدث سعر شراء.
   - توليد قيد المديونية في `supplier_ledger_entries`.

2. **`WarrantyClaimObserver`:**
   - توليد رقم تسلسلي فريد للكود التلقائي: `CLM-YYYYMM-XXXX`.

---

## ✅ معايير التحقق والاعتماد (Phase 02 Verification):
- [x] التحقق من استرجاع العلاقات `Product::with('suppliers')` بدون أي استعلامات N+1.
- [x] التحقق من استرجاع الشريحة السعرية للكهنة بدقة عبر `ScrapPricingTier::getPriceForCapacity(70)` وإرجاع 800 ج.م.
- [x] ربط وتحديث علاقات `WarrantyClaim`, `Invoice::payments`, `Supplier::products`, `Product::suppliers`.
- [x] ترقية `PurchaseInvoiceObserver` بحساب المتوسط المرجح (WAC) وتحديث كتالوج المورد.
- [x] إنشاء وتسجيل `WarrantyClaimObserver` لتوليد أرقام مطالبات الضمان التلقائية.
- [x] اجتياز الاختبارات الشاملة (`php artisan test`): **91 passed (312 assertions)**.
