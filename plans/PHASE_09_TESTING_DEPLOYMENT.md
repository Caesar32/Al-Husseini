# المرحلة التاسعة: الاختبارات ونشر الإنتاج (Testing Suite & Production Deployment)

> **طبيعة الملف:** وثيقة تقنية لمطوري الـ QA والـ DevOps تحدد اختبارات الوحدة والتكامل الآلية باستخدام إطار عمل Pest PHP، متبوعة بإعدادات النشر على خوادم الإنتاج (Nginx, Supervisor, Reverb Daemon).

---

## 1. جناح اختبارات Pest PHP الآلية (Automated Pest Test Suite)

### 1. اختبار احتساب التأخير وفترة السماح `tests/Feature/Hr/AttendanceLatenessTest.php`
```php
<?php

use App\Models\Employee;
use App\Models\Branch;
use App\Models\JobTitle;
use App\Models\Department;
use App\Models\SalaryStructure;
use App\Services\Hr\AttendanceService;
use Carbon\Carbon;

beforeEach(function () {
    $this->branch = Branch::create(['name' => 'الفرع الرئيسي', 'code' => 'MAIN']);
    $this->department = Department::create(['name' => 'الصيانة', 'code' => 'MAINT']);
    $this->jobTitle = JobTitle::create(['department_id' => $this->department->id, 'title' => 'فني بطاريات']);
    
    $this->employee = Employee::create([
        'branch_id' => $this->branch->id,
        'job_title_id' => $this->jobTitle->id,
        'employee_code' => 'EMP-001',
        'full_name' => 'محمود أحمد',
        'national_id' => '29501010101234',
        'phone' => '01012345678',
        'hire_date' => '2025-01-01',
        'shift_start_time' => '09:00:00',
        'shift_end_time' => '17:00:00',
        'grace_period_minutes' => 15,
        'zkteco_pin' => '9901',
        'status' => 'active',
    ]);

    SalaryStructure::create([
        'employee_id' => $this->employee->id,
        'basic_salary' => 6000,
        'effective_from' => '2025-01-01',
        'is_current' => true,
    ]);
});

test('يتم احتساب الحضور كمنتظم إذا وصل الموظف خلال فترة السماح الـ 15 دقيقة', function () {
    $service = app(AttendanceService::class);
    $punchTime = Carbon::parse('2026-03-01 09:12:00'); // تأخر 12 دقيقة (أقل من 15)

    $attendance = $service->recordPunch('9901', $punchTime, 0);

    expect($attendance->late_minutes)->toBe(0)
        ->and($attendance->status)->toBe('present');
});

test('يتم احتساب التأخير وتغيير الحالة إذا تجاوز فترة السماح', function () {
    $service = app(AttendanceService::class);
    $punchTime = Carbon::parse('2026-03-01 09:35:00'); // تأخر 35 دقيقة

    $attendance = $service->recordPunch('9901', $punchTime, 0);

    expect($attendance->late_minutes)->toBe(35)
        ->and($attendance->status)->toBe('late');
        
    // التأكد من تسجيل مسودة الجزاء المالي تلقائياً عبر الـ AttendanceObserver
    $this->assertDatabaseHas('employee_deductions', [
        'employee_id' => $this->employee->id,
        'attendance_id' => $attendance->id,
        'status' => 'pending',
    ]);
});
```

---

