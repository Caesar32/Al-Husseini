# المرحلة الأولى: مخطط قاعدة البيانات المطبّع (Database Migrations & Schema Architecture)

> **طبيعة الملف:** وثيقة تقنية تفصيلية (Technical Specification) مخصصة لمطور الـ Backend ومهندس الـ AI لتنفيذ بنية الجداول وقواعد البيانات لمركز الحسيني لبطاريات السيارات وإدارة الورشة وشؤون الموظفين.
> **الهدف:** توفير كود التهجير الكامل (Laravel Migrations) بصيغة PHP الجاهزة للتنفيذ، محققة الدرجة الثالثة من التطبيع (3NF/BCNF) مع الفهارس والمفاتيح الأجنبية والقيود الحسابية.

---

## 1. شجرة ملفات التهجير (Migration Execution Order)

يجب إنشاء وتشغيل ملفات التهجير وفق الترتيب الصارم التالي لتفادي أخطاء المفاتيح الأجنبية (Foreign Key Constraint Violations):

```
database/migrations/
├── 2026_01_01_000001_create_branches_table.php
├── 2026_01_01_000002_create_departments_and_job_titles_tables.php
├── 2026_01_01_000003_create_employees_and_salaries_tables.php
├── 2026_01_01_000004_create_attendances_and_deductions_tables.php
├── 2026_01_01_000005_create_payrolls_and_payroll_items_tables.php
├── 2026_01_01_000006_create_customers_and_customer_vehicles_tables.php
├── 2026_01_01_000007_create_products_and_categories_tables.php
├── 2026_01_01_000008_create_invoices_and_invoice_items_tables.php
├── 2026_01_01_000009_create_warranties_and_warranty_claims_tables.php
├── 2026_01_01_000010_create_credit_ledger_entries_table.php
└── 2026_01_01_000011_create_scrap_batteries_inventory_table.php
```

---

## 2. كود التهجيرات الكامل (Laravel Migrations Code)

### Migration 1: الفروع (Branches)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('code', 50)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('branches');
    }
};
```

---

### Migration 2: الأقسام والمسميات الوظيفية (Departments & Job Titles)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->unique();
            $table->timestamps();
        });

        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->decimal('min_salary', 10, 2)->default(0);
            $table->decimal('max_salary', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'title']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_titles');
        Schema::dropIfExists('departments');
    }
};
```

---

### Migration 3: الموظفون وهيكل الرواتب (Employees & Salary Structures)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // في حال كان مستخدماً في النظام
            $table->string('employee_code', 30)->unique();
            $table->string('full_name', 150);
            $table->string('national_id', 20)->unique();
            $table->string('phone', 20)->unique();
            $table->date('hire_date');
            $table->time('shift_start_time')->default('09:00:00');
            $table->time('shift_end_time')->default('17:00:00');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);
            $table->string('zkteco_pin', 50)->nullable()->unique(); // معرف جهاز البصمة
            $table->enum('status', ['active', 'on_leave', 'terminated'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('housing_allowance', 10, 2)->default(0);
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('employees');
    }
};
```

---

### Migration 4: الحضور والانصراف والجزاءات (Attendances & Deductions)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->decimal('overtime_hours', 4, 2)->default(0);
            $table->enum('status', ['present', 'absent', 'late', 'excused', 'holiday'])->default('present');
            $table->string('source', 30)->default('zkteco'); // zkteco, manual, mobile
            $table->timestamps();

            // قيد حاسم: لا يمكن تكرار تسجيل الحضور لنفس الموظف في نفس تاريخ اليوم
            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });

        Schema::create('deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', ['lateness', 'absence', 'disciplinary', 'loan'])->index();
            $table->enum('calculation_method', ['fixed_amount', 'hourly_rate_multiplier', 'day_wage_multiplier']);
            $table->decimal('multiplier_value', 6, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('deduction_date');
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'applied', 'cancelled'])->default('pending')->index();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'deduction_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('employee_deductions');
        Schema::dropIfExists('deduction_rules');
        Schema::dropIfExists('attendances');
    }
};
```

---

### Migration 5: مسيرات الرواتب (Payrolls & Payroll Items)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('total_basic', 12, 2)->default(0);
            $table->decimal('total_allowances', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_net', 12, 2)->default(0);
            $table->enum('status', ['draft', 'reviewed', 'approved', 'disbursed'])->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disbursed_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'year', 'month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('total_allowance', 10, 2)->default(0);
            $table->decimal('total_deduction', 10, 2)->default(0);
            $table->decimal('total_overtime', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2);
            $table->unsignedTinyInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('late_minutes_total')->default(0);
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
    }
};
```

---

### Migration 6: العملاء ومركبات العملاء (Customers & Customer Vehicles)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->unique()->index();
            $table->string('national_id', 30)->nullable();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->decimal('current_credit_balance', 10, 2)->default(0); // رصيد المديونية المستحق
            $table->enum('tier', ['standard', 'vip', 'fleet'])->default('standard');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('plate_number', 50)->index();
            $table->string('car_brand', 50); // تويوتا، هيونداي
            $table->string('car_model', 50); // كورولا، النترا
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('chassis_number', 100)->nullable();
            $table->unsignedInteger('last_odometer')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'plate_number']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_vehicles');
        Schema::dropIfExists('customers');
    }
};
```

