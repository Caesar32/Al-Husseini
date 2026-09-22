# 🏢 وثيقة السياق التقني الشامل لنظام إدارة مجموعة الحسيني
### Technical Master Context & Architecture Specification for AI Agents & Developers

> **الغرض من هذا الملف:**
> هذا الملف صُمم ليكون المرجع التقني المباشر والشامل لأي **AI Agent** أو **مهندس برمجيات**. بقراءته، يتلقى الـ Agent الفهم المعماري والبرمجي الكامل للمشروع دون الحاجة لقراءة وبحث مئات الملفات من الصفر.

> [!NOTE]
> **آخر تحديث:** 2026-09-23 | تم إنجاز وتدقيق منظومة الفهرسة والبحث الشامل (Global Spotlight Search)، وتحديث محركات البحث في كافة شاشات الموارد البشرية، وحل مشاكل صفحة التقارير، وتأكيد اجتياز 91 اختباراً مؤتمتاً بنسبة 100%.

---

## 📌 1. نظرة عامة على المشروع (Executive Overview)
* **اسم النظام:** نظام مجموعة الحسيني لإدارة الفروع والمبيعات والموارد البشرية (Al-Husseini Management System).
* **طبيعة النظام:** نظام تخطيط موارد مؤسسي ونقاط بيع متكامل (**ERP & POS**) مخصص لخدمات وتجارة بطاريات السيارات، الزيوت، الفلاتر، الورش الفنية، وخدمات الإنقاذ السريع، مع دعم إدارة الفروع المتعددة (**Multi-Branch**).
* **الفروع والتشغيل الحالي (Current Operational Branch):**
  * النظام مصمم بمعمارية تدعم تعدد الفروع (**Multi-Branch Ready**).
  * **الفرع التشغيلي الوحيد للمجموعة حالياً:** `فرع دمياط الجديدة` (العنوان: **شارع المحجوب، دمياط الجديدة، محافظة دمياط** | كود: `MAIN`).
  * جميع حسابات المستخدمين والموظفين والمخزون وفواتير المبيعات مرتبطة بهذا الفرع.
* **اللغة والبيئة:**
  * **Framework:** Laravel 12.x (أحدث معايير لارافيل).
  * **PHP:** ^8.2 (دعم Backed Enums, Types الصارمة, Nullsafe Operators).
  * **Database:** SQLite للتطوير السريع، مع دعم كامل للترحيل إلى MySQL / PostgreSQL في الإنتاج.
  * **Frontend:** Laravel Blade + Bootstrap 5 + Remix Icons + Vite.
  * **Testing:** Pest 3.x + PHPUnit.
  * **Authorization:** Spatie Laravel-Permission v6.25.

---

## 🏛️ 2. المعمارية البرمجية وطبقات النظام (Architecture Blueprint)

المشروع يتبع معمارية **Service-Layer Driven** مع تطبيق مبدأ عكس التبعيات (**Dependency Inversion Principle - DIP**):

```
[ HTTP Requests / Routes ]
           │
           ▼
[ Controllers (Thin) ] ── (Requests & Validation)
           │
           ▼
[ Contracts / Interfaces ] ─── (Dependency Inversion)
           │
           ▼
[ Service Layer (Pure Business Logic) ] ── (DB Transactions)
           │
           ▼
[ Eloquent Models & Observers ] ─── (Event-Driven Automations)
           │
           ▼
[ Database Schema & Notifications ]
```

### تفصيل الطبقات ومساراتها:
1. **طبقة الواجهات المعيارية (`app/Contracts/Hr/*`):**
   * عقود صارمة لواجهات الأعمال:
     * `EmployeeServiceInterface`
     * `AttendanceServiceInterface`
     * `PayrollServiceInterface`
     * `DeductionServiceInterface`
     * `LeaveServiceInterface`
     * `NotificationServiceInterface`
