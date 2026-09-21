# 🚀 Al-Husseini Management System — AI Agent Technical Master Plan
### خطة العمل الفنية المتكاملة لترقية وتطوير نظام مركز الحسيني لبطاريات وزيوت وصيانة السيارات

---

## 📌 0. إرشادات ومحددات الذكاء الاصطناعي (AI Agent Execution Guidelines)

> [!IMPORTANT]
> **على أي AI Agent يشرع في تنفيذ هذه الخطة الالتزام الصارم بالقواعد التالية:**
> 1. **الاتساق أولاً (Consistency First)**: فحص الأنماط الموجودة في المستودع واتباع اصطلاحات Laravel 12 و PSR-12 بدون إدخال أساليب تجميع أو حزم متضاربة.
> 2. **الأمان المالي والبياني (Data & Financial Invariants)**: جميع عمليات الدفع والخصم وفواتير الـ POS وخصم البطاريات القديمة (الكهنة) يجب أن تُنفّذ داخل **Database Transactions (`DB::transaction`)** لمنع Race Conditions.
> 3. **منع استعلامات N+1**: استخدام التحميل المسبق المباشر `with()` و `withCount()` و `loadMissing()`.
> 4. **فصل منطق العمل (Separation of Concerns)**: بقاء الـ Controllers مقتصرة على توجيه الطلبات واستقبال الـ Form Requests، بينما تذهب كل الحسابات الرياضية إلى طبقة الخدمات (`App\Services`).
> 5. **كتابة الاختبارات التلقائية**: تغطية كل ميزة باختبارات وحدة وتكامل (Pest / PHPUnit) قبل إغلاق أي مرحلة.

---

## 🗺️ خريطة المراحل التقنية (Technical Phases Roadmap)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  المرحلة 1: طبقة هيكل قاعدة البيانات والعلاقات (Migrations & Schema)        │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 2: طبقة النماذج والعلاقات المتقدمة (Eloquent Models & Observers)   │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 3: طبقة التحقق وقواعد الأمان (Form Requests & Validation Rules)     │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 4: محرك الخدمات ومنطق الأعمال (Service Layer & Business Logic)      │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 5: طبقة التحكم ومسارات النظام (Controllers & API/Web Endpoints)     │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 6: ترقية الواجهات واستبدال LocalStorage بروابط حية (UI Migration)   │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 7: تكامل الأجهزة والعتاد (Biometrics, Barcode Scanners & ESC/POS)   │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 8: الأحداث اللحظية والبث المباشر (WebSockets, Reverb & Alerts)      │
├─────────────────────────────────────────────────────────────────────────────┤
│  المرحلة 9: حزمة الاختبارات الشاملة والنشر السحابي (Testing & Cloud Launch) │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🧱 المرحلة 1: طبقة هيكل قاعدة البيانات والعلاقات (Migrations & Schema)

### الهدف:
نقل كافة الهياكل الموجودة داخل `hr-store.js` و `sales-store.js` إلى جداول علائقية مهيأة بالكامل مع الفهارس (Indexes) والقيود الخارجية (Foreign Key Constraints).

### الخطوات التفصيلية للـ AI Agent:

#### 1.1 جداول شؤون العاملين والورشة (HR & Staff Schema):
1. **جدول الأقسام (`departments`)**:
   - `id`: unsignedBigInteger
   - `name`: string (e.g. "ورشة الصيانة والشحن", "المبيعات والمعرض", "خدمة الطوارئ والإنقاذ المتنقل")
   - `code`: string(20) - فريد (e.g. `SALES`, `WORKSHOP`, `ELECTRIC`, `RESCUE`, `STORE`, `ADMIN`)
   - `description`: text (nullable)
   - `is_active`: boolean (default true)
   - `timestamps`

2. **جدول الموظفين والفنيين (`employees`)**:
   - `id`: unsignedBigInteger
   - `employee_code`: string(20) - فريد مفهرس (`BAT-101`, `BAT-102`)
   - `department_id`: foreignId constrained to `departments` on delete restrict
   - `name`: string
   - `role`: string (e.g. "كبير بائعي بطاريات", "فني أول صيانة وشحن", "كهربائي سيارات دينامو")
   - `email`: string - فريد
   - `phone`: string(30)
   - `avatar`: string (nullable)
   - `start_time`: time (e.g. `08:30:00`, `09:00:00`)
   - `end_time`: time (e.g. `17:30:00`, `18:00:00`)
   - `base_salary`: decimal(10, 2)
   - `allowances`: decimal(10, 2) (default 0)
   - `status`: enum (`active`, `on_leave`, `suspended`, `terminated`) default `active`
   - `join_date`: date
   - `timestamps`, `softDeletes`

