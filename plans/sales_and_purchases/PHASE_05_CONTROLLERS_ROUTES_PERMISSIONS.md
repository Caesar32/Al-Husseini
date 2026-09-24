# 🎛️ المرحلة 5: طبقة التحكم والمسارات والصلاحيات (Controllers & Routes)
### Phase 05: Thin Controllers, Route Declarations & Spatie Permission Matrix

> **الهدف الفني:**
> بناء وحدات تحكم رشيقة وموجهة (Thin Controllers) تعتمد كلياً على حقن التبعيات (Dependency Injection) واستقبال الـ Form Requests وتوجيه الاستجابة، مع حماية كل مسار بصلاحية دقيقة عبر Spatie Laravel-Permission.

---

## 📑 1. وحدات التحكم المستهدفة (Controllers Blueprint):

### 1. متحكم الموردين (`App\Http\Controllers\Admin\SupplierController`):
* `index()`: استعراض قائمة الموردين مع الفلترة والبحث السريع وأرصدة المديونية.
* `store(StoreSupplierRequest $request)`: إضافة مورد جديد.
* `show(Supplier $supplier)`: استعراض الملف التعريفي والكتالوج الخاص به وفواتيره.
* `ledger(Supplier $supplier)`: كشف حساب أستاذ المورد التفصيلي.
* `recordPayment(StoreSupplierPaymentRequest $request, Supplier $supplier)`: قيد سند صرف/سداد دفعة للمورد.

---

### 2. متحكم فواتير المشتريات والتوريد (`App\Http\Controllers\Admin\PurchaseInvoiceController`):
* `index()`: كشف فواتير المشتريات وحالات السداد وتواريخ الشحنات.
* `create()`: شاشة إدخال فاتورة توريد سريعة مع قراءة الكتالوج والأسعار.
* `store(StorePurchaseInvoiceRequest $request)`: حفظ الفاتورة وتحديث المخزون والمتوسط المرجح لحظياً.
* `show(PurchaseInvoice $invoice)`: استعراض بنود الفاتورة وحساب المديونية.
* `print(PurchaseInvoice $invoice)`: طباعة إذن الاستلام وتفاصيل الشحنة.

---

### 3. متحكم نقطة البيع السريعة والكاشير (`App\Http\Controllers\Admin\PosController`):
* `index()`: شاشة الكاشير التفاعلية (مسح الباركود، إدخال السريال، واختيار الكهنة).
* `store(StorePosInvoiceRequest $request)`: معالجة أمر البيع والدفع المجزأ وإصدار الضمان.
* `receipt(Invoice $invoice)`: معاينة وطباعة الإيصال الحراري السريع (80mm).
* `warrantyCert(Invoice $invoice)`: معاينة وطباعة شهادة الضمان المستقلة (A4/A5).

---

### 4. متحكم إدارة الضمانات والبطاريات المعيبة (`App\Http\Controllers\Admin\WarrantyController`):
* `index()`: متابعة الضمانات السارية وقائمة المطالبات المفتوحة.
* `verify(Request $request)`: فحص السيريال واسترجاع تاريخ الفاتورة وحالة السريان (API).
* `storeClaim(ProcessWarrantyClaimRequest $request)`: إنشاء تذكرة استبدال فوري وصرف البديل.
* `settleSupplier(Request $request, WarrantyClaim $claim)`: إغلاق المطالبة مع مندوب المورد.

---

### 5. متحكم مخزن الكهنة وتجارة الرصاص (`App\Http\Controllers\Admin\ScrapInventoryController`):
* `index()`: لوحة إحصائيات مخزن الكهنة، إجمالي السعات، الأوزان، وسجل الحركات.
* `sellBatch(StoreScrapSaleBatchRequest $request)`: تفريغ شحنة كهنة وبيعها لمصنع إعادة التدوير.
* `updateTiers(Request $request)`: تعديل الشرائح السعرية المرجعية للأمبير (صلاحية مدير عام).

---

