# ⚙️ المرحلة 4: محرك الخدمات ومنطق الأعمال (Service Layer)
### Phase 04: Business Logic Services, Contracts & Mathematical Engines

> **الهدف الفني:**
> عزل كافة المعادلات المحاسبية، وحسابات المتوسط المرجح (WAC)، وتوليد قيود دفتر الأستاذ المزدوج، وقفل التزامن المخزني، وإصدار الضمانات الإلكترونية، وعمولات الفنيين داخل طبقة خدمات نقية (Pure Services) قابلة للاختبار وقائمة على العقود (Dependency Inversion).

---

## 📑 1. العقود والواجهات المعيارية (Contracts & Interfaces):

### 1. `App\Contracts\Purchases\PurchaseServiceInterface`:
```php
namespace App\Contracts\Purchases;

use App\Models\PurchaseInvoice;
use App\Models\SupplierLedgerEntry;

interface PurchaseServiceInterface
{
    public function createDirectPurchase(array $data, int $receivedByUserId): PurchaseInvoice;
    public function recordSupplierPayment(int $supplierId, float $amount, string $method, array $extra = []): SupplierLedgerEntry;
    public function processPurchaseReturn(int $purchaseInvoiceId, array $items, string $reason, int $userId): PurchaseInvoice;
    public function getSupplierLedgerStatement(int $supplierId, ?string $fromDate = null, ?string $toDate = null): array;
}
```

---

### 2. `App\Contracts\Sales\PosOrderServiceInterface`:
```php
namespace App\Contracts\Sales;

use App\Models\Invoice;

interface PosOrderServiceInterface
{
    public function processPosSale(array $data, int $cashierUserId): Invoice;
    public function processSalesReturn(int $invoiceId, array $items, string $reason, int $cashierUserId): Invoice;
    public function calculateScrapDeduction(int $capacityAh, int $quantity = 1): float;
    public function getDailyCashierSummary(int $branchId, string $date): array;
}
```

---

### 3. `App\Contracts\Sales\WarrantyServiceInterface`:
```php
namespace App\Contracts\Sales;

use App\Models\Warranty;
use App\Models\WarrantyClaim;

interface WarrantyServiceInterface
{
    public function verifyBatterySerial(string $serialNumber): array;
    public function issueWarranty(int $invoiceId, int $customerId, ?int $vehicleId, string $serial, int $months): Warranty;
    public function processInstantClaim(array $data, int $receivedByUserId): WarrantyClaim;
    public function settleClaimWithSupplier(int $claimId, string $action, array $resolutionData, int $userId): WarrantyClaim;
}
```

---

### 4. `App\Contracts\Sales\ScrapBatteryServiceInterface`:
```php
namespace App\Contracts\Sales;

interface ScrapBatteryServiceInterface
{
    public function getInventoryMetrics(int $branchId): array;
    public function dispatchScrapSaleBatch(array $data, int $authorizedByUserId): array;
    public function updatePricingTiers(array $tiers): void;
}
```

---

## 📐 2. منطق الخدمات وتفاصيل الخوارزميات (Service Implementations):

### أ. خوارزمية الشراء والمتوسط المرجح (`PurchaseService::createDirectPurchase`):
1. بدء معاملة آمنة `DB::transaction`.
2. التحقق من المورد وإنشاء فاتورة التوريد `purchase_invoices`.
3. تكرار الأصناف:
   - قفل صف المنتج بـ `lockForUpdate()`.
   - حساب المتوسط المرجح الجديد:
     $$\text{New Cost} = \frac{(\text{Current Stock} \times \text{Current Cost}) + (\text{Received Qty} \times \text{Unit Price})}{\text{Current Stock} + \text{Received Qty}}$$
   - تحديث المخزون والتكلفة في جدول `products`.
   - تحديث أو إنشاء سجل الربط في `supplier_products` بأحدث سعر شراء.
4. حساب مديونية الفاتورة المتبقية (`remaining_amount = final_amount - paid_amount`).
5. توليد قيد المديونية في `supplier_ledger_entries` وتحديث `current_balance` للمورد.

---

### ب. خوارزمية البيع السريع واستبدال الكهنة (`PosOrderService::processPosSale`):
1. بدء معاملة `DB::transaction`.
2. التحقق من توفر كميات المنتجات مع حجزها وقفلها بـ `lockForUpdate()`.
3. إذا وجد استبدال كهنة (`has_scrap = true`):
   - استدعاء القيمة المعتمدة الصارمة من `ScrapPricingTier::getPriceForCapacity($ah)`.
   - منع أي تعديل يدوي، واعتماد السعر كخصم كهنة في الفاتورة.
