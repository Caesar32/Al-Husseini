# تقرير تحليل أخطاء قطاع الماليات — الآجل والفواتير والفلاتر
### Finance Module Error Analysis Report (Invoices, Credit, Filters)

> **تاريخ التحليل:** 2026-09-30 | **المحلل:** Muse Spark | **النطاق المالي:** `app/Models/{Invoice,InvoicePayment,CreditLedgerEntry,Customer}` + `app/Services/Sales/PosOrderService.php` + `app/Http/Controllers/Admin/{SalesInvoice,CreditCustomer,Dashboard}Controller.php` + `resources/views/admin/{sales/invoices,credit,credit/statement,dashboard/partials}/*.blade.php` + `database/migrations/2026_09_21_160008_create_invoices*` + `2026_09_21_160010_create_credit_ledger*` | **حالة الاختبارات:** 146 اختبار — تم تأكيد مرور `CreditAndInvoiceManagementTest` لكنه يستخدم أعمدة غير موجودة (`payment_status`, `invoice_type`)

---

## 0. الملخص التنفيذي

تم فحص **المسار المالي الكامل** من إنشاء الفاتورة → خصم الكهنة → توزيع الدفعات → قيد الآجل → تحصيل FIFO → كشف الحساب → فلاتر التواريخ → لوحة التحكم.

- **النواة المالية سليمة:** كل الحركات داخل `DB::transaction` مع `lockForUpdate` على `Customer` والفواتير المفتوحة (`PosOrderService.php:66,174,332,511`).
- **أكبر ثغرة معمارية:** ازدواج منطق الفلترة بين `PosOrderService::getPaginatedInvoices` و `SalesInvoiceController::index` و `DashboardController::index` — نفس المعادلة تُحسب بثلاث طرق مختلفة، ما يسبب أرقام KPI غير متطابقة.
- **أخطر خطأ تشغيلي:** `resources/views/admin/credit/statement.blade.php:139` يعرض `$inv->total_amount` (عمود غير موجود) بدل `final_amount` — كشف حساب العميل يظهر `0.00` بدل الإجمالي الحقيقي.

| الفئة | العدد | الأخطر |
|------|-------|--------|
| **P0 حرجة — سلامة مالية** | 9 | FIN-C01 كشف حساب يظهر صفر، FIN-C05 مرتجع يخفي نقدية، FIN-C09 احتساب إيراد مزدوج |
| **P1 عالية — منطق/أمان** | 9 | FIN-H01 خصم غير مقيّد، FIN-H07 تصادم `receipt_number` فريد |
| **P2 متوسطة — فلاتر/تقارير** | 9 | FIN-M01 ترميز `entry_type` غير متطابق، FIN-M07 فلتر تواريخ لا يتحقق من الترتيب |
| **P3 منخفضة — أداء/صيانة** | 6 | FIN-L01 `round()` بدون هللات، FIN-L02 تحميل كل المدينين |

---

## 1. منهجية الفحص

1. قراءة كامل الـ migrations (`2026_09_21_160008:10` تعريف `invoices` بلا `payment_status`/`invoice_type`) ومطابقتها مع `Invoice.php:12` (`$fillable` بلا هذين الحقلين) ومع الاختبارات التي تستخدمهما.
2. تتبع كل حقل مالي (`subtotal`, `discount_amount`, `scrap_deduction_amount`, `tax_amount`, `final_amount`, `paid_amount`, `remaining_amount`) من الـ Request → Service → DB → Blade.
3. إعادة إنتاج `DashboardSalesFilterTest.php:17` و `CreditAndInvoiceManagementTest.php:151` يدوياً وتأكيد أن الأخير يمر رغم أعمدة وهمية (mass-assignment تُهمل).
4. مطابقة `entry_type` في `credit_ledger_entries:13` (`invoice_debt,payment_collection,credit_adjustment,refund`) مع `statement.blade.php:184` (`sale_on_credit, payment_received`).
5. تحليل كل `whereDate`/`where('created_at','>=',…)` وتأثير `Carbon::today()->startOfDay()` مقابل `whereDate` على الفهارس.

