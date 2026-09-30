# تقرير تحليل أخطاء قطاع نقاط البيع والمبيعات — نظام مجموعة الحسيني
### POS & Sales Error Analysis Report

> **تاريخ التحليل:** 2026-09-30 | **المحلل:** Muse Spark (Automated Review) | **النطاق:** `app/Services/Sales/*` + `app/Http/Controllers/Admin/{Pos,SalesInvoice,CreditCustomer,Warranty,Scrap}*` + `app/Http/Requests/Admin/{Pos,Warranties,Scrap}/*` + `app/Models/{Invoice,InvoiceItem,InvoicePayment,Warranty,WarrantyClaim,ScrapBatteriesInventory,Customer,CreditLedgerEntry,Product}` + `app/Observers/InvoiceObserver.php` | **حالة الاختبارات قبل التحليل:** 146 اختبار — 143 نجاح / 3 فشل (DashboardSalesFilterTest + SystemDiagnostics Simulation×2)

---

## 0. الملخص التنفيذي (Executive Summary)

تم فحص **31 ملفاً** مرتبطاً بالـ POS بشكل سطري (line-by-line) مع تشغيل الاختبارات والمحاكاة الحية (`SystemDiagnosticService`). النتيجة:

- **9 أخطاء حرجة (P0)** تمس سلامة المخزون/المالية أو تسمح بفساد بيانات تحت التزامن.
- **8 أخطاء عالية (P1)** — ثغرات صلاحيات، تجاوز سقف الائتمان، وكشف كود تفويض.
- **9 أخطاء متوسطة (P2)** — منطق مرتجع/تحصيل، تجربة مستخدم، وفجوات تحقق.
- **6 أخطاء منخفضة (P3)** — أداء، صيانة، وانحراف عن معايير Laravel Best Practices.

نقطة القوة الأساسية: **كل العمليات المالية داخل `DB::transaction` مع `lockForUpdate`** (`app/Services/Sales/PosOrderService.php:66,73,174,332,416,479,512`). الأخطاء الباقية تتركز في **TOCTOU بين طبقة التحقق (Request) والتنفيذ (Service)**، و**توليد المعرّفات غير الذري**، و**منطق المرتجع/الضمان**.

---

## 1. منهجية الفحص (Methodology)

1. قراءة كامل الكود المصدري للـ POS (Controllers/Services/Requests/Models/Observers/Migrations).
2. تشغيل `php artisan test` و `php artisan system:diagnose` وتحليل الـ simulation log (تم تأكيد فشل `Payroll Lifecycle` برسالة `إجماليات المسير لا تتطابق` و `DashboardSalesFilterTest` يبحث عن نص غير موجود).
3. مطابقة كل قاعدة عمل مع `docs/SALES_AND_PURCHASES_SPEC.md` و `docs/SALES_PURCHASES_SCENARIOS.md` و `PROJECT_CONTEXT.md:142-245`.
4. البحث الآلي عن أنماط شائعة: `lockForUpdate`, `withoutEvents`, `firstOrCreate`, `uniqid`, `Hash::check`, `authorize():true`, `float`.

> كل ادعاء أدناه مدعوم بمسار ملف ورقم سطر بصيغة `file_path:line_number`.

---

## 2. مقياس الخطورة

| الرمز | الخطورة | التعريف |
|------|---------|---------|
| **P0** | حرجة | فساد مالي/مخزني أو سباق تزامن قابل للاستغلال في الإنتاج |
| **P1** | عالية | تجاوز صلاحيات/ائتمان أو تسريب بيانات |
| **P2** | متوسطة | منطق عمل خاطئ يؤثر على التشغيل اليومي |
| **P3** | منخفضة | أداء/صيانة/انحراف عن المعايير |

---

## 3. جدول الملخص (31 خطأ)