3. **جدول سجل الحضور والبصمة (`attendances`)**:
   - `id`: unsignedBigInteger
   - `employee_id`: foreignId constrained to `employees` on delete cascade
   - `date`: date (مفهرس مع `employee_id` كقيد فريد مركب: `unique(['employee_id', 'date'])`)
   - `punch_in`: time (nullable)
   - `punch_out`: time (nullable)
   - `status`: enum (`on_time`, `late`, `absent`, `on_leave`) default `absent`
   - `lateness_minutes`: unsignedSmallInteger (default 0)
   - `verified_via`: enum (`manual`, `biometric_fingerprint`, `rfid_card`, `face_id`) default `manual`
   - `notes`: text (nullable)
   - `timestamps`

4. **جدول الخصومات والجزاءات الإدارية (`deductions`)**:
   - `id`: unsignedBigInteger
   - `decision_no`: string(50) - فريد ومفهرس (`BAT-DEC-2026/001`)
   - `employee_id`: foreignId constrained to `employees`
   - `amount`: decimal(10, 2)
   - `reason`: string(255)
   - `date`: date
   - `manager_notes`: text (nullable)
   - `created_by`: foreignId constrained to `users`
   - `timestamps`

#### 1.2 جداول المبيعات والمخزن وفواتير الـ POS (Sales & POS Schema):
1. **جدول المنتجات والبطاريات والزيوت (`products`)**:
   - `id`: unsignedBigInteger
   - `barcode`: string(50) - فريد ومفهرس (`6221001010018`)
   - `code`: string(30) - فريد (`PROD-101`)
   - `category`: enum (`battery`, `oil`, `grease`, `service`, `accessory`)
   - `brand`: string (e.g. "كلورايد Chloride", "فارتا Varta", "موبيل Mobil", "إيه سي ديلكو ACDelco")
   - `name`: string
   - `ampere`: string(20) (nullable - e.g. "70 أمبير", "60 أمبير")
   - `battery_type`: string(50) (nullable - e.g. "جافة - كالسيوم", "AGM Start-Stop")
   - `price_new`: decimal(10, 2)
   - `price_with_old`: decimal(10, 2) (السعر شامل استبدال البطارية القديمة)
   - `scrap_value`: decimal(10, 2) (قيمة خصم الكهنة/الخردة)
   - `warranty_months`: unsignedTinyInteger (e.g. 12, 18, 24)
   - `stock`: integer (default 0)
   - `min_stock_alert`: integer (default 5)
   - `unit`: string(20) default "قطعة"
   - `status`: enum (`in_stock`, `low_stock`, `out_of_stock`) default `in_stock`
   - `timestamps`, `softDeletes`

2. **جدول سجل العملاء والمركبات (`customers`)**:
   - `id`: unsignedBigInteger
   - `code`: string(20) - فريد (`CUST-101`)
   - `name`: string
   - `phone`: string(30) - مفهرس
   - `car_model`: string(100) (e.g. "تويوتا كورولا 2021", "كيا سيراتو 2018")
   - `plate_number`: string(30) (e.g. "أ ب ج 1234")
   - `credit_limit`: decimal(10, 2) (حد السحب الآجل)
   - `credit_balance`: decimal(10, 2) (الرصيد المدين الحالي)
   - `status`: enum (`active`, `blocked`) default `active`
   - `timestamps`, `softDeletes`

3. **جدول فواتير المبيعات ونقاط البيع (`invoices`)**:
   - `id`: unsignedBigInteger
   - `invoice_number`: string(50) - فريد ومفهرس (`INV-2026-001`)
   - `customer_id`: foreignId constrained to `customers` on delete restrict
   - `cashier_id`: foreignId constrained to `users`
   - `technician_id`: foreignId (nullable) constrained to `employees` (الفني المنفذ للتركيب/الصيانة)
   - `type`: enum (`pos_sale`, `workshop_service`, `warranty_replacement`)
   - `subtotal`: decimal(10, 2)
   - `discount`: decimal(10, 2) default 0
   - `scrap_total`: decimal(10, 2) default 0 (إجمالي خصم البطاريات المسترجعة)
   - `grand_total`: decimal(10, 2)
   - `paid_amount`: decimal(10, 2)
   - `credit_amount`: decimal(10, 2) default 0 (المتبقي بالآجل)
   - `payment_method`: enum (`cash`, `credit_due`, `visa`, `vodafone_cash`, `instapay`, `split`)
   - `payment_status`: enum (`paid`, `partially_paid`, `credit_unpaid`)
   - `notes`: text (nullable)
   - `timestamps`