2. **طبقة الخدمات (`app/Services/Hr/*`):**
   * عزل كامل لمنطق الحسابات، توليد الرواتب، حساب دقائق التأخير، والخصومات داخل كلاسات نظيفة وقابلة للاختبار.
3. **طبقة مزودي الخدمة (`app/Providers/`):**
   * `HrServiceProvider`: يقوم بعمل Container Bindings التلقائية بين كل Contract والـ Service المقابلة له.
   * `AppServiceProvider`:
     * **حماية N+1 الصارمة:** `Model::preventLazyLoading(!app()->isProduction())` — يرمي Exception فوري إذا تم الوصول لأي علاقة بدون Eager Loading مسبق في بيئة التطوير.
     * يمنح دور `super-admin` تخطياً كاملاً وتلقائياً لجميع الصلاحيات عبر: `Gate::before(fn($user) => $user->hasRole('super-admin') ? true : null);`
     * تسجيل الـ Eloquent Observers.
4. **طبقة مراقبة الأحداث والتنفيذ التلقائي (`app/Observers/`):**
   * `InvoiceObserver`: يتولى أتمتة ما بعد إنشاء الفواتير.
   * `AttendanceObserver`: يتولى احتساب التأخير وتطبيق الجزاءات تلقائياً.
   * `PurchaseInvoiceObserver`: يتولى تحديث المخزون ومطابقة الموردين.

---

## 🗄️ 3. هيكل قاعدة البيانات والكيانات الرئيسية (Database & Schema)

### أ. إدارة الفروع والمستخدمين (Core & Multi-Branch)
* `branches`: الفروع، الهاتف، المدينة، حالة التفعيل، الفرع الرئيسي.
* `users`: الحسابات، الأدوار، الفرع التابع له، الصورة الرمزية، الهاتف.
* `roles` & `permissions`: جداول Spatie القياسية للتحكم بالصلاحيات.

### ب. شؤون الموظفين والموارد البشرية (HR & Operations)
* `departments`: الأقسام (ورشة الصيانة، المبيعات والمعرض، الطوارئ، الإدارة).
* `job_titles`: المسميات الوظيفية وربطها بالأقسام.
* `employees`: الموظفون، كود الموظف، مواعيد الشفت (`shift_start_time`, `shift_end_time`)، فترة السماح (`grace_period_minutes`)، الفرع، والحالة.
* `salary_structures`: الراتب الأساسي، بدل السكن، الانتقال، والبدلات الأخرى.
* `attendances`: تسجيل الحضور، البصمة، دقائق التأخير (`late_minutes`)، وساعات العمل الإضافي.
* `employee_deductions`: الجزاءات والخصومات المعتمدة والمعلقة.
* `employee_leaves`: الإجازات (اعتيادية، مرضية، طارئة).
* `payrolls` & `payroll_items`: مسيرات الرواتب الشهرية على مستوى الفرع، تفاصيل كل موظف، الإجمالي، الصافي، وحالة المسير (`draft`, `approved`, `disbursed`).

### ج. المبيعات ونقاط البيع وخدمات الورشة (Sales & POS)
* `customers`: العملاء، بيانات الاتصال، الرصيد الآجل (`current_credit_balance`)، وحد الائتمان (`credit_limit`).
* `customer_vehicles`: سيارات العملاء (الموديل، الشاسيه، رقم اللوحة) لربط الضمان بها.
* `categories` & `products`: البطاريات والزيوت، الأكواد، الباركود، الأمبير، السعر الجديد، وسعر الاستبدال مع خصم الكهنة القديمة.
* `invoices` & `invoice_items`: الفواتير، الكاشير، الفني، الإجمالي، الخصم، قيمة الكهنة (`scrap_deduction_amount`)، المدفوع، والمتبقي بالآجل.
* `warranties` & `warranty_claims`: شهادات وبطاقات الضمان للبطاريات بالسيريال وتاريخ البداية والنهاية ومطالبات الاستبدال.
* `credit_ledger_entries`: دفتر أستاذ حسابات العملاء الآجلة (سداد، مديونية فاتورة، رصيد قبل وبعد).
* `scrap_batteries_inventory`: مخزن بطاريات الكهنة المستبدلة للبيع أو إعادة التدوير.
* `technician_commissions`: استحقاقات وعمولات الفنيين عن عمليات التركيب والصيانة.