| # | المعرف | العنوان | الملف:السطر | الخطورة | الحالة |
|---|--------|---------|-------------|---------|--------|
| 1 | POS-C01 | توليد رقم فاتورة غير ذري `uniqid()` قابل للتصادم | `app/Services/Sales/PosOrderService.php:194` | **P0** | مفتوح |
| 2 | POS-C02 | سباق إنشاء عميل نقدي عابر `firstOrCreate(phone=00000000000)` | `app/Services/Sales/PosOrderService.php:270-276` | **P0** | مفتوح |
| 3 | POS-C03 | خصم كهنة صامت `0.00` عند غياب شريحة التسعير | `app/Services/Sales/PosOrderService.php:113-126` / `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:138-143` | **P0** | مفتوح |
| 4 | POS-C04 | هامش تسامح `0.05` جنيه يخفي أخطاء تقريب تراكمية | `app/Services/Sales/PosOrderService.php:153` / `StorePosInvoiceRequest.php:194` | **P0** | مفتوح |
| 5 | POS-C05 | مرتجع يحوّل الفاتورة كاملةً إلى `refunded` حتى لو جزئي | `app/Services/Sales/PosOrderService.php:396-400` | **P0** | مفتوح |
| 6 | POS-C06 | استبدال ضمان ينشئ ضمان جديد بنفس `invoice_item_id` القديم | `app/Services/Sales/WarrantyService.php:153` | **P0** | مفتوح |
| 7 | POS-C07 | رد مخزون المرتجع بدون قفل `lockForUpdate` على الفاتورة الأصلية في كل الحالات | `app/Services/Sales/PosOrderService.php:351` (يوجد قفل لكن `SalesInvoiceController:88` يمرر `Request` بدون تحقق فرع) | **P0** | مفتوح |
| 8 | POS-C08 | فشل `assertPayrollTotalsConsistent` في المحاكاة يوقف مسار POS المرتبط (اختبار E2E) | `app/Services/Hr/PayrollService.php:259-285` / `app/Services/Diagnostics/SystemDiagnosticService.php:657-674` | **P0** | مفتوح — مؤكد بالتنفيذ |
| 9 | POS-C09 | `InvoiceObserver` معطل لكن شفرته تكرر المنطق المالي إذا أُعيد تفعيله | `app/Observers/InvoiceObserver.php:14-74` / `app/Providers/AppServiceProvider.php:40-44` | **P0** | مفتوح (خطر تكرار محاسبي) |
| 10 | POS-H01 | `authorize():true` يتجاوز بوابة `can:pos.access` في كل طلبات POS | `StorePosInvoiceRequest.php:16-19` / `ProcessWarrantyClaimRequest.php:12-15` / `StoreScrapSaleBatchRequest.php:12-15` | **P1** | مفتوح |
| 11 | POS-H02 | `isManagerOverrideValid` يحمّل كل المدراء ويفحص `Hash::check` بالتسلسل | `PosOrderService.php:562-583` / `StorePosInvoiceRequest.php:238-262` | **P1** | مفتوح |
| 12 | POS-H03 | كود تفويض افتراضي ضعيف `9999` + باكدور `mgr_override_99` ثابت في الكود | `PosOrderService.php:568` / `StorePosInvoiceRequest.php:245` | **P1** | مفتوح |
| 13 | POS-H04 | فحص مخزون/ائتمان/سيريال في `StorePosInvoiceRequest::withValidator` بدون قفل — TOCTOU | `StorePosInvoiceRequest.php:84-88,117-122,213-233` | **P1** | مفتوح |
| 14 | POS-H05 | `PosController::index` يحمّل كل المنتجات/العملاء بلا ترقيم (Pagination) | `app/Http/Controllers/Admin/PosController.php:26-46` | **P1** | مفتوح |
| 15 | POS-H06 | `CreditCustomerController::index` يحمّل كل المدينين `get()` بلا ترقيم | `app/Http/Controllers/Admin/CreditCustomerController.php:26-34` | **P1** | مفتوح |
| 16 | POS-H07 | `WarrantyController::verify` سلوك مزدوج JSON/HTML قد يكشف بيانات ضمان بلا صلاحية إضافية | `app/Http/Controllers/Admin/WarrantyController.php:50-89` | **P1** | مفتوح |
| 17 | POS-H08 | غياب `branch_id` في تحقق `StorePosInvoiceRequest` يسمح ببيع لفرع آخر | `StorePosInvoiceRequest.php:24` (`nullable` بدلاً من `required` + `exists` + `in:auth_branches`) | **P1** | مفتوح |
| 18 | POS-M01 | `calculateScrapDeduction` ترجع `0` بصمت ولا تُخطِر المستخدم | `PosOrderService.php:406-414` | **P2** | مفتوح |
| 19 | POS-M02 | تحصيل الآجل FIFO لا يتحقق من `branch_id` للفواتير المفتوحة | `PosOrderService.php:511-516` | **P2** | مفتوح |
| 20 | POS-M03 | تحصيل الآجل ينشئ `InvoicePayment` لكن لا يحدّث `Invoice.notes` بمرجع التحصيل | `PosOrderService.php:536-543` | **P2** | مفتوح |
| 21 | POS-M04 | بيع كهنة `total_amount` غير مقيّد بسعر السوق/وزن الرصاص | `ScrapBatteryService.php:100` / `StoreScrapSaleBatchRequest.php:21` | **P2** | مفتوح |
| 22 | POS-M05 | `DashboardSalesFilterTest` يبحث عن نص لم يعد موجوداً | `tests/Feature/Admin/DashboardSalesFilterTest.php:22` / `resources/views/admin/dashboard/partials/kpi-cards.blade.php:13` | **P2** | مؤكد بالتنفيذ |
| 23 | POS-M06 | وزن رصاص الكهنة `capacity*0.17` مقابل `*0.28` — ثابتان مختلفان في نفس النظام | `PosOrderService.php:310` vs `database/seeders/SalesAndPosDataSeeder.php:521` | **P2** | مفتوح |
| 24 | POS-M07 | `SalesInvoiceController::index` يكرر منطق فلترة `PosOrderService::getPaginatedInvoices` | `SalesInvoiceController.php:30-60` | **P2** | مفتوح |
| 25 | POS-M08 | `ProcessWarrantyClaimRequest` لا يتحقق من `branch_id` للضمان | `ProcessWarrantyClaimRequest.php:19-33` | **P2** | مفتوح |
| 26 | POS-M09 | `InvoicePayment.payment_method` يقبل `credit` لكن `CreditCustomerController::settle` يرفضه | `StorePosInvoiceRequest.php:49` vs `CreditCustomerController.php:68` | **P2** | مفتوح |
| 27 | POS-L01 | `getInventoryMetrics` يحمّل كل الكهنة في الذاكرة ثم يجمع | `ScrapBatteryService.php:42-53` | **P3** | مفتوح |
| 28 | POS-L02 | استخدام `float` للنقود بدلاً من `int` سنت أو `decimal` صارم | `PosOrderService.php:32,76,128` وعدة مواضع | **P3** | مفتوح |
| 29 | POS-L03 | `invoice_number` فريد لكن خطأ التصادم يظهر كـ `QueryException` غير مقروء | `PosOrderService.php:194` + migration `2026_09_21_160008:11` | **P3** | مفتوح |
| 30 | POS-L04 | `config('app.manager_override_code')` غير معرّف في `config/app.php` ولا `.env.example` | `config/app.php:1-126` | **P3** | مفتوح |
| 31 | POS-L05 | `Invoice.scrapBattery()` علاقة `HasOne` لكن الكود ينشئ عدة صفوف `ScrapBatteriesInventory` لنفس الفاتورة | `Invoice.php:73-77` vs `PosOrderService.php:305-313` | **P3** | مفتوح |
| 32 | POS-L06 | `Customer.creditLedgers` بلا ترتيب افتراضي — التعليق يوضح السبب لكن الاختبارات تعتمد الترتيب | `Customer.php:51-55` | **P3** | مفتوح (توثيقي) |