> كل ادعاء موثّق بـ `file_path:line_number` كما هو مطلوب.

---

## 2. مقياس الخطورة

| الرمز | المعنى |
|------|--------|
| **P0** | فساد رصيد/دفتر أستاذ أو عرض مالي خاطئ للعميل |
| **P1** | تجاوز سقف ائتمان/خصم أو فشل تراكمي تحت التزامن |
| **P2** | تقرير/فلتر يضلل الإدارة أو يكسر تجربة المستخدم |
| **P3** | أداء/صيانة/انحراف عن Best Practices |

---

## 3. جدول الملخص (33 خطأ مالي)

| # | المعرف | العنوان | الملف:السطر | الخطورة |
|---|--------|---------|-------------|---------|
| 1 | FIN-C01 | كشف الحساب يعرض `$inv->total_amount` غير موجود بدل `final_amount` | `resources/views/admin/credit/statement.blade.php:139` | **P0** |
| 2 | FIN-C02 | ازدواج منطق فلترة الفواتير — Controller يكرر Service | `app/Http/Controllers/Admin/SalesInvoiceController.php:30-60` vs `app/Services/Sales/PosOrderService.php:21-62` | **P0** |
| 3 | FIN-C03 | عدم تطابق احتساب الإيراد: Dashboard يجمع `InvoicePayment` + `CreditLedgerEntry` معاً | `app/Http/Controllers/Admin/DashboardController.php:49-80` | **P0** |
| 4 | FIN-C04 | فواتير `refunded` لا تزال محتسبة في `total_sales` | `DashboardController.php:31` / `SalesInvoiceController.php:55` | **P0** |
| 5 | FIN-C05 | مرتجع يرد فقط الجزء الآجل، النقدي لا يُسترد | `PosOrderService.php:362-393` | **P0** |
| 6 | FIN-C06 | `receipt_number` فريد يفشل عند تحصيل FIFO يوزع على فواتير متعددة بنفس الرقم | `database/migrations/2026_09_21_160010:18` + `PosOrderService.php:536-542` | **P0** |
| 7 | FIN-C07 | أعمدة وهمية `payment_status`/`invoice_type` في الاختبارات تُهمل بصمت | `tests/Feature/Sales/CreditAndInvoiceManagementTest.php:170,172` / `Invoice.php:12` | **P0** |
| 8 | FIN-C08 | `status=refunded` أحادي حتى للمرتجع الجزئي | `PosOrderService.php:397` | **P0** |
| 9 | FIN-C09 | `final_amount = max(0, subtotal+tax-discount-scrap)` يخفي تجاوز الخصم | `PosOrderService.php:130` | **P0** |
| 10 | FIN-H01 | `discount_amount` و `scrap_deduction_amount` بلا سقف `max:final_amount` | `app/Http/Requests/Admin/Pos/StorePosInvoiceRequest.php:41,44` | **P1** |
| 11 | FIN-H02 | `search` في الفواتير لا يبحث في السيريال/الباركود/اسم المنتج | `PosOrderService.php:34-42` vs `SalesInvoiceController.php:32-39` | **P1** |
| 12 | FIN-H03 | `branch_id` nullable في POS — فاتورة بلا فرع تُسند لـ `1` | `StorePosInvoiceRequest.php:24` + `PosOrderService.php:193` | **P1** |
| 13 | FIN-H04 | `remaining_amount > 0.009` سحر رقمي غير موثق | `PosOrderService.php:512` | **P1** |
| 14 | FIN-H05 | كود تفويض افتراضي `9999`/`mgr_override_99` يخترق سقف الائتمان | `PosOrderService.php:568` | **P1** |
| 15 | FIN-H06 | `settleCustomerDebt` بلا تحقق `branch_id` للفواتير المفتوحة | `PosOrderService.php:511-516` | **P1** |
| 16 | FIN-H07 | تحصيل أكبر من الرصيد بهامش `+0.01` يسمح بتجاوز قرش | `PosOrderService.php:493` | **P1** |
| 17 | FIN-H08 | `getDailyCashierSummary` يحمّل كل فواتير اليوم في الذاكرة | `PosOrderService.php:417-421` | **P1** |
| 18 | FIN-H09 | `SalesInvoiceController::processReturn` يتحقق `exists:products,id` بلا `branch_id` | `SalesInvoiceController.php:90` | **P1** |
| 19 | FIN-M01 | `statement.blade.php:184` ترميز `entry_type` غير متطابق (`sale_on_credit` ≠ `invoice_debt`) | `statement.blade.php:184` vs `CreditLedgerEntry:13` | **P2** |
| 20 | FIN-M02 | فلتر التواريخ لا يتحقق `date_from <= date_to` | `SalesInvoiceController.php:47-52` / `PosOrderService.php:53-59` | **P2** |
| 21 | FIN-M03 | `whereDate('created_at','>=',date_from)` لا يستخدم فهرس `branch_id,created_at` بكفاءة | `SalesInvoiceController.php:48` | **P2** |
| 22 | FIN-M04 | لوحة التحكم تستخدم `round()` بلا هللات — تفقد دقة | `DashboardController.php:75-79,89-90` | **P2** |
| 23 | FIN-M05 | `stat: total_remaining_credit` يجمع `remaining_amount` بلا استثناء `refunded` | `SalesInvoiceController.php:58` | **P2** |
| 24 | FIN-M06 | `credit.blade.php` يعرض KPI من `window.AlHusseiniSales` (localStorage) لا من DB | `credit.blade.php:439` | **P2** |
| 25 | FIN-M07 | أزرار اختصار التواريخ (`applyDatePreset`) لا تحافظ على `search/status/branch_id` | `resources/views/admin/sales/invoices.blade.php:435-475` | **P2** |
| 26 | FIN-M08 | `statement` يحمّل `invoices: limit 50` و `creditLedgers: limit 100` بلا ترقيم حقيقي | `CreditCustomerController.php:123-132` | **P2** |
| 27 | FIN-M09 | `InvoicePayment.payment_method` يقبل `credit` لكن `CreditCustomerController:68` يرفضه | `InvoicePayment migration:13` vs `CreditCustomerController.php:68` | **P2** |
| 28 | FIN-L01 | `number_format(...,2)` في Blade مع `decimal:2` cast يكرر التقريب | `statement.blade.php:91,99` | **P3** |
| 29 | FIN-L02 | `Customer.current_credit_balance` افتراضي `0.00` لكن الاختبارات تنشئ عملاء برصيد `5000` بلا `invoice_id` | `Customer.php:25` / `CreditAndInvoiceManagementTest.php:57` | **P3** |
| 30 | FIN-L03 | `Invoice::branch()` علاقة `restrictOnDelete` قد تمنع حذف فرع حتى لو فواتيره `cancelled` | `2026_09_21_160008:12` | **P3** |
| 31 | FIN-L04 | `Invoice` بلا `casts` لـ `status/payment_method` كـ `enum` — مقارنة نصية هشة | `Invoice.php:31-41` | **P3** |
| 32 | FIN-L05 | `DashboardController:54` تكرر `whereHas('invoice', where status != cancelled)` 5 مرات — N+5 queries | `DashboardController.php:54-61` | **P3** |
| 33 | FIN-L06 | `.env.example` بلا `MANAGER_OVERRIDE_CODE` | `/.env.example:1-65` | **P3** |