## 🛣️ 2. خريطة المسارات المعتمدة في `routes/web.php`:

```php
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {

    // قطاع الموردين والتوريدات
    Route::resource('suppliers', SupplierController::class);
    Route::get('suppliers/{supplier}/ledger', [SupplierController::class, 'ledger'])->name('suppliers.ledger');
    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'recordPayment'])->name('suppliers.payments');

    // فواتير المشتريات
    Route::resource('purchases', PurchaseInvoiceController::class)->except(['edit', 'update', 'destroy']);
    Route::get('purchases/{purchase}/print', [PurchaseInvoiceController::class, 'print'])->name('purchases.print');

    // نقطة البيع ومبيعات الكاشير
    Route::get('pos', [PosController::class, 'index'])->name('pos.index')->middleware('can:pos.access');
    Route::post('pos', [PosController::class, 'store'])->name('pos.store')->middleware('can:pos.access');
    Route::get('pos/{invoice}/receipt', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::get('pos/{invoice}/warranty', [PosController::class, 'warrantyCert'])->name('pos.warranty_cert');

    // فواتير المبيعات وسجل العمليات
    Route::get('invoices', [SalesInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [SalesInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/return', [SalesInvoiceController::class, 'processReturn'])->name('invoices.return');

    // الضمانات والبطاريات التالفة
    Route::get('warranties', [WarrantyController::class, 'index'])->name('warranties.index');
    Route::get('warranties/verify', [WarrantyController::class, 'verify'])->name('warranties.verify');
    Route::post('warranties/claims', [WarrantyController::class, 'storeClaim'])->name('warranties.claims.store');
    Route::post('warranties/claims/{claim}/settle', [WarrantyController::class, 'settleSupplier'])->name('warranties.claims.settle');

    // مخزن الكهنة وتجارة الرصاص
    Route::get('scrap-inventory', [ScrapInventoryController::class, 'index'])->name('scrap.index');
    Route::post('scrap-inventory/sell-batch', [ScrapInventoryController::class, 'sellBatch'])->name('scrap.sell_batch');
    Route::put('scrap-inventory/tiers', [ScrapInventoryController::class, 'updateTiers'])->name('scrap.update_tiers');
});
```

---

## 🛡️ 3. مصفوفة الصلاحيات (Spatie Permissions Matrix):

| الصلاحية الكودية | الوصف التشغيلي | الأدوار الممنوحة افتراضياً |
| :--- | :--- | :--- |
| `pos.access` | فتح واستخدام شاشة الكاشير ونقاط البيع | كاشير، مدير فرع، مشرف عام |
| `invoices.view` | استعراض فواتير المبيعات السابقة | كاشير، محاسب، مدير فرع |
| `invoices.return` | تنفيذ مرتجع مبيعات | مدير فرع، مشرف عام |
| `suppliers.manage` | إضافة وتعديل بيانات الموردين | مسؤول مشتريات، مدير عام |
| `suppliers.ledger` | استعراض كشف حساب أستاذ الموردين | محاسب، مدير فرع |
| `suppliers.pay` | صرف دفعات وسندات قبض للموردين | محاسب، مدير فرع |
| `purchases.create` | إدخال فواتير التوريد واستلام الشحنات | أمين مخزن، مدير فرع |
| `warranties.manage`| فحص السريالات وتنفيذ استبدال الضمان | فني استقبال، مدير فرع |
| `scrap.manage` | استعراض مخزن الكهنة وبيع شحنات الرصاص | أمين مخزن، مدير فرع |

---

## ✅ معايير التحقق والاعتماد (Phase 05 Verification):
- [ ] فحص حماية كافة المسارات والتأكد من رفض المستخدم غير المصرح له بخطأ `403 Forbidden`.
- [ ] التأكد من أن المشرف العام (`super-admin`) يتخطى كافة الحواجز بسلاسة.
- [ ] فحص نظافة الـ Controllers وخلوها من الاستعلامات المباشرة.