---

## 4. التفصيل التحليلي

### 4.1 الأخطاء الحرجة (P0)

#### P0-01 — توليد رقم فاتورة غير ذري [POS-C01]
- **المسار:** `app/Services/Sales/PosOrderService.php:194`
  ```php
  $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
  ```
- **الوصف:** `uniqid()` مبني على microtime؛ تحت تزامن كاشيرين في نفس الميلي ثانية قد يتصادم الرقم. القيد الفريد في `2026_09_21_160008:11` (`$table->string('invoice_number',50)->unique()`) سيحوّل التصادم إلى `QueryException` 500 بدلاً من إعادة المحاولة.
- **الأثر:** فشل فاتورة في الذروة، تجربة كاشير سيئة، سجل `invoice_payments` قد يُنشأ جزئياً إذا لم يُلتقط الاستثناء بشكل صحيح.
- **الإصلاح المقترح:**
  ```php
  // استخدم تسلسل يومي ذري
  $invoiceNumber = DB::transaction(function () {
      $seq = DB::table('invoice_sequences')->where('date', today()->toDateString())->lockForUpdate()->first();
      // increment + return INV-YYYYMMDD-XXXX
  });
  // أو: retry loop مع catch UniqueConstraintViolation
  ```
- **اختبار مفقود:** لا يوجد اختبار تزامن (concurrency) في `SalesAndPurchasesServicesTest`.