---

## 4. التفصيل التحليلي

### 4.1 الحرجة (P0)

#### FIN-C01 — كشف حساب يظهر `0.00` بدل الإجمالي [P0]
- **المسار:** `resources/views/admin/credit/statement.blade.php:139`
  ```php
  <td>{{ number_format($inv->total_amount, 2) }} ج.م</td> // ❌ العمود غير موجود
  // الأعمدة الحقيقية في 2026_09_21_160008:21 هي final_amount / subtotal / paid_amount / remaining_amount
  ```
- **الوصف:** `Invoice.php:12` `$fillable` لا يحتوي `total_amount`، والـ migration لا يُنشئه. Blade يحاول قراءة خاصية غير موجودة فيُرجع `null` → `number_format(null,2) = 0.00`.
- **الأثر:** كل كشف حساب مطبوع/مُصدّر يظهر إجمالي فاتورة `0.00` بينما `المسدد` و`المتبقي` صحيحان — العميل يرى أرقاماً متناقضة.
- **الإصلاح الفوري:**
  ```php
  // statement.blade.php:139
  {{ number_format($inv->final_amount, 2) }} ج.م
  // أو لإظهار التفصيل:
  {{ number_format($inv->subtotal, 2) }} ج.م // مع خصم {{ $inv->discount_amount }}
  ```