### د. المشتريات والموردين (Purchases & Suppliers)
* `suppliers`: الموردون، السجل، وحسابات الديون.
* `purchase_invoices` & `purchase_invoice_items`: فواتير التوريدات والمخزون.
* `supplier_ledger_entries`: حركات قيود الموردين وسندات الصرف.

---

## ⚙️ 4. قواعد العمل الصارمة والأتمتة (Business Invariants & Rules)

### 1. دورة حياة الفاتورة ونقطة البيع (`InvoiceObserver`):
عند إنشاء أي فاتورة في النظام، تُنفذ المعاملات التالية داخل `DB::transaction`:
1. **خصم المخزون:** استدعاء `$item->product->decrement('current_stock', $item->quantity)`.
2. **إصدار الضمان الفوري:** إذا كانت السلعة بطارية ولها سيريال، يُنشأ سجل `Warranty` نشط صالح للمدة المحددة (مثلاً 12 أو 18 شهراً) مربوطاً بالسيارة والعميل.
3. **حسابات الآجل:** إذا كان هناك متبقٍ (`remaining_amount > 0`)، يتم رفع مديونية العميل وتوليد قيد في `CreditLedgerEntry` يسجل رصيد ما قبل وما بعد.
4. **مخزن الكهنة:** إذا وجد استبدال بطارية قديمة (`scrap_deduction_amount > 0`)، تُدرج البطارية القديمة في `ScrapBatteriesInventory`.
5. **عمولة الفني:** إذا أسندت الفاتورة لفني، يُسجل له استحقاق عمولة تركيب (`TechnicianCommission`).

### 2. محرك البصمة والجزاءات التلقائية (`AttendanceObserver`):
1. عند الحضور: مقارنة وقت البصمة بـ `shift_start_time` للموظف.
2. إذا كان الفارق أكبر من `grace_period_minutes`، يتم احتساب `late_minutes`.
3. **الجزاء الآلي:** إذا تجاوز التأخير 30 دقيقة، يُنشأ تلقائياً سجل `EmployeeDeduction` بحالة `pending` يخصم ربع يوم عمل:
   $$\text{Deduction} = \frac{\text{Basic Salary}}{30} \times 0.25$$

### 3. محرك الرواتب (`PayrollService`):
1. **المسير يمر بـ 3 مراحل:**
   * `draft`: مسودة يتم احتسابها آلياً وتجميع أيام الغياب والجزاءات المعتمدة والعمولات والإضافي.
   * `approved`: اعتماد من قبل المشرف العام (`approved_by`).
   * `disbursed`: الصرف الفعلي وإغلاق المسير المالي نهائياً لمنع أي تعديل لاحق.
2. **المعادلة المطبقة:**
   $$\text{الصافي} = (\text{الأساسي} + \text{البدلات} + \text{عمولات الفنيين} + \text{العمل الإضافي}) - (\text{تكلفة الغياب} + \text{الجزاءات المعتمدة})$$
3. إشعار تلقائي للمشرفين عبر `PayrollGeneratedNotification`.

---

## 🚦 5. أنماط الأداء المعتمدة — خريطة Eager Loading (Performance Patterns)

> [!IMPORTANT]
> **الحماية الصارمة مفعّلة:** `Model::preventLazyLoading` مُشغَّل في `AppServiceProvider`. أي وصول لعلاقة بدون تحميل مسبق سيرمي Exception في بيئة التطوير. **يجب دائماً استخدام الأنماط التالية.**

### خريطة الـ Eager Loading المعتمدة لكل Service:

| الـ Service | الدالة | العلاقات المطلوبة |
|---|---|---|
| `EmployeeService` | `getPaginatedEmployees()` | `['branch', 'jobTitle.department', 'currentSalary']` |
| `EmployeeService` | `getEmployeeDetails()` | `['branch', 'jobTitle.department', 'currentSalary', 'attendances', 'deductions', 'leaves', 'commissions']` |
| `EmployeeService` | `getFormData()` | `departments` مع `jobTitles` |
| `AttendanceService` | `getDailyAttendance()` | `['employee.branch', 'employee.jobTitle', 'deductions']` |
| `AttendanceService` | `getFormData()` | `['branch', 'jobTitle']` ✅ مُصلح |
| `PayrollService` | `getPayrollIndexData()` | `['branch', 'approvedBy', 'items.employee.jobTitle']` |
| `PayrollService` | `generateMonthlyPayroll()` | Bulk `whereIn` + `groupBy` قبل الـ loop ✅ مُصلح |
| `LeaveService` | `getPaginatedLeaves()` | `['employee.branch', 'actionedByUser']` |
| `DeductionService` | `getPaginatedDeductions()` | `['employee', 'deductionRule', 'approvedByUser']` |
| `ProfileController` | `index()` | `['roles.permissions', 'branch']` |

### نمط توليد الرواتب الجماعي (Payroll Bulk-Query Pattern):

بدلاً من **3N استعلام داخل الـ loop**، يتم الجلب **مرة واحدة قبل الـ loop** ثم الوصول من الذاكرة:

```php
// ✅ النمط الصحيح المعتمد في PayrollService::generateMonthlyPayroll()
$employeeIds = $employees->pluck('id');

$allAttendances = Attendance::whereIn('employee_id', $employeeIds)
    ->whereBetween('work_date', [...])->get()->groupBy('employee_id');

$allCommissions = TechnicianCommission::whereIn('employee_id', $employeeIds)
    ->where('status', 'approved')->get()->groupBy('employee_id');

$allDeductions = EmployeeDeduction::whereIn('employee_id', $employeeIds)
    ->where('status', 'approved')->get()->groupBy('employee_id');

// داخل الـ loop — وصول من الذاكرة بدون أي استعلام إضافي:
$attendances = $allAttendances->get($employee->id, collect());
```

---

## 🛡️ 6. خريطة الصلاحيات والأمان (Permissions Matrix)

* **Super Admin:** يمتلك دور `super-admin` وصولاً شاملاً لكافة المسارات بدون استثناء.
* **صلاحيات الـ HR:**
  * `employees.view`, `employees.create`, `employees.edit`, `employees.delete`
  * `attendance.view`, `attendance.manual_punch`
  * `leaves.manage`, `deductions.manage`
  * `payroll.generate`, `payroll.approve`, `payroll.disburse`
  * `reports.hr`, `notifications.view`
* **صلاحيات المبيعات والعمليات:**
  * `pos.access`, `invoices.view`, `credit.view`, `customers.view`, `products.view`
* **صلاحيات الإدارة:**
  * `dashboard.view`, `settings.manage`

---

## 🚀 7. دليل الأوامر والاختبارات السريع (Cheatsheet for Agents)

### تشغيل الاختبارات الآلية (Pest):
```bash
php artisan test
# أو فحص اختبارات الموارد البشرية حصراً:
php artisan test --filter=HrSubsystemTest
```

### الأوامر التأسيسية:
```bash
# تشغيل الـ Migrations والـ Seeders
php artisan migrate --seed

# مسح وإعادة بناء الـ Cache
php artisan config:clear
php artisan route:clear
php artisan view:clear

# تشغيل خادم التطوير مع الـ Vite والـ Queue
composer run dev
```

---

## 📝 8. إرشادات التطوير والتعديل للـ AI Agent