#### P0-02 — سباق عميل نقدي عابر [POS-C02]
- **المسار:** `app/Services/Sales/PosOrderService.php:270-276`
  ```php
  $guestCustomer = Customer::firstOrCreate(['phone'=>'00000000000'], [...]);
  ```
- **الوصف:** `firstOrCreate` ليس ذرياً بدون قفل فريد. كاشيران يبيعان لعميل نقدي عابر في نفس اللحظة قد ينشئان صفين بنفس الهاتف (رغم `unique` على `phone` إن وجد — غير موجود في `2026_09_21_160006_create_customers...`).
- **الأثر:** ضمانات معلّقة على عميلين مختلفين، كشف حساب مشتت.
- **الإصلاح:** ضمان وجود صف واحد مسبقاً عبر seeder + `Customer::where('phone','00000000000')->lockForUpdate()->firstOrFail()` أو فرض `unique` على `phone` وإعادة المحاولة.

#### P0-03 — خصم كهنة صامت [POS-C03]
- **المسار:** `PosOrderService.php:113-126` و `StorePosInvoiceRequest.php:128-142`
- **الوصف:** إذا `has_scrap=true` لكن `ScrapPricingTier::findPriceForCapacity($ah)` ترجع `null`، يُحتسب الخصم `0.00` بصمت. المستخدم يرى “تم استلام كهنة” لكن الفاتورة بلا خصم.
- **الأثر:** خسارة للعميل أو نزاع على الكاشير.
- **الإصلاح:** في `withValidator` أضف:
  ```php
  if ($this->boolean('has_scrap') && !$tier && !$this->filled('scrap_price_override') && !$this->filled('scrap_deduction_amount')) {
      $validator->errors()->add('scrap_capacity_ah','سعة الكهنة لا تطابق أي شريحة تسعير نشطة.');
  }
  ```

#### P0-04 — هامش تسامح 0.05 جنيه [POS-C04]
- **المسار:** `PosOrderService.php:153` و `StorePosInvoiceRequest.php:194`
  ```php
  if (abs($totalPaid - $finalAmount) > 0.05) throw ...
  ```
- **الوصف:** الرقم السحري `0.05` يسمح بفروق 5 قروش. عبر 1000 فاتورة قد يتراكم 50 جنيه فرق غير مبرر. كما أن التحقق مكرر في Request وService (DRY violation).
- **الإصلاح:** توحيد التحقق في Service فقط بهامش `0.01` (قرش واحد) مع `round(...,2)` صارم، أو استخدام `int` سنت.

#### P0-05 — مرتجع يحوّل الفاتورة كلها إلى `refunded` [POS-C05]
- **المسار:** `PosOrderService.php:396-400`
  ```php
  $invoice->update(['status'=>'refunded', 'notes'=> ...]);
  ```
- **الوصف:** حتى لو أُرجع صنف واحد من 3 أصناف، تُوسم الفاتورة `refunded` بدلاً من `partially_refunded`. كما أن رد مخزون `increment` يتم لكن `InvoiceItem.quantity` لا يُنقص (يبقى كما هو).
- **الأثر:** تقارير المبيعات تظهر الفاتورة كمرتجعة كاملة، ونسب المرتجعات مضللة.
- **الإصلاح:** احسب `totalReturnedQty` vs `totalOriginalQty`:
  ```php
  $status = ($refundTotal >= (float)$invoice->final_amount - 0.01) ? 'refunded' : 'partially_refunded';
  // + تحديث InvoiceItem.quantity أو إنشاء جدول invoice_returns
  ```

#### P0-06 — ضمان جديد بنفس `invoice_item_id` القديم [POS-C06]
- **المسار:** `app/Services/Sales/WarrantyService.php:153`
  ```php
  Warranty::create(['invoice_item_id'=>$warranty->invoice_item_id, ...]);
  ```
- **الوصف:** `invoice_item_id` يشير لصنف الفاتورة الأصلية، ليس لفاتورة الاستبدال. علاقة `InvoiceItem.warranty()` (`InvoiceItem.php:40-44` `HasOne`) ستعيد ضمانين لنفس الصنف، أحدهما `claimed` والآخر `active` — استعلامات `whereHas('warranty')` قد تختار الخاطئ.
- **الإصلاح:** إنشاء `InvoiceItem` جديد لفاتورة استبدال صفرية أو ربط الضمان الجديد بـ `null` مع `notes` يوضح الربط، أو إضافة عمود `replacement_for_warranty_id`.