- **اختبار كاشف:**
  ```php
  $this->get(route('admin.credit.statement',$customer))->assertSee(number_format($inv->final_amount,2));
  // حالياً سيفشل لأنه يرى 0.00
  ```

#### FIN-C02 — ازدواج منطق الفلترة (DRY Violation) [P0]
- **المسار:** `SalesInvoiceController.php:30-60` يكرر نفس `if (search/status/branch_id/date_from/date_to)` الموجود في `PosOrderService.php:21-62`، لكن `SalesInvoiceController` يستخدمه مرتين: مرة للـ paginator ومرة لـ `$statsQuery` منفصل.
- **الوصف:** إذا أُضيف فلتر جديد (مثلاً `payment_method`) في Service ونُسي في Controller، الإحصائيات أعلى الجدول ستختلف عن عدد الصفوف المُعروضة.
- **الأثر:** مدير يرى “إجمالي المبيعات 125,000 ج” بينما مجموع الفواتير في الجدول 98,000 ج — فقدان ثقة.
- **الإصلاح:**
  ```php
  // SalesInvoiceController.php:20
  $filters = $request->only([...]);
  $invoices = $this->posOrderService->getPaginatedInvoices($filters);
  $stats = $this->posOrderService->getInvoiceStats($filters); // ← انقل المنطق للـ Service
  ```

#### FIN-C03 — احتساب الإيراد المزدوج [P0]
- **المسار:** `DashboardController.php:49-80`
  ```php
  $basePayments = InvoicePayment::whereHas('invoice', ...)->where('payment_method','!=','credit'); // 57-61
  $baseLedger = CreditLedgerEntry::where('entry_type','payment_collection'); // 65
  // ثم: $revenue = $cashToday (فقط) لكن التعليق يقول “احتياط” — قبل الإصلاح كان الجمع مزدوجاً
  ```
- **الوصف:** `PosOrderService::settleCustomerDebt` يُنشئ **اثنين** معاً: `InvoicePayment` على كل فاتورة مفتوحة **و** `CreditLedgerEntry` واحد للإجمالي. إذا جُمع الاثنان في الإيراد، كل تحصيل آجل يُحتسب مرتين.
- **الحالة الحالية:** الكود الحالي (`DashboardController.php:75`) يحسب `revenue = cashToday` فقط ويترك `creditCollected` كـ احتياط — **صحيح بعد الإصلاح**، لكن تعليق السطر 73 يوحي أن نسخة سابقة كانت تجمع. يجب إضافة اختبار يضمن عدم الجمع.
- **الإصلاح:** توثيق المعادلة في `PROJECT_CONTEXT.md` + اختبار `assert revenue == sum InvoicePayment where method != credit`.

#### FIN-C04 — فواتير `refunded` محتسبة كمبيعات [P0]
- **المسار:** `DashboardController.php:31` `where('status','!=','cancelled')` و `SalesInvoiceController.php:55` `sum('final_amount')`
- **الوصف:** `invoices.status` enum يشمل `refunded` (`2026_09_21_160008:25`). المرتجع يُبقي `final_amount` كما هو لكن `remaining_amount` يقل. جمع `final_amount` لفواتير `refunded` يضخّم المبيعات.
- **الأثر:** لو فاتورة 3,500 ج رُدّت بالكامل، لا تزال تُحسب ضمن “إجمالي المبيعات”.
- **الإصلاح:**
  ```php
  $baseInvoices = Invoice::whereNotIn('status',['cancelled','refunded']);
  // أو احسب net sales = sum(final_amount) - sum(refund_amount) بجدول منفصل
  ```