> [!CAUTION]
> **قواعد لا يجوز كسرها مطلقاً عند كتابة أي كود جديد في هذا المشروع:**
>
> 1. **العمليات المالية الحساسة:** أي كود يمس الفواتير، المخزون، سداد الديون، أو الرواتب **يجب أن يوضع داخل `DB::transaction(function () { ... })`**.
>
> 2. **منع N+1 — قاعدة صارمة:**
>    * `Model::preventLazyLoading` **مفعّل** — أي Lazy Load سيرمي Exception فوراً في التطوير.
>    * عند كتابة Loop على Collection تحتوي علاقات، استخدم `with()` أو نمط `whereIn()` + `groupBy()` قبل الـ loop.
>    * لا تكتب: `foreach ($items as $item) { $item->relation->... }` دون Eager Load مسبق.
>
> 3. **منطق الأعمال:** لا تضع معادلات أو منطق أعمال ثقيل داخل الـ Controllers؛ استخدم طبقة `app/Services` مع تعريف Interface في `app/Contracts`.
>
> 4. **أدوار Spatie:** لا تتخطى صلاحيات المستخدم العادي بدون التحقق عبر الـ Middleware أو `$user->can()`.
>
> 5. **الحسابات المالية:** استخدم `round(..., 2)` و `max(0, ...)` دائماً لمنع القيم السالبة أو الكسور الخاطئة.
>
> 6. **الأمان ومسيرات الرواتب:** يُمنع اعتماد أي مسير رواتب دون وجود معرف مستخدم معتمد صريح أو مسجل دخول (`Auth::id()`)، ويحظر استخدام Fallbacks عشوائية.
>
> 7. **شاشات التحقق الحساسة:** شاشات تسجيل الدخول وفك القفل (`LockScreenController`) تخضع لـ Rate Limiter صارم (5 محاولات يتبعها حظر مؤقت).
>
> 8. **Observers والأداء:** تأكد من عمل `load()` للعلاقات داخل دوال الـ Observers (مثل `InvoiceObserver` و `PurchaseInvoiceObserver`) قبل تكرار العناصر لتفادي استعلامات N+1 الخفية.

---

## 🛡️ 9. التحسينات الأمنية وهياكل الأداء المطبقة حديثاً (Security & Performance Fortifications)

1. **اعتماد الرواتب الآمن (`PayrollService::approvePayroll`):**
   * إزالة أي Fallback عشوائي (`User::first()`).
   * التحقق الإجباري من وجود المسؤول المعتمِد أو رمي `Exception` صريحة تمنع تمرير العملية في الخفاء.

2. **حماية شاشة القفل (`LockScreenController`):**
   * دمج `RateLimiter` بمفتاح فريد `lock-screen:{userId}:{ip}` لمنع هجمات التخمين Brute Force (حد أقصى 5 محاولات مع حظر لمدة 5 دقائق).

3. **التحقق المسبق للخصومات (`StoreDeductionRequest`):**
   * إضافة التحقق من أن الموظف المستهدف في حالة نشطة `active` عبر `Rule::exists('employees', 'id')->where('status', 'active')` لمنع ترحيل خصومات لموظفين متوقفين أو مفصولين.
   * ضبط صلاحية الطلب بـ `$this->user()->can('deductions.manage')`.

4. **فهارس قاعدة البيانات المركبة (`payrolls_branch_year_month_unique`):**
   * إضافة Unique Composite Index على أعمدة `(branch_id, year, month)` لتسريع استعلامات احتساب المسير الشهري وضمان سلامة البيانات ومنع التكرار على مستوى قاعدة البيانات.

5. **القضاء على N+1 في الـ Observers:**
   * تجهيز `InvoiceObserver` بـ `$invoice->load(['items.product', 'customer'])`.
   * تجهيز `PurchaseInvoiceObserver` بـ `$invoice->load(['items.product', 'supplier'])`.
   * إزالة الترتيب `orderByDesc` من داخل تعريف علاقة `Customer::creditLedgers` لتفادي التعارض مع Eager Loading.