---

### Migration 7: الأصناف والبطاريات (Products & Categories)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('sku', 50)->unique()->index();
            $table->string('barcode', 100)->nullable()->unique()->index();
            $table->string('name', 200);
            $table->string('brand', 100); // كلورايد، فارتا، إيه سي ديلكو
            $table->string('capacity_ah', 20)->nullable(); // 45Ah, 70Ah
            $table->string('voltage', 20)->default('12V');
            $table->enum('terminal_type', ['regular', 'reverse', 'side'])->default('regular');
            $table->unsignedSmallInteger('warranty_months')->default(12);
            $table->decimal('cost_price', 10, 2);
            $table->decimal('retail_price', 10, 2);
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('reorder_threshold')->default(5);
            $table->boolean('is_battery')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
```

---

### Migration 8: الفواتير وعناصر الفواتير (Invoices & Invoice Items)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique()->index();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('scrap_deduction_amount', 10, 2)->default(0); // خصم البطارية القديمة (الكهنة)
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2)->default(0); // المتبقي ديناً (Credit)
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'credit', 'split'])->default('cash');
            $table->enum('status', ['paid', 'partially_paid', 'unpaid', 'cancelled', 'refunded'])->default('paid')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('battery_serial_number', 100)->nullable()->index(); // الرقم التسلسلي للبطارية الجديدة
            $table->unsignedSmallInteger('warranty_duration_months')->default(12);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
```

---

### Migration 9: الضمانات ومطالبات الاستبدال (Warranties & Warranty Claims)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number', 100)->unique()->index();
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->enum('status', ['active', 'expired', 'claimed', 'voided'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->constrained('employees')->restrictOnDelete();
            $table->date('claim_date');
            $table->decimal('battery_voltage_tested', 4, 2); // قراءة الفولتميتر
            $table->decimal('cca_tested', 6, 1)->nullable(); // تيار بدء التدوير البارد
            $table->text('issue_description');
            $table->enum('decision', ['pending', 'recharged', 'repaired', 'replaced', 'rejected'])->default('pending')->index();
            $table->foreignId('replacement_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranties');
    }
};
```

---

### Migration 10: دفتر أستاذ الآجل والمديونيات (Credit Ledger Double-Entry)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('credit_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('entry_type', ['invoice_debt', 'payment_collection', 'credit_adjustment', 'refund'])->index();
            $table->decimal('amount', 10, 2); // القيمة دائماً موجبة
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 50)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('credit_ledger_entries');
    }
};
```

---

### Migration 11: مخزون بطاريات الكهنة/الخردة (Scrap Batteries Inventory)
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('scrap_batteries_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete(); // الفاتورة التي تم استبدال الكهنة معها
            $table->string('capacity_ah', 20)->default('70Ah'); // سعة البطارية القديمة
            $table->decimal('scrap_value', 10, 2); // المبلغ المحتسب للعميل
            $table->decimal('lead_weight_kg', 6, 2)->nullable(); // وزن الرصاص التقريبي
            $table->enum('status', ['in_stock', 'sold_to_factory', 'recycled'])->default('in_stock')->index();
            $table->string('batch_number', 50)->nullable()->index(); // رقم شحنة البيع لمصنع التدوير
            $table->foreignId('received_by')->constrained('employees')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('scrap_batteries_inventory');
    }
};
```

---

## 3. خطة الفهارس وضمان سرعة الاستعلام (Indexing & Performance Strategy)

1. **فهارس الباركود والتسلسلات:** حقول `sku`, `barcode`, `serial_number`, `invoice_number` مزودة بـ Unique Indexes لتنفيذ مسح الباركود في زمن استجابة أقل من 5ms عبر أجهزة الماسح الضوئي (USB HID Barcode Scanners).
2. **فهارس السجلات المالية والآجل:** مركب `['customer_id', 'created_at']` في `credit_ledger_entries` لتسريع جلب كشف حساب العميل دون Full Table Scan.
3. **فهارس الحضور والانصراف:** القيد الفريد المركب `['employee_id', 'work_date']` يمنع تكرار أي سجل لنفس اليوم مع تسريع تقارير الرواتب الشهرية.
4. **تأمين البيانات الحساسة:** استخدام `softDeletes()` في جداول العملاء، المنتجات، الموظفين لحماية سجلات الضمان والفواتير القديمة من الحذف العرضي (Data Integrity).