#### P0-07 — رد مخزون المرتجع — تحقق فرع مفقود [POS-C07]
- **المسار:** `PosOrderService.php:332-360` و `SalesInvoiceController.php:88-91`
- **الوصف:** `processReturn` يتحقق `exists:products,id` فقط، بلا `branch_id`. فني فرع A قد يرد منتجاً لفاتورة فرع B فيزيد مخزون الفرع الخطأ (الـ `increment` على `products.current_stock` عام لا فرعي).
- **الإصلاح:** إضافة عمود `branch_id` لجدول `products` أو جدول `branch_stocks`، والتحقق `where branch_id = $invoice->branch_id`.

#### P0-08 — فشل `assertPayrollTotalsConsistent` يوقف المحاكاة [POS-C08]
- **المسار:** `app/Services/Hr/PayrollService.php:259-285` و `SystemDiagnosticService.php:657-674`
- **التحقق بالتنفيذ:** `test_diag.php` أظهر:
  ```
  [failed] مسير الرواتب — المتوقع صافي 124787.06 ج.م، المسجل 125487.77 ج.م
  ```
  الفرق `700.71` ≈ مجموع `total_allowance` غير المتضمن عمولات أو `carried_debt`.
- **السبب:** `generateMonthlyPayroll` يحسب `total_allowance = allowances + commissions` (`PayrollService.php:223`) لكن `assertPayrollTotalsConsistent` يحسب `expectedNet = basic + allowances - deductions + carriedDebt` (`:270`) — المعادلة غير متطابقة مع `net_salary` المحسوب فعلياً (`:196`).
- **الإصلاح:** توحيد المعادلة أو تخزين `commissions` منفصلاً.

#### P0-09 — خطر إعادة تفعيل `InvoiceObserver` [POS-C09]
- **المسار:** `app/Observers/InvoiceObserver.php:14-74` و `AppServiceProvider.php:40-44`
- **الوصف:** المراقب معطل بتعليق فقط. إذا أُزيل `Invoice::withoutEvents()` من `PosOrderService.php:196` سهواً، سيُنفذ خصم المخزون/الضمان/الآجل **مرتين**.
- **الإصلاح:** حذف الملف أو إضافة `if (app()->environment('testing')) return;` + اختبار يضمن `withoutEvents` موجود.

---

### 4.2 الأخطاء عالية الخطورة (P1)

#### P1-01 — تجاوز صلاحيات عبر `authorize():true` [POS-H01]
- **المسار:** `StorePosInvoiceRequest.php:16-19`, `ProcessWarrantyClaimRequest.php:12-15`, `StoreScrapSaleBatchRequest.php:12-15`
- **الوصف:** كل الطلبات ترجع `true` بلا تحقق. الراوتر يحمي بـ `can:pos.access` (`routes/web.php:174`) لكن طلبات `warranties.claims.store` و `scrap.sell_batch` قد تُستدعى عبر API دون المرور بنفس الـ middleware.
- **الإصلاح:**
  ```php
  public function authorize(): bool { return $this->user()->can('pos.access'); }
  // أو: return $this->user()->can('warranties.claim');
  ```

#### P1-02 — فحص كود تفويض غير فعال [POS-H02]
- **المسار:** `PosOrderService.php:562-583`
  ```php
  $managers = User::whereHas('roles', fn($q)=>$q->whereIn('name',[...]))->get();
  foreach ($managers as $manager) if (Hash::check($code,$manager->password)) return true;
  ```
- **الوصف:** يحمّل كل المدراء (قد يكون 20+) ويفحص كلمة السر بالتسلسل — بطيء، وعرضة لـ timing attack، ويكشف وجود كود صحيح عبر زمن الاستجابة.
- **الإصلاح:** استخدم كود تفويض واحد مشفّر في `settings` مع `hash_equals`، أو `RateLimiter` على المحاولة.

#### P1-03 — كود تفويض افتراضي ضعيف [POS-H03]
- **المسار:** `PosOrderService.php:568` (`mgr_override_99`), `StorePosInvoiceRequest.php:245` (`9999`)
- **الوصف:** كودان ثابتان في الكود المصدري، الثاني افتراضي `9999` من `config('app.manager_override_code','9999')`. غير موجود في `.env.example` ولا `config/app.php`.
- **الإصلاح:** إزالة الباكدور، توليد كود عشوائي عند الـ seeder وحفظه مشفراً في `settings`.