4. **جدول بنود الفاتورة والضمان (`invoice_items`)**:
   - `id`: unsignedBigInteger
   - `invoice_id`: foreignId constrained to `invoices` on delete cascade
   - `product_id`: foreignId constrained to `products`
   - `quantity`: unsignedInteger default 1
   - `unit_price`: decimal(10, 2)
   - `has_old_exchange`: boolean default false (هل تم تسليم بطارية قديمة)
   - `scrap_deduction`: decimal(10, 2) default 0
   - `total`: decimal(10, 2)
   - `warranty_serial`: string(100) (nullable - سيريال كارت الضمان المعتمد)
   - `warranty_expires_at`: date (nullable)
   - `timestamps`

5. **جدول معاملات وسداد الآجل (`credit_payments`)**:
   - `id`: unsignedBigInteger
   - `receipt_no`: string(50) - فريد ومفهرس (`REC-2026-001`)
   - `customer_id`: foreignId constrained to `customers`
   - `invoice_id`: foreignId (nullable) constrained to `invoices`
   - `amount`: decimal(10, 2)
   - `balance_before`: decimal(10, 2)
   - `balance_after`: decimal(10, 2)
   - `payment_method`: enum (`cash`, `instapay`, `vodafone_cash`, `bank_transfer`)
   - `collected_by`: foreignId constrained to `users`
   - `notes`: text (nullable)
   - `timestamps`

---

## 🏗️ المرحلة 2: طبقة النماذج والعلاقات المتقدمة (Eloquent Models & Observers)

### المهام التنفيذية:
1. **إنشاء النماذج (`app/Models/`)**:
   - `Department`, `Employee`, `Attendance`, `Deduction`
   - `Product`, `Customer`, `Invoice`, `InvoiceItem`, `CreditPayment`
2. **ضبط الخصائص الصارمة**:
   - استخدام `$fillable` حصراً (تجنب `$guarded = []` لحماية الثغرات).
   - استخدام `$casts`:
     ```php
     protected function casts(): array {
         return [
             'date' => 'date:Y-m-d',
             'start_time' => 'datetime:H:i',
             'end_time' => 'datetime:H:i',
             'base_salary' => 'decimal:2',
             'grand_total' => 'decimal:2',
             'status' => EmployeeStatus::class, // PHP 8.2 Backed Enums
         ];
     }
     ```
3. **تعريف العلاقات (Relationships)**:
   - `Employee` hasMany `Attendance`, `Deduction`.
   - `Department` hasMany `Employee`.
   - `Customer` hasMany `Invoice`, `CreditPayment`.
   - `Invoice` belongsTo `Customer`, belongsTo `User (cashier)`, hasMany `InvoiceItem`.
   - `InvoiceItem` belongsTo `Product`.
4. **تثبيت المراقبات (Model Observers)**:
   - `InvoiceObserver`: عند إنشاء فاتورة بنجاح:
     * خصم الكميات تلقائياً من مخزون المنتج `Product::decrement('stock', $item->quantity)`.
     * تحديث حالة المنتج إذا وصل للحد الأدنى `status = low_stock`.
     * في حال وجود سداد آجل: زيادة رصيد مديونية العميل `Customer::increment('credit_balance', $creditAmount)`.
   - `AttendanceObserver`: عند تسجيل بصمة دخول متأخرة بعد 15 دقيقة:
     * إطلاق حدث `EmployeeLateEvent` تلقائياً.

---

## 🛡️ المرحلة 3: طبقة التحقق وقواعد الأمان (Form Requests & Validation Rules)

### المهام التنفيذية:
1. **إنشاء Form Requests مخصصة لكل عملية (`app/Http/Requests/`)**:
   - `StoreEmployeeRequest` / `UpdateEmployeeRequest`
   - `RecordPunchRequest` (التحقق من صحة كود الموظف ونوع البصمة `in|out`)
   - `StoreDeductionRequest` (التحقق من أن قيمة الخصم > 0 ولا تتجاوز مرتب الموظف)
   - `CreatePosInvoiceRequest`:
     * التحقق من توفر كميات المنتجات بالمخزن قبل الخصم.
     * التحقق من عدم تجاوز مديونية العميل للـ `credit_limit` المسموح به.
   - `SettleCreditPaymentRequest`:
     * التحقق من أن المبلغ المسدد لا يتجاوز إجمالي مديونية العميل الحالية.