6. **محرك الحضور والانصراف الصارم (Strict Attendance & Edge Cases Rules):**
   * **فترة التبريد (Anti-Passback / Debounce 5 Minutes):** تجاهل أي بصمة مكررة سريعة تحدث خلال أقل من 5 دقائق والحفاظ على البصمة الأولى وتفادي التكرار العرضي بحساس البصمة.
   * **منع الحضور المكرر (Duplicate Check-in):** رفض أي حركة حضور بعد مرور أكثر من 5 دقائق برمي استثناء صريح: `الموظف مسجل حضور بالفعل اليوم في تمام الساعة (XX:XX)`.
   * **اشتراط الحضور قبل الانصراف:** منع تسجيل انصراف لموظف لم يسجل حضوره اليوم.
   * **سلامة التسلسل الزمني:** منع تسجيل انصراف يسبق أو يساوي وقت الحضور المسجل للموظف.
   * **بصمة يوم الإجازة المعتمدة:** لا يتم إلغاء الإجازة المعتمدة للموظف، بل يتم تسجيل حضوره كوسم `holiday` مع احتساب ساعات تواجده بالكامل كعمل إضافي (Overtime).
   * **صلاحيات المشرف لتسجيل الغياب (`markAbsent`):** تمكين الإدارة والمشرف فقط من تسجيل الموظف كغائب وإلغاء بصمات اليوم مع تدوين السبب.

---

## 🔍 10. منظومة الفهارس والبحث الشامل (Database Indexing & Spotlight Search)

تمت ترقية بنية البحث وفهارس الجداول بالكامل لتوفير تجربة استعلام فورية واستجابة فائقة السرعة:

### أ. فهارس الجداول المضافة (`2026_09_23_000001_add_comprehensive_search_indexes_to_all_tables.php`):
* `employees`: فهارس على `full_name`, `[branch_id, status]`, `[branch_id, full_name]`.
* `customers`: فهارس على `name`, `national_id`, `[is_active, name]`.
* `customer_vehicles`: فهارس على `chassis_number`, `[car_brand, car_model]`.
* `products`: فهارس على `name`, `brand`, `[category_id, is_active]`, `[is_battery, is_active, brand]`.
* `invoices`: فهارس مركبة على `[customer_id, created_at]`, `[technician_id, created_at]`.
* `invoice_items`: فهارس على `[product_id, created_at]`.
* `warranties`: فهارس على `[customer_id, status]`, `[status, end_date]`.
* `suppliers`: فهارس على `name`, `tax_number`, `commercial_register`.
* `employee_leaves`: فهارس على `[start_date, end_date, status]`.

### ب. محرك معالجة النصوص العربية الثنائي (Bidirectional Arabic Normalization):
* **العقد والخدمة:** `App\Contracts\SearchServiceInterface` -> `App\Services\SearchService`.
* **المتحكم:** `App\Http\Controllers\Admin\SearchController` عبر المسار `GET /admin/global-search`.
* **القواعد المطبقة:**
  - مطابقة الهمزات التلقائية (`أ / إ / آ / ا`).
  - مطابقة التاء المربوطة والهاء (`ة / ه`).
  - مطابقة الياء والألف المقصورة (`ي / ى`).
  - إزالة التشكيل والتنوين بالكامل.
  - تنظيف أرقام الهواتف وأرقام لوحات السيارات من الرموز والمسافات.

### ج. واجهة البحث العالمي التفاعلية (Spotlight UI):
* وصول سريع من أي مكان في النظام باختصار لوحة المفاتيح `Ctrl + K` أو `Cmd + K`.
* دعم التنقل الكامل بالأسهم `↑` و `↓` والاختيار بـ `Enter` والإغلاق بـ `Esc`.
* استجابة Debounce بمقدار 250ms لمنع إرهاق السيرفر.
* نتائج مقسمة قطاعياً (موظفون، عملاء، سيارات، منتجات وبطاريات، فواتير، وضمانات) مع شارات ملونة للحالة.