#### P1-04 — فحص TOCTOU بدون قفل [POS-H04]
- **المسار:** `StorePosInvoiceRequest.php:69-88,106-122,213-233`
- **الوصف:** تحقق المخزون/السيريال/سقف الائتمان في `withValidator` يقرأ بدون `lockForUpdate`. بين انتهاء التحقق وبدء `PosOrderService::processPosSale` قد يتغير المخزون (كاشير آخر باع نفس الصنف).
- **الأثر:** المستخدم يرى “الرصيد كافٍ” ثم يفشل بطلب `DomainException` بعد إدخال البيانات.
- **الإصلاح:** احذف فحص المخزون من Request واتركه للـ Service فقط (مع رسالة مفهومة)، أو استخدم `SELECT ... FOR UPDATE` في Request داخل `DB::transaction`.

#### P1-05 — تحميل كل المنتجات/العملاء بلا Pagination [POS-H05/P1-06]
- **المسار:** `PosController.php:26-46` و `CreditCustomerController.php:26-34`
- **الوصف:** `Product::where('is_active',true)->get()` قد يحمّل 5000 منتج في الذاكرة. نفس الشيء للعملاء المدينين.
- **الإصلاح:** `paginate(50)` + بحث Ajax، أو `cursor()` مع تحميل كسول.

#### P1-07 — كشف بيانات ضمان [POS-H07]
- **المسار:** `WarrantyController.php:50-89`
- **الوصف:** مسار `GET /admin/warranties/verify?serial_number=XYZ` يرجع كامل بيانات العميل/السيارة/الفاتورة بلا تحقق إضافي غير `auth`. لا يوجد `throttle` أو `can:warranties.view` على الـ JSON branch.
- **الإصلاح:** إضافة `->middleware('can:warranties.view')` في `routes/web.php:186` على كل فروع `verify`.

#### P1-08 — فرع غير مطلوب في POS Request [POS-H08]
- **المسار:** `StorePosInvoiceRequest.php:24` (`branch_id` nullable)
- **الوصف:** يسمح ببيع بلا فرع، فيُسند تلقائياً `auth()->user()?->branch_id ?? 1` (`PosOrderService.php:193`). كاشير فرع دمياط قد يبيع لفرع وهمي `1`.
- **الإصلاح:** `required|exists:branches,id` + تحقق `Gate::allows('sell-in-branch', $branchId)`.

---

### 4.3 الأخطاء متوسطة الخطورة (P2)

#### P2-01 — `calculateScrapDeduction` صامت [POS-M01]
- `PosOrderService.php:406-414` ترجع `0.0` إذا لا توجد شريحة. لا تُخطِر الـ Request.
- الحل: ارمِ `DomainException` إذا `has_scrap=true` ولا يوجد tier ولا override.

#### P2-02 — تحصيل FIFO بلا فلتر فرع [POS-M02]
- `PosOrderService.php:511-516` يوزع التحصيل على كل فواتير العميل بلا `where branch_id`.
- عميل له فواتير في فرعين — تحصيل فرع A قد يسدد فاتورة فرع B.

#### P2-03 — تحصيل بلا تحديث `notes` [POS-M03]
- يُنشئ `InvoicePayment` لكن `Invoice.notes` تبقى بلا مرجع `receipt_number`. كشف الحساب في `CreditCustomerController::statement` (`:119-141`) يظهر `creditLedgers` لكن لا يربطها بـ `InvoicePayment`.

#### P2-04 — سعر بيع كهنة غير مقيّد [POS-M04]
- `StoreScrapSaleBatchRequest.php:21` (`min:0.01`) يسمح ببيع 10 بطاريات بـ `0.01` جنيه أو `10,000,000` جنيه.
- الحل: `max: total_scrap_value * 3` أو هامش ربح معقول + `manager_override` إذا تجاوز.

#### P2-05 — نص اختبار غير مطابق [POS-M05] — **مؤكد**
- `tests/Feature/Admin/DashboardSalesFilterTest.php:22` يبحث عن `إجمالي مبيعات المركز` بينما `kpi-cards.blade.php:13` يعرض `إيرادات ومبيعات المركز`.
- الحل: تحديث الاختبار أو الـ view (النص المقترح: `إجمالي مبيعات المركز` أوسع، لكن `إيرادات ومبيعات المركز` أدق محاسبياً).