---

## ⚙️ المرحلة 4: محرك الخدمات ومنطق الأعمال (Service Layer & Business Logic)

### الهدف:
تجريد الحسابات المعقدة في خدمات نقية قابلة لإعادة الاستخدام والاختبار التلقائي داخل مجلد `app/Services/`.

### الخدمات المطلوبة بالتفصيل:

#### 1. `AttendanceService` (خدمة البصمة ومتابعة الورشة):
```php
class AttendanceService {
    public function recordPunch(string $employeeCode, string $type, ?Carbon $customTime = null): AttendanceResult;
    public function calculateLateness(Employee $employee, Carbon $punchTime): int; // بالدقائق
    public function getDailyAttendanceSummary(?Carbon $date = null): AttendanceSummaryDTO;
}
```
* **قاعدة العمل**: احتساب فترة السماح (15 دقيقة بعد `start_time`). إذا تجاوزها الموظف يتم احتساب الفارق من بداية موعد العمل الفعلي.

#### 2. `PayrollService` (خدمة مسير الرواتب والخصومات):
```php
class PayrollService {
    public function generateMonthlyPayroll(int $year, int $month): Collection;
    public function calculateNetSalary(Employee $employee, Carbon $period): float;
    public function generatePayslip(Employee $employee, Carbon $period): PayslipDTO;
}
```
* **المعادلة المطبقة**:
  $$\text{Net Salary} = (\text{base\_salary} + \text{allowances}) - \sum \text{Deductions for current month}$$

#### 3. `PosOrderService` (خدمة فواتير المبيعات وخصم الكهنة المسترجعة):
```php
class PosOrderService {
    public function processCheckout(PosCheckoutDTO $dto): Invoice;
    private function validateStockAvailability(array $items): void;
    private function applyOldBatteryScrapDiscount(Invoice $invoice, array $scrapItems): float;
}
```
* **ميزة فريدة لمركز البطاريات**: حساب قيمة البطارية المستبدلة (الكهنة) وخصمها من السعر الجديد تلقائياً مع تسجيل السيريال للضمان.

#### 4. `CreditLedgerService` (خدمة إدارة حسابات الآجل والمستحقات):
```php
class CreditLedgerService {
    public function recordPayment(Customer $customer, float $amount, string $method, ?string $notes = null): CreditPayment;
    public function getCustomerStatement(Customer $customer): Collection;
}
```

---

## 🔌 المرحلة 5: طبقة التحكم ومسارات النظام (Controllers & API/Web Endpoints)

### المهام التنفيذية:
1. **تنظيم المتحكمات (`app/Http/Controllers/Admin/`)**:
   - `EmployeeController` (Index, Store, Update, Destroy, Profile)
   - `AttendanceController` (Index, Punch, LiveClock, Export)
   - `PayrollController` (Index, Settle, Payslip, ExportCSV)
   - `ReportController` (Index, Daily, Monthly, CustomRange, Print)
   - `PosController` (Terminal, QuickScan, Checkout, Receipt)
   - `InvoiceController` (Index, Show, PrintWarranty, Cancel)
   - `CreditController` (Index, Statement, PayDue)
   - `CustomerController` & `ProductController`

2. **تسجيل المسارات في `routes/web.php`**:
   - تنظيم المسارات باستخدام المجموعات المسبوقة بـ `Route::prefix('admin')->name('admin.')->middleware(['auth'])`.

---

## 🖥️ المرحلة 6: ترقية الواجهات واستبدال LocalStorage بروابط حية (UI Migration)

### المهام التنفيذية:
1. استبدال `window.AlHusseiniHR` و `window.AlHusseiniSales` باستدعاءات AJAX/Axios سريعة تستهدف الـ Controllers مع الحفاظ على سرعة تفاعل الفرونت إند بنسبة 100%.
2. ربط نوافذ SweetAlert2 و Modals بالاستجابات البرمجية الحقيقية من الخادم (Server Responses).
3. الحفاظ على المكونات المميزة الحالية:
   - ساعة الورشة الرقمية المضيئة (`Live Digital Shop Clock`).
   - مستشعر البصمة التفاعلي بأنيميشن النبض الصوتي والضوئي.
   - قسائم الرواتب المجهزة للتفقيط والطباعة (`Payslip Print`).
   - رسوم ApexCharts الحية لمعدلات الانضباط وأوقات التأخير.