---

## 👥 11. تحديث محركات البحث والتصفية في شاشات الموارد البشرية (HR Blades Search Overhaul)

تم تدقيق وتطوير جميع واجهات (Blade Views) الموارد البشرية لتوفير بحث وتصفية متكاملة وسلسة:

1. **شاشة الموظفين (`admin/hr/employees`):**
   - معالجة ارتفاع حاوية الجدول (`min-height: 380px`) لمنع اقتطاع القوائم المنسدلة للعمليات (`...`).
   - قراءة معلمات الرابط `?search=...` و `?branch_id=...` تلقائياً وتفعيل البحث والفتح التلقائي للملف الشخصي عبر `open_profile`.
   - إصلاح ربط المسمى الوظيفي عبر Accessor `title_name` الآمن في نموذج `JobTitle`.
   - بحث شامل في الباك إند عبر `EmployeeService` يشمل الاسم، الكود، الهاتف، الرقم القومي، المسمى، والقسم.

2. **شاشة مسير الرواتب (`admin/hr/payroll`):**
   - إضافة سمات البحث لصفوف كشف الاستحقاقات (`data-name`, `data-code`, `data-role`, `data-department`, `data-phone`).
   - دعم البحث بكود الموظف مباشرة (مثل `EMP-0101` أو `0101`).
   - محرك تطابق عربي متكامل في الفرونت إند وتصفية متزامنة لجدول الاستحقاقات وسجل الخصومات وجدول المسيرات.
   - إضافة زر مسح البحث السريع (`btnClearPayrollSearch`) وقراءة معلمات الرابط `?search=...`.

3. **شاشة الحضور والانصراف (`admin/hr/attendance`):**
   - ربط حقل البحث بالرابط `request('search')` والتصفية الفورية بالاسم أو الكود أو المسمى أو الهاتف.
   - دعم التبويبات السريعة (`present`, `late`, `absent`) من خلال الرابط.
   - تطبيق دالة `normalizeArabic` في واجهة المستخدم لمنع أي تفاوت هجائي.

---

## 📊 12. محرك تقارير الموارد البشرية المتكامل والطباعة والتصدير (HR Reports Engine)

مراجعة وتأمين كافة عمليات صفحة التقارير (`admin/hr/reports`):

1. **إصلاح المتجر الأساسي:** تضمين `hr-store.js` داخل `vendor-scripts.blade.php` لربط كائن `window.AlHusseiniHR` وحل مشكلة الشاشات الفارغة والـ KPIs الصفرية.
2. **محرك الفترات الزمنية الآمن:**
   - **التقرير اليومي:** تفكيك التاريخ بالأرقام `[year, month, day]` لتفادي انزياح التوقيت العالمي (UTC drift) مع توقيت القاهرة.
   - **التقرير الشهري:** قراءة الشهور والسنوات بدقة مع تعريب أسماء الأشهر.
   - **الفترة المخصصة:** معالجة تلقائية لحالة إدخال تاريخ بداية لاحق لتاريخ النهاية.
3. **فلترة متعددة وبحث عربي:** دعم الفلترة المتزامنة بالاسم والكود والقسم عبر الجداول الثلاثة (سجل الحضور، ملخص العاملين، وسجل الخصومات).
4. **الرسوم البيانية (ApexCharts):** عمل مخطط الكعكة ومخطط المسار الزمني بدقة 100%.
5. **طباعة كارت الموظف المستقلة:** إضافة تنسيقات `@media print` خاصة بـ `.modal-open` لعزل نافذة بطاقة الموظف وطباعتها كوثيقة رسمية دون أي تشويش من الصفحة الخلفية.
6. **تصدير Excel متوافق عربياً:** تصدير CSV بترميز `UTF-8 BOM` (`\uFEFF`) لضمان فتح الجداول باللغة العربية في Microsoft Excel مباشرة.

---