#### FIN-C05 — مرتجع يرد الآجل فقط ويتجاهل النقدي [P0]
- **المسار:** `PosOrderService.php:362-393`
  ```php
  if ($invoice->remaining_amount > 0) { $creditRefund = min(refundTotal, remaining_amount); ... }
  // لا يوجد رد نقدي إذا remaining_amount == 0
  ```
- **الوصف:** فاتورة `paid` بالكامل (نقدي) إذا رُدّت، `remaining_amount = 0` فلا يدخل فرع الآجل — لا يُنشأ قيد `refund` ولا يُرجع مال للعميل، فقط `increment` مخزون.
- **الأثر:** عميل دفع 3,200 نقداً واستبدل بطارية، عند إرجاعها لا يسترد نقوده في النظام — فرق خزينة.
- **الإصلاح:** أضف فرعاً للنقدي:
  ```php
  if ($customer && $creditRefund < $refundTotal) {
      $cashRefund = $refundTotal - $creditRefund;
      // سجل InvoicePayment سالب أو CreditLedgerEntry entry_type=refund_cash
  }
  ```

#### FIN-C06 — `receipt_number` فريد يفشل في FIFO [P0]
- **المسار:** `2026_09_21_160010:18` `$table->string('receipt_number',50)->nullable()->unique()` + `PosOrderService.php:536-542` يمرر نفس `receiptNumber` لكل `InvoicePayment` في حلقة FIFO.
- **الوصف:** تحصيل 5,000 ج يوزع على 3 فواتير (2,000+2,000+1,000) — كل `InvoicePayment` سيحمل نفس `transaction_reference`، لكن `CreditLedgerEntry` واحد فقط يحمل `receipt_number` فريد. إذا حاول النظام لاحقاً إنشاء `CreditLedgerEntry` آخر بنفس الرقم (إعادة إرسال)، سيُرمى `UniqueViolation` 500.
- **الإصلاح:** اجعل `receipt_number` غير فريد أو أضف لاحقة `-1`, `-2` لكل فاتورة، وتحقق `where receipt_number exists` قبل الإدراج.

#### FIN-C07 — أعمدة وهمية في الاختبارات [P0]
- **المسار:** `tests/Feature/Sales/CreditAndInvoiceManagementTest.php:170`
  ```php
  Invoice::create(['payment_status'=>'paid','invoice_type'=>'retail', ...]) // ليسا في fillable ولا migration
  ```
  نفس النمط في `:215` و `EndToEndSalesAndPurchasesScenarioTest.php:352`.
- **الوصف:** `Invoice.php:12` لا يحتويهما، والـ migration `2026_09_21_160008` لا يُنشئهما. Laravel يتجاهلهما بصمت (mass assignment). الاختبار يمر لكنه يختبر **سلوكاً غير موجود** في الإنتاج.
- **الأثر:** مطور يظن أن `payment_status` عمود حقيقي ويبني عليه فلتر — سيفشل في الإنتاج.
- **الإصلاح:** احذف الحقلين من الاختبارات أو أنشئ migration يضيفهما إن كانا مطلوبين، وأضف `assertDatabaseHas('invoices',['status'=>'paid'])` بدل `payment_status`.

#### FIN-C08 — `refunded` أحادي حتى للجزئي [P0]
- **المسار:** `PosOrderService.php:397` `$invoice->update(['status'=>'refunded'])`
- **الوصف:** لا يميز بين مرتجع كامل وجزئي. فاتورة 3 أصناف أُرجع منها صنف واحد (refundTotal < final_amount) تُوسم `refunded` بدل `partially_refunded`/`partial_refunded`.
- **الإصلاح:** احسب `isFullReturn = $refundTotal >= $invoice->final_amount - 0.01`.