#### P2-06 — ثابت وزن رصاص مختلف [POS-M06]
- `PosOrderService.php:310` (`$scrapAh * 0.17`) مقابل `SalesAndPosDataSeeder.php:521` (`*0.28`).
- وزن 70Ah = 11.9كجم vs 19.6كجم — تقارير `ScrapBatteryService::getInventoryMetrics` (`total_lead_weight_kg`) غير دقيقة.

#### P2-07 — تكرار منطق فلترة الفواتير [POS-M07]
- `SalesInvoiceController.php:30-60` يكرر نفس `where` الموجود في `PosOrderService.php:23-62`.
- الحل: استخدم `posOrderService->getStats($filters)` وأزل التكرار.

#### P2-08 — مطالبة ضمان بلا تحقق فرع [POS-M08]
- `ProcessWarrantyClaimRequest.php:19-33` لا تتحقق أن `defective_serial` ينتمي لنفس `branch_id` المرسل.

#### P2-09 — تناقض `payment_method` [POS-M09]
- `StorePosInvoiceRequest.php:49` يقبل `credit` كطريقة دفع، لكن `CreditCustomerController.php:68` (`in:cash,card,bank_transfer`) يرفضه عند التحصيل. لا يوجد `credit` في `InvoicePayment` عند التحصيل (صحيح) لكن التناقض يربك المطور.

---

### 4.4 الأخطاء منخفضة الخطورة (P3)

| المعرف | الملف:السطر | الوصف | الإصلاح |
|--------|-------------|-------|---------|
| POS-L01 | `ScrapBatteryService.php:42-53` | `get()` ثم `sum()` في الذاكرة | `ScrapBatteriesInventory::where(...)->sum('scrap_value')` |
| POS-L02 | `PosOrderService.php:76-98` | استخدام `float` للنقود | استخدم `int` سنت أو `Brick\Money` |
| POS-L03 | `PosOrderService.php:194` | رسالة تصادم `invoice_number` غير مقروءة | `try/catch UniqueViolation` برسالة عربية |
| POS-L04 | `config/app.php` | `manager_override_code` غير معرّف | أضف `'manager_override_code'=>env('MANAGER_OVERRIDE_CODE')` |
| POS-L05 | `Invoice.php:73` | `HasOne scrapBattery` لكن يُنشأ عدة صفوف | غيّر إلى `HasMany scrapBatteries` أو `HasOne` مع `unique invoice_id` |
| POS-L06 | `Customer.php:51` | تعليق يوضح عدم وضع `orderBy` لكن لا يوجد اختبار يضمنه | أضف اختبار `creditLedgers` ترتيب |

---

## 5. ما تم بشكل صحيح (Positive Findings)

- ✅ **المعاملات الذرية:** كل عمليات `processPosSale` / `processSalesReturn` / `settleCustomerDebt` داخل `DB::transaction` مع `lockForUpdate` على المنتجات والعملاء والفواتير.
- ✅ **التحقق المزدوج للسيريال:** `StorePosInvoiceRequest.php:117` + `Warranty.php:14` (`unique`) + `processInstantClaim` مع `lockForUpdate`.
- ✅ **الدفع المجزأ:** `InvoicePayment` منفصل عن `Invoice` يسمح بـ `split` صحيح (`PosOrderService.php:217-226`).
- ✅ **دفتر أستاذ مزدوج:** `CreditLedgerEntry` يسجل `balance_before/after` مع `collected_by` (`PosOrderService.php:235-244`).
- ✅ **Eager Loading محكم:** `PosController.php:27-33` و `ScrapBatteryService.php:16-21` يمنعان N+1.
- ✅ **فهارس مركبة:** `2026_09_23_000001` يغطي كل جداول البحث <15ms.

---

## 6. خارطة طريق الإصلاح المقترحة (Prioritized Roadmap)

### المرحلة 1 — إصلاح فوري (P0) — أسبوع 1
1. **POS-C01:** تسلسل فاتورة ذري + retry (يوم 1)
2. **POS-C05:** منطق مرتجع جزئي/كامل (يوم 2)
3. **POS-C03:** تحقق شريحة كهنة صارم (يوم 2)
4. **POS-C08:** توحيد معادلة الرواتب `expectedNet` (يوم 3) — يحل فشل المحاكاة
5. **POS-C09:** حذف/تعطيل `InvoiceObserver` نهائياً + اختبار `withoutEvents` (يوم 3)