### 2. اختبار الحركات المالية لنقطة البيع `tests/Feature/Pos/PosCheckoutFinancialTest.php`
```php
<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\User;
use App\Services\Pos\PosOrderService;

beforeEach(function () {
    $this->branch = Branch::create(['name' => 'فرع طنطا', 'code' => 'TNT']);
    $this->category = Category::create(['name' => 'بطاريات جافة', 'slug' => 'dry-batteries']);
    $this->cashier = User::factory()->create();

    $this->battery = Product::create([
        'category_id' => $this->category->id,
        'sku' => 'BAT-CHL-70',
        'barcode' => '62210000001',
        'name' => 'بطارية كلورايد 70 أمبير',
        'brand' => 'Chloride',
        'cost_price' => 2000,
        'retail_price' => 2500,
        'current_stock' => 10,
        'is_battery' => true,
        'warranty_months' => 12,
    ]);

    $this->customer = Customer::create([
        'name' => 'الحاج إبراهيم',
        'phone' => '01222222222',
        'credit_limit' => 5000,
        'current_credit_balance' => 0,
    ]);
});

test('يتم خصم المخزون وإنشاء الضمان واحتساب خصم الكهنة وترحيل الآجل بدقة', function () {
    $service = app(PosOrderService::class);

    $orderData = [
        'branch_id' => $this->branch->id,
        'customer_id' => $this->customer->id,
        'payment_method' => 'split',
        'discount_amount' => 100,
        'scrap_deduction_amount' => 400, // استبدال بطارية قديمة بـ 400 ج.م
        'tax_amount' => 0,
        'paid_amount' => 1000,           // سداد 1000 ج.م كاش، والمتبقي 1000 ج.م آجل
        'items' => [
            [
                'product_id' => $this->battery->id,
                'quantity' => 1,
                'unit_price' => 2500,
                'battery_serial_number' => 'SN-2026-CHL-9988',
                'warranty_duration_months' => 12,
            ]
        ]
    ];

    $invoice = $service->createInvoice($orderData, $this->cashier->id);

    // الحسابات: 2500 - 100 - 400 = 2000 صافي. مدفوع 1000 -> متبقي 1000
    expect($invoice->final_amount)->toBe('2000.00')
        ->and($invoice->remaining_amount)->toBe('1000.00')
        ->and($invoice->status)->toBe('partially_paid');

    // 1. التحقق من خصم المخزون
    expect($this->battery->fresh()->current_stock)->toBe(9);

    // 2. التحقق من إصدار الضمان
    $this->assertDatabaseHas('warranties', [
        'serial_number' => 'SN-2026-CHL-9988',
        'customer_id' => $this->customer->id,
        'status' => 'active',
    ]);

    // 3. التحقق من تحديث رصيد الآجل للعميل وتسجيل دفتر الأستاذ
    expect($this->customer->fresh()->current_credit_balance)->toBe('1000.00');
    $this->assertDatabaseHas('credit_ledger_entries', [
        'customer_id' => $this->customer->id,
        'amount' => 1000,
        'entry_type' => 'invoice_debt',
    ]);

    // 4. التحقق من تسجيل بطارية الكهنة في مخزن الخردة
    $this->assertDatabaseHas('scrap_batteries_inventory', [
        'invoice_id' => $invoice->id,
        'scrap_value' => 400,
        'status' => 'in_stock',
    ]);
});
```

---

## 2. إعدادات النشر على خوادم الإنتاج (Production Checklist)

### 1. أوامر التخزين المؤقت والحماية (Production Optimizations)
```bash
# تثبيت الاعتماديات بدون مكتبات التطوير
composer install --no-dev --optimize-autoloader

# كاش الإعدادات والمسارات والقوالب
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# تشغيل التهجيرات بأمان
php artisan migrate --force
```

---

### 2. إعداد مشغل المهام الدائم (Supervisor Configuration)

إنشاء ملف التكوين `/etc/supervisor/conf.d/alhusseini-workers.conf`:

```ini
[program:alhusseini-reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/alhusseini/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/alhusseini/storage/logs/reverb.log

[program:alhusseini-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/alhusseini/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/alhusseini/storage/logs/queue.log
```

---

### 3. جدولة المهام الدورية (Cron Scheduling)

إضافة السطر التالي في `crontab -e`:
```bash
* * * * * cd /var/www/alhusseini && php artisan schedule:run >> /dev/null 2>&1
```

تتضمن الجدولة داخل `routes/console.php`:
- إغلاق سجلات الحضور والانصراف تلقائياً عند منتصف الليل وتسجيل من لم يسجل انصراف.
- إنشاء نسخة احتياطية يومية من قاعدة البيانات `alhusseini_backup`.