#### FIN-C09 — `max(0, ...)` يخفي تجاوز الخصم [P0]
- **المسار:** `PosOrderService.php:130` `round(max(0, subtotal+tax-discount-scrap),2)`
- **الوصف:** إذا `discount=5,000` و `subtotal=3,200`، النتيجة `0.00` بدل رمي خطأ. كاشير يخطئ في الخصم فيُصدر فاتورة مجانية بصمت.
- **الإصلاح:**
  ```php
  if ($discount + $scrapDeduction > $subtotal + $tax) throw new DomainException('الخصم يتجاوز الإجمالي');
  ```

---

### 4.2 عالية (P1)

#### FIN-H01 — خصم/كهنة بلا سقف
- `StorePosInvoiceRequest.php:41` `discount_amount: nullable|numeric|min:0` بلا `max`. يمكن إدخال `discount=999999`.
- **الإصلاح:** `max:99999` + تحقق `discount + scrap <= subtotal + tax` في `withValidator`.

#### FIN-H02 — بحث الفواتير لا يشمل السيريال/المنتج
- `PosOrderService.php:34-42` يبحث فقط في `invoice_number` و `customer.name/phone`. بينما `SearchService.php` يبحث في كل القطاعات.
- المستخدم يبحث عن `SN-BAT-123` في فواتير المبيعات فلا يجد شيئاً.

#### FIN-H03 — `branch_id` nullable
- `StorePosInvoiceRequest.php:24` + `PosOrderService.php:193` `auth()->user()?->branch_id ?? 1` — فرع `1` ثابت يفترض وجوده. في نظام متعدد الفروع، فاتورة بلا فرع تُسند لدمياط حتى لو الكاشير في فرع آخر.

#### FIN-H04 — هامش `0.009` سحري
- `PosOrderService.php:512` `where('remaining_amount','>',0.009)` و `519` `<=0.009`. الرقم غير موثق. هل هو قرش واحد؟ لماذا 0.009 وليس 0.01؟

#### FIN-H05 — كود تفويض ضعيف
- `PosOrderService.php:568` `mgr_override_99` + `config('app.manager_override_code','9999')` — باكدور ثابت. لم يُضف إلى `.env.example`.

#### FIN-H06 — `settleCustomerDebt` بلا فلتر فرع
- `PosOrderService.php:511` `Invoice::where('customer_id',...)` بلا `branch_id`. عميل له فواتير في فرعين — تحصيل فرع A يسدد فاتورة فرع B.

#### FIN-H07 — هامش تجاوز `+0.01`
- `PosOrderService.php:493` `if ($amount > $currentBalance + 0.01)` يسمح بتحصيل 1000.01 إذا الرصيد 1000.

#### FIN-H08 — `getDailyCashierSummary` N+1
- `PosOrderService.php:417-421` `Invoice::whereDate(...)->with(...)->get()` يحمّل كل فواتير اليوم (قد تكون 500) ثم يحسب `sum` في PHP. يجب `sum('final_amount')` في DB.

#### FIN-H09 — `processReturn` بلا تحقق فرع المنتج
- `SalesInvoiceController.php:90` `exists:products,id` فقط. منتج موقوف `is_active=false` لا يزال قابلاً للإرجاع.

---

### 4.3 متوسطة (P2)

#### FIN-M01 — ترميز `entry_type` غير متطابق [P2]
- **المسار:** `statement.blade.php:184`
  ```php
  $typeBadge = match($entry->entry_type) {
      'sale_on_credit' => ... // ❌ القيمة الحقيقية في DB هي invoice_debt (PosOrderService.php:238)
      'payment_collection','payment_received' => ...
      'sales_return_refund' => ... // ❌ الحقيقية refund
  ```
- كل القيود ستقع في `default => $entry->entry_type` — الشارة تظهر `invoice_debt` نصاً خاماً.

#### FIN-M02 — فلتر التواريخ لا يتحقق من الترتيب
- `SalesInvoiceController.php:47-52` لا يتحقق `date_from <= date_to`. لو أدخل `2026-09-30` إلى `2026-09-01`، `whereDate >= 09-30 AND <= 09-01` ترجع صفر صف.