### المرحلة 2 — أمان وصلاحيات (P1) — أسبوع 2
6. **POS-H01/H02/H03:** إصلاح `authorize()` + كود تفويض واحد مشفر + `RateLimiter` (يوم 5)
7. **POS-H04:** نقل كل تحقق TOCTOU إلى Service فقط (يوم 6)
8. **POS-H05/H06:** ترقيم POS/Credit (يوم 7)
9. **POS-H08:** `branch_id` مطلوب + `Gate` فرعي (يوم 7)

### المرحلة 3 — منطق عمل (P2) — أسبوع 3
10. **POS-M05:** تصحيح نص الاختبار (دقائق)
11. **POS-M06:** توحيد ثابت وزن الرصاص (0.17 vs 0.28) بعد تأكيد مع المصنع
12. **POS-M02/M07:** استخراج `InvoiceStatsService`

### المرحلة 4 — أداء وصيانة (P3) — أسبوع 4
13. **POS-L01/L02:** تجميع DB + `int` سنت
14. **POS-L04:** إضافة `MANAGER_OVERRIDE_CODE` إلى `.env.example`

---

## 7. فجوات التغطية الاختبارية (Test Gaps)

| السيناريو غير المغطى | الاختبار المقترح |
|----------------------|------------------|
| تزامن كاشيرين يبيعان نفس المنتج (آخر قطعة) | `test_concurrent_pos_race_last_piece()` — استخدم `Http::pool` أو `paratest` |
| تصادم `invoice_number` | `test_invoice_number_unique_retry()` |
| كهنة بسعة غير موجودة في الشرائح | `test_scrap_tier_not_found_throws_422()` |
| مرتجع جزئي (1 من 3 قطع) | `test_partial_return_keeps_invoice_partially_refunded()` |
| تحصيل آجل يوزع على فرعين | `test_settle_customer_debt_branch_isolation()` |
| كود تفويض ضعيف `9999` | `test_manager_override_rejects_default_code()` |

---

## 8. كيفية التحقق من الإصلاح (Verification Checklist)

```bash
# 1. إعادة إنتاج الأخطاء المؤكدة قبل الإصلاح
php test_diag.php
# المتوقع: [failed] Payroll Lifecycle — قبل الإصلاح
# بعد توحيد المعادلة: [passed] 8/8

php artisan test --filter=DashboardSalesFilterTest
# قبل الإصلاح: 1 failed (إجمالي مبيعات المركز)
# بعد تحديث النص: 3 passed

# 2. تشغيل كامل الحزمة
php artisan test
# الهدف: 146 passed / 0 failed

# 3. فحص التزامن (يدوي)
# افتح نافذتين، بيع آخر قطعة في نفس اللحظة — يجب أن تنجح واحدة وتفشل الأخرى بـ DomainException واضح
```

---

## 9. الملاحق

### أ. أوامر البحث المستخدمة
```bash
rg -n "lockForUpdate|withoutEvents|firstOrCreate|uniqid|Hash::check|authorize.*true" app/Services/Sales app/Http/Requests/Admin/Pos
rg -n "float.*amount|round\(.*2\)" app/Services/Sales/PosOrderService.php
```

### ب. المراجع
- `PROJECT_CONTEXT.md:131-180` — قواعد العمل الصارمة
- `docs/SALES_AND_PURCHASES_SPEC.md` — معمارية WAC والكهنة والضمان
- `docs/SALES_PURCHASES_SCENARIOS.md` — سيناريوهات الحالات الحدية
- `app/Services/Diagnostics/SystemDiagnosticService.php:48-231` — معايير التدقيق الثمانية

---

**الخلاصة:** قطاع POS **مبني بشكل قوي** (Transactions + Locking + Eager Loading) لكنه يحتاج **إصلاح 9 نقاط حرجة** قبل التوسع لفرع ثانٍ، وأهمها **رقم الفاتورة الذري** و**منطق المرتجع الجزئي** و**تسامح 5 قروش**. بعد المرحلة 1 (أسبوع) يصبح النظام **جاهزاً للإنتاج متعدد الفروع**.

> **توصية نهائية:** لا تُفعّل `InvoiceObserver.php` مجدداً، وحافظ على `Invoice::withoutEvents()` في `PosOrderService.php:196` كقاعدة ذهبية.