4. حساب الإجمالي الصافي: $\text{Final} = \text{Items Total} - \text{Scrap Deduction} - \text{Discount}$.
5. تسجيل وتوزيع المدفوعات المجزأة في `invoice_payments`.
6. إذا وجد جزء آجل (`credit`): التحقق من سقف ائتمان العميل ومطابقة رمز موافقة المدير.
7. خصم المنتجات المبيعة من المخزون.
8. إصدار وثائق الضمان بالسريالات في جدول `warranties`.
9. إيداع البطارية القديمة في مخزن الكهنة `scrap_batteries_inventory`.
10. احتساب عمولة الفني المحددة (25 ج.م للبطارية) وإدراجها في `technician_commissions`.

---

### ج. خوارزمية استبدال الضمان الفوري (`WarrantyService::processInstantClaim`):
1. داخل `DB::transaction`.
2. التحقق من سريان الضمان وعدم انقضاء مدته (حسب شهور الصلاحية وتاريخ الشراء).
3. خصم بطارية جديدة بديلة من المخزن (تكلفة على العميل = 0 ج.م).
4. إنشاء وثيقة ضمان جديدة بالسيريال الجديد للبطارية البديلة.
5. إنشاء تذكرة `warranty_claims` ونقل البطارية التالفة إلى عهدة مخزن الضمان بانتظار مندوب المورد.

---

### 🛡️ د. منظومة مكافحة تعارض العمليات والسباق (Race Conditions & Pessimistic Locking):

لمنع أي تعارض تزامن (Concurrency Conflict) بين الكاشيرات والمستخدمين، يتم تطبيق الحماية الرباعية التالية:

1. **حماية بيع آخر قطعة في المخزن (Inventory Stock Race Condition):**
   - استخدام القفل التشاؤمي الحصري (`Pessimistic Exclusive Lock`):
     ```php
     $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
     ```
   - عند محاولة كاشيرين بيع آخر بطارية في نفس اللحظة: تجبر قاعدة البيانات المعاملة الثانية على **الانتظار** حتى تنتهي المعاملة الأولى وتعمل `commit`.
   - عند استيقاظ المعاملة الثانية، تجد المخزون = 0، فترمي `InsufficientStockException` صريحة تمنع أي رصيد سالب.

2. **حماية سقف ائتمان العميل (Customer Credit Limit Race Condition):**
   - قفل سجل العميل أثناء فحص الرصيد وإصدار الفاتورة الآجلة:
     ```php
     $customer = Customer::where('id', $customerId)->lockForUpdate()->first();
     ```
   - هذا يمنع كاشيرين من البيع بالآجل لنفس العميل في نفس اللحظة وتخطي سقف مديونيته دون اكتشاف ذلك.

3. **حماية تكرار سريال البطارية (Battery Serial Race Condition):**
   - حماية مزدوجة: فحص برمجي داخل المعاملة + قيد فريد صارم في قاعدة البيانات:
     `$table->string('battery_serial_number', 100)->unique();`
   - في حال حدث سباق لحظي متطابق، تضمن قاعدة البيانات على مستوى الـ Storage Engine رفض المعاملة الثانية تلقائياً بـ `UniqueConstraintViolation` والتراجع الآلي (`Rollback`).

4. **حماية ترقيم الفواتير وسندات القبض (Invoice Sequence Collision):**
   - **يُمنع منعاً باتاً** الترقيم بالاعتماد على `Invoice::count() + 1` أو `max('id') + 1` لأن طلبين متزامنين سيأخذان نفس الرقم.
   - **الآلية المعتمدة:** الترقيم التسلسلي الذري (Atomic Sequences) بالاعتماد على معرف الصف الحقيقي `id` بعد الحفظ، أو دمج التاريخ وأجزاء الثانية مع كود الفرع `MAIN` والمعرف الذري لضمان عدم حدوث أي تصادم في الأرقام نهائياً.

---

## 🔍 3. توسيع محرك البحث الذكي والفهرسة (`SearchService Integration`):