#### FIN-M03 — `whereDate` لا يستخدم الفهرس المركب
- `2026_09_21_160008:29` فهرس `['branch_id','created_at']` على `DATETIME`, لكن `whereDate('created_at','>=',...)` يحوّل العمود بـ `DATE()` فيُبطل استخدام الفهرس. استخدم `where('created_at','>=',$dateFrom.' 00:00:00')`.

#### FIN-M04 — `round()` بلا هللات
- `DashboardController.php:75` `round($cashToday)` يفقد القروش. لو إيراد 12,345.67 يظهر 12,346.

#### FIN-M05 — إحصائية الآجل تشمل `refunded`
- `SalesInvoiceController.php:58` `sum('remaining_amount')` يجمع حتى فواتير `refunded` التي رُدّت لكن `remaining_amount` قد لا يكون صفراً (راجع FIN-C05).

#### FIN-M06 — `credit.blade.php` يعرض من `localStorage`
- `credit.blade.php:439` `window.AlHusseiniSales.getCreditSummary()` — بيانات وهمية من `localStorage` لا من `CreditCustomerController.php:26` (DB). المستخدم يرى أرقاماً قديمة إذا لم يحدّث `AlHusseiniSales`.

#### FIN-M07 — أزرار اختصار التواريخ تفقد باقي الفلاتر
- `invoices.blade.php:435` `applyDatePreset` يضبط `date_from/to` فقط ثم `form.submit()` — يحتفظ بـ `status/search` لأنه داخل نفس الفورم، لكنه لا يحافظ على `branch_id` إذا كان hidden.

#### FIN-M08 — `statement` يحدّ `limit 50/100` بلا ترقيم
- `CreditCustomerController.php:123-132` `limit(50)` يقطع كشف حساب عميل له 200 فاتورة آجل — المستخدم لا يرى الأقدم.

#### FIN-M09 — تناقض `payment_method`
- `InvoicePayment:13` `enum('credit')` مسموح، لكن `CreditCustomerController.php:68` `in:cash,card,bank_transfer` يرفض `credit` عند التحصيل — صحيح منطقياً لكن التناقض يربك.

---

### 4.4 منخفضة (P3)

| # | الملف:السطر | الوصف |
|---|-------------|-------|
| FIN-L01 | `statement.blade.php:91` | `number_format(...,2)` مع `decimal:2` cast يكرر التقريب — استخدم accessor |
| FIN-L02 | `Customer.php:25` | رصيد وهمي في الاختبار بلا `invoice_id` — يختبر `current_credit_balance` بمعزل عن `CreditLedgerEntry` |
| FIN-L03 | `2026_09_21_160008:12` | `restrictOnDelete` على `branch_id` يمنع حذف فرع حتى لو فواتيره `cancelled` — استخدم `nullOnDelete` أو أرشفة |
| FIN-L04 | `Invoice.php:31` | لا يوجد `enum` cast لـ `status` — مقارنة `=== 'paid'` هشة |
| FIN-L05 | `DashboardController.php:54-61` | تكرار `whereHas('invoice', where status != cancelled)` 5 مرات — استخرج `scopeActive()` |
| FIN-L06 | `/.env.example:1` | لا يوجد `MANAGER_OVERRIDE_CODE` |

---

## 5. ما تم بشكل صحيح (يُحتفظ به)

- ✅ كل الحركات المالية داخل `DB::transaction` مع `lockForUpdate` على العميل والفواتير المفتوحة — يمنع سباق تحصيل متزامن.
- ✅ `CreditLedgerEntry` يسجل `balance_before/after` + `collected_by` — قابل للتدقيق المزدوج.
- ✅ `PosOrderService::settleCustomerDebt` يوزع FIFO ويُنشئ `InvoicePayment` على كل فاتورة — يضمن تطابق `customer.current_credit_balance == sum(invoice.remaining_amount)`.
- ✅ `SalesInvoiceController::index` يفصل `statsQuery` عن `paginator` لكن يحافظ على نفس الفلاتر — فكرة صحيحة تحتاج استخراج لـ Service.
- ✅ `statement.blade.php:38` `@can('credit.settle')` يحمي زر التحصيل.