---

## 📟 المرحلة 7: تكامل الأجهزة والعتاد (Biometrics, Barcode & ESC/POS)

### الميزات المتقدمة لبيئة الورشة الحقيقية:
1. **ماكينات البصمة البيومترية (ZKTeco Protocol)**:
   - إنشاء مسار Webhook / Listener يستقبل نبضات الحضور الآلية من أجهزة البصمة عبر منفذ TCP/UDP أو بروتوكول ADMS السحابي وتغذية `AttendanceService::recordPunch`.
2. **قارئ الباركود (Barcode Handheld Scanners)**:
   - تفعيل مستمع Keydown سريع لشاشات POS للتعرف على قراءة ماسح الباركود (سلسلة أرقام تنتهي بـ `Enter` في أقل من 50 مللي ثانية) لإدراج البطارية في سلة البيع فوراً.
3. **طابعات الإيصالات الحرارية (80mm ESC/POS Thermal Printing)**:
   - توليد فاتورة مقاس 80mm متضمنة باركود الفاتورة، تفاصيل البطارية والسيارة، وبيانات الضمان المعتمد.

---

## ⚡ المرحلة 8: الأحداث اللحظية والبث المباشر (WebSockets & Live Alerts)

### المهام التنفيذية:
1. تشغيل خادم البث الفوري المدمج في لارافيل: **Laravel Reverb**.
2. إنشاء قنوات خاصة للمدير والمشرفين:
   - قناة `admin-alerts`:
     * حدث `EmployeeLateBroadcastEvent`: إطلاق جرس التنبيهات في شريط الترويسة فور تسجيل أي فني لتأخير.
     * حدث `LowStockBroadcastEvent`: تنبيه أمين المخزن بنقص مخزون بطارية معينة فور بيعها.
     * حدث `CreditLimitExceededEvent`: تحذير الكاشير عند محاولة عميل سحب آجل يتجاوز الحد المسموح.

---

## 🧪 المرحلة 9: حزمة الاختبارات الشاملة والنشر السحابي (Testing & Cloud Launch)

### 1. خطة الاختبارات التلقائية (Pest / PHPUnit):
- **اختبارات الحسابات المالية**:
  ```php
  it('calculates net salary correctly subtracting all deductions for the month');
  it('deducts old battery scrap value accurately during pos checkout');
  it('prevents customer from exceeding allowed credit limit');
  ```
- **اختبارات الانضباط والبصمة**:
  ```php
  it('allows 15 minutes grace period before recording lateness');
  it('calculates exact lateness minutes when punching after grace period');
  it('generates a sequential administrative decision number for deductions');
  ```

### 2. تدابير الإطلاق والأداء:
- تفعيل الكاش المتكامل للأداء الإنتاجي:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```
- إعداد ملفات بيئة الإنتاج `.env.production` مع ضبط اتصالات قواعد البيانات الآمنة.

---

## 📋 جدول التحقق التنفيذي الميداني (AI Agent Milestone Checklist)

| المرحلة | الوصف | الأولوية | حالة الإنجاز |
|---|---|---|---|
| **Phase 1** | إنشاء ملفات Migrations لكافة جداول الـ HR والـ Sales والـ POS | 🔴 حرجة | جاهز للتنفيذ |
| **Phase 2** | إنشاء الـ Models مع الـ Casts والـ Relationships والـ Observers | 🔴 حرجة | بانتظار البدء |
| **Phase 3** | إنشاء Form Requests والتحقق الصارم لكل مسار | 🟡 هامة | بانتظار البدء |
| **Phase 4** | برمجة طبقة الـ Services لحسابات الرواتب والبصمة وفواتير الـ POS | 🔴 حرجة | بانتظار البدء |
| **Phase 5** | بناء الـ Controllers وتوزيع الـ Routes | 🟡 هامة | بانتظار البدء |
| **Phase 6** | ترقية الواجهات الحالية من LocalStorage إلى Live Endpoints | 🟡 هامة | بانتظار البدء |
| **Phase 7** | دعم قارئ الباركود والطباعة الحرارية وماكينة البصمة | 🟢 تطويري | بانتظار البدء |
| **Phase 8** | تفعيل البث اللحظي عبر Laravel Reverb | 🟢 تطويري | بانتظار البدء |
| **Phase 9** | حزمة اختبارات Pest وتجهيز النشر النهائي | 🔴 حرجة | بانتظار البدء |