يتم ترقية خدمة [`SearchService`](file:///d:/Projects/Al-Husseini/app/Services/SearchService.php) لتشمل استعلامات الفهرسة السريعة للقطاعات الجديدة:

1. **البحث في المنتجات بأكواد الموردين (`supplier_sku`):**
   - تمكين الكاشير أو مسؤول المشتريات من البحث بكود الصنف لدى المورد عبر `whereHas('suppliers', fn($q) => $q->where('supplier_sku', 'LIKE', ...))`.
2. **البحث في فواتير المشتريات وشحنات التوريد (`purchase_invoices`):**
   - إضافة قطاع جديد في الـ Spotlight Search لفواتير الشراء بالرقم `invoice_number`، اسم شركة التوريد، وتاريخ الفاتورة.
3. **البحث في تذاكر ومطالبات الضمان (`warranty_claims`):**
   - استرجاع فوري لتذكرة الضمان بكود المطالبة `claim_number` أو بسيريال البطارية التالفة/البديلة.
4. **معالجة النصوص العربية في قطاع المبيعات:**
   - تطبيق دالة `getSearchVariants()` على أسماء شركات الموردين (مثل: "الأهرام / الاهرام"، "كلورايد / كلورايد")، وأسماء ماركات البطاريات والزيوت لتطابق فوري في كل الأحوال.

---

## 🚦 4. منظومة الحماية الصارمة من استعلامات N+1 وخريطة الـ Eager Loading:

> [!IMPORTANT]
> **الحماية الصارمة مفعّلة في المشروع:** `Model::preventLazyLoading(!app()->isProduction())` مُشغَّل في `AppServiceProvider`. أي وصول لعلاقة بدون تحميل مسبق (`with`) سيرمي `LazyLoadingViolationException` فوراً أثناء التطوير والاختبارات.

### أ. خريطة الـ Eager Loading الإلزامية لكل دالة وخدمة:

| المكون / الخدمة | الدالة التشغيلية | العلاقات الإلزامية للتحميل المسبق (Eager Loading) |
| :--- | :--- | :--- |
| **`SearchService`** | `searchProducts()` | `['category:id,name', 'suppliers' => fn($q) => $q->wherePivot('is_primary_supplier', true)]` |
| **`SearchService`** | `searchSuppliers()` | `['products:id,name']` |
| **`SearchService`** | `searchInvoices()` | `['customer:id,name,phone', 'items.product:id,name']` |
| **`SearchService`** | `searchWarranties()`| `['customer:id,name', 'customerVehicle:id,car_brand,car_model,plate_number']` |
| **`PurchaseService`** | `getSupplierLedgerStatement()` | `['supplier', 'purchaseInvoice.items.product', 'paidBy:id,name']` |
| **`PurchaseInvoiceController`**| `index()` | `['supplier:id,name,company_name', 'receivedBy:id,name', 'branch:id,name']` |
| **`PurchaseInvoiceController`**| `show()` | `['supplier', 'receivedBy', 'branch', 'items.product.category']` |
| **`PosOrderService`** | `getDailyCashierSummary()` | `['payments', 'items.product', 'customer:id,name', 'technician:id,full_name']` |
| **`SalesInvoiceController`** | `index()` | `['customer:id,name,phone', 'customerVehicle', 'cashier:id,name', 'technician:id,full_name', 'payments']` |
| **`WarrantyController`** | `index()` | `['warranty.customer', 'warranty.customerVehicle', 'replacementProduct', 'supplier', 'receivedByUser:id,name']` |
| **`ScrapInventoryController`** | `index()` | `['invoice:id,invoice_number,customer_id', 'invoice.customer:id,name']` |

---

### ب. نمط الجلب الجماعي قبل الـ Loop (Bulk-Query Pattern):

يُمنع منعاً باتاً استدعاء استعلامات `find()` أو استعلامات العلاقات داخل أي `foreach` لمعالجة بنود الفواتير:

```php
// ❌ خطأ كارثي يتسبب في N+1 استعلام:
foreach ($items as $item) {
    $product = Product::find($item['product_id']); // استعلام لكل سطر!
    $stock = $product->current_stock;
}

// ✅ النمط المعتمد الصارم (استعلام واحد فقط مجمع قبل الـ loop):
$productIds = collect($items)->pluck('product_id')->unique()->all();

// جلب كافة المنتجات دفعة واحدة مع قفل الصفوف في استعلام واحد:
$products = Product::whereIn('id', $productIds)
    ->lockForUpdate()
    ->get()
    ->keyBy('id');

// داخل الـ loop — وصول مباشر من الذاكرة (In-Memory Access):
foreach ($items as $item) {
    $product = $products->get($item['product_id']);
    // المعالجة المحاسبية بدون أي استعلام إضافي!
}
```

---

## 🔌 5. تسجيل مزود الخدمة (Service Provider Binding):
* إنشاء `App\Providers\SalesAndPurchasesServiceProvider` وعمل Container Bindings:
  - `PurchaseServiceInterface` -> `PurchaseService`
  - `PosOrderServiceInterface` -> `PosOrderService`
  - `WarrantyServiceInterface` -> `WarrantyService`
  - `ScrapBatteryServiceInterface` -> `ScrapBatteryService`
  - `SupplierServiceInterface` -> `SupplierService`

---

## ✅ معايير التحقق والاعتماد (Phase 04 Verification):
- [ ] التأكد من عدم إطلاق أي `LazyLoadingViolationException` أثناء تشغيل كافة دوال ومسارات البيع والشراء في بيئة التطوير.
- [ ] كتابة Unit Tests لاختبار دقة حساب المتوسط المرجح WAC بدقة الكسور العشرية.
- [ ] كتابة اختبارات تؤكد التراجع التلقائي (Rollback) في حال حدوث خطأ عند إدخال الفاتورة.
- [ ] التأكد من عدم وجود أي استعلامات N+1 داخل دوال استرجاع كشوف الحسابات.