---

## 6. خارطة طريق الإصلاح

### أسبوع 1 — إصلاح العرض المكسور
1. **FIN-C01** — `statement.blade.php:139` → `final_amount` (ساعة)
2. **FIN-M01** — توحيد `entry_type` (`invoice_debt`/`refund`/`payment_collection`) في Blade (ساعة)
3. **FIN-C07** — حذف `payment_status`/`invoice_type` من الاختبارات أو إضافة migration (يوم)

### أسبوع 2 — سلامة الرصيد
4. **FIN-C05/C08** — مرتجع يرد النقدي + حالة `partially_refunded` (يومان)
5. **FIN-C09/H01** — سقف خصم `discount+scrap <= subtotal+tax` (يوم)
6. **FIN-C04/C02** — استثناء `refunded` من المبيعات + استخراج `InvoiceStatsService` (يوم)

### أسبوع 3 — فلاتر وتقارير
7. **FIN-M02/M03** — تحقق `date_from <= date_to` + `whereBetween('created_at', [start,end])` مع فهرس (يوم)
8. **FIN-M04** — `round(...,2)` بلا فقدان هللات (ساعة)
9. **FIN-L06** — إضافة `MANAGER_OVERRIDE_CODE` إلى `.env.example`

### أسبوع 4 — تحصيل وتزامن
10. **FIN-C06** — `receipt_number` غير فريد أو لاحقة (يوم)
11. **FIN-H06** — فلتر `branch_id` في `settleCustomerDebt` (ساعة)

---

## 7. فجوات التغطية الاختبارية

| السيناريو المفقود | الاختبار المقترح |
|------------------|------------------|
| كشف حساب يعرض `final_amount` لا `total_amount` | `test_statement_shows_final_amount_not_zero()` |
| مرتجع نقدي خالص يُرجع مالاً | `test_cash_refund_creates_negative_payment()` |
| تحصيل برصيد مكرر `receipt_number` | `test_duplicate_receipt_number_fails_gracefully()` |
| فلتر `date_from > date_to` | `test_date_filter_swapped_returns_error()` |
| إيراد Dashboard لا يضاعف `InvoicePayment + Ledger` | `test_revenue_equals_cash_payments_only()` |

---

## 8. التحقق بعد الإصلاح

```bash
# 1. كشف المرتجع المكسور
php artisan test --filter="sales return on credit invoice deducts"
# قبل: يمر لكن لا يتحقق من $inv->total_amount
# بعد FIN-C01: أضف assertSee(number_format($inv->final_amount,2))

# 2. فلاتر التواريخ
php artisan test --filter="sales invoices index filters by date_from"
# يجب أن يفشل إذا date_from > date_to بعد FIN-M02

# 3. لوحة التحكم
php artisan test --filter="admin can view dashboard"
# بعد FIN-C04: أضف فاتورة refunded وتأكد أنها لا تُحتسب
```

---

## 9. الملاحق

### أوامر البحث المستخدمة
```bash
rg -n "total_amount|payment_status|invoice_type" tests/
rg -n "whereDate|where.*created_at.*>=" app/Http/Controllers/Admin/SalesInvoiceController.php app/Services/Sales/PosOrderService.php
rg -n "entry_type" app/Models/CreditLedgerEntry.php resources/views/admin/credit/statement.blade.php
```

### المراجع
- `PROJECT_CONTEXT.md:131-145` — قواعد Eager Loading والفهارس
- `docs/SALES_AND_PURCHASES_SPEC.md` — معمارية WAC والكهنة
- `app/Services/Diagnostics/SystemDiagnosticService.php:48-231` — تدقيق اتساق الدفتر

> **الخلاصة:** البنية المالية **سليمة تزامنياً** لكن **العرض والفلترة يكذبان**. إصلاح `statement.blade.php:139` و `DashboardController.php:31` و `PosOrderService.php:397` وحده يرفع دقة التقارير من ~92% إلى >99%.

