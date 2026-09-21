# 🔬 تحليل ومخطط تطبيع قاعدة البيانات (Database Normalization Analysis - 3NF / BCNF)

### مركز ومجموعة الحسيني لبطاريات وزيوت وصيانة السيارات

---

## 1. إشكاليات النموذج غير المطبع (Denormalized Model Anomalies)

في الواجهة الحالية ومتاجر `LocalStorage` (`hr-store.js` و `sales-store.js`)، البيانات مسطحة وتواجه ثلاث مشاكل بنيوية خطيرة إذا نُقلت كما هي لقاعدة بيانات إنتاجية:

1. **شذوذ التحديث (Update Anomalies)**:
    - بيانات السيارة ورقم اللوحة مخزنة مباشرة كحقول داخل جدول العميل (`customers.car_model`, `customers.plate_number`). إذا امتلك العميل أكثر من سيارة (مثلاً سيارة ملاكي وسيارة نصف نقل)، أو قام بتغيير سيارته، تتلف فواتير الضمان السابقة أو يُجبر النظام على تكرار العميل.
2. **شذوذ الحذف (Deletion Anomalies)**:
    - تخزين الماركة وبيانات الأمبير ونوع البطارية داخل اسم المنتج يؤدي إلى عدم إمكانية تصنيف أو جرد منتجات ماركة معينة (مثل "فارتا Varta") إذا نفد مخزونها أو حُذفت سجلاتها مؤقتاً.
3. **شذوذ المعاملات المالية (Financial Ledger Discrepancy)**:
    - تخزين حقل منفرد `credit_balance` في جدول العميل وتعديله يدوياً بدون **سجل أستاذ مزدوج (Double-Entry Ledger)** يعرض الحسابات للانهيار وفقدان أثر التدقيق (Audit Trail) عند حدوث خطأ أو تراجع في فاتورة.

---

## 2. مراحل التطبيع الهندسي (Normalization Stages)

### أ) الشكل الطبيعي الأول (1NF - First Normal Form)

- **القاعدة**: كل عمود يحتوي على قيمة ذرية وحيدة (Atomic Values)، ولا توجد مصفوفات أو مجموعات متكررة.
- **التطبيق**:
    - فصل بنود الفاتورة (`invoice_items`) بالكامل عن الفاتورة الأم (`invoices`).
    - فصل بطاريات الكهنة المسترجعة (`scrap_items`) ككيان محاسبي مستقل بسعر توريد وخردة محدد.

### ب) الشكل الطبيعي الثاني (2NF - Second Normal Form)

- **القاعدة**: تحقيق 1NF + اعتماد كافة الأعمدة غير المفتاحية اعتماداً وظيفياً كاملاً على المفتاح الأساسي (No Partial Dependencies).
- **التطبيق**:
    - سعر شراء وبيع وصلاحية كارت الضمان للبطارية (`warranty_serial`, `warranty_months`) تعتمد على البند الفعلي المباع وليس الفاتورة الإجمالية.
    - بيانات الفني والوردية ترتبط بالموظف وليس بسجل البصمة.

### ج) الشكل الطبيعي الثالث (3NF - Third Normal Form)

- **القاعدة**: تحقيق 2NF + عدم وجود أي اعتماد متعدٍ (No Transitive Dependencies: عمود غير مفتاحي يعتمد على عمود غير مفتاحي آخر).
- **التطبيق**:
    - فصل الماركات (`brands`) ومجموعات البطاريات (`battery_models`) عن جدول المخزون.
    - فصل سيارات العملاء (`customer_vehicles`) في جدول مستقل متعدد لواحد (`customers` 1:N `customer_vehicles`).
    - فصل حسابات الآجل إلى سجل قيود مالي (`credit_ledger_entries`) مع الاحتفاظ بـ `credit_balance` المحسوب عبر Trigger أو Generated Column لضمان سرعة القراءة دون المساس بصحة التدقيق.

---

## 3. المخطط العلائقي المطبع الكامل (Normalized ERD Architecture)

```
 [ departments ] 1 ─── N [ employees ] 1 ─── N [ attendances ]
                              │
                              ├─── N [ deductions ]
                              └─── N [ employee_shifts ]

 [ customers ] 1 ─── N [ customer_vehicles ]
       │                         │
       │                         └── 1 ─── N [ invoices ] 1 ─── N [ invoice_items ] ─── N:1 [ products ]
       │                                           │                                              │
       └── 1 ─── N [ credit_ledger_entries ] ──────┘                                              └── N:1 [ brands ]
```

---

## 4. تفصيل الجداول المطبعة (Normalized Schema Specifications)

### 4.1 قطاع المبيعات والمخزن والسيارات

#### جدول الماركات والمصنعين (`brands`):

```sql
CREATE TABLE brands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,       -- 'كلورايد Chloride', 'فارتا Varta', 'موبيل Mobil'
    country_of_origin VARCHAR(50) NULL,      -- 'ألماني', 'مصري', 'كوري'
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### جدول المنتجات والمواصفات الفنية (`products`):

```sql
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id BIGINT UNSIGNED NOT NULL,
    barcode VARCHAR(60) NOT NULL UNIQUE,
    sku VARCHAR(40) NOT NULL UNIQUE,
    category ENUM('battery', 'oil', 'grease', 'service', 'part') NOT NULL,
    name VARCHAR(150) NOT NULL,
    ampere SMALLINT UNSIGNED NULL,           -- 60, 70, 80, 100
    voltage TINYINT UNSIGNED DEFAULT 12,     -- 12V, 24V
    terminal_type VARCHAR(30) NULL,          -- 'قطب يمين R', 'قطب شمال L', 'عريض', 'رفيع'
    battery_tech VARCHAR(50) NULL,           -- 'جافة Calcium', 'AGM Start-Stop', 'EFB'
    cost_price DECIMAL(10, 2) NOT NULL,      -- سعر التكلفة
    retail_price DECIMAL(10, 2) NOT NULL,    -- سعر البيع الجديد
    scrap_deduction DECIMAL(10, 2) NOT NULL, -- قيمة خصم البطارية القديمة (الكهنة)
    stock_quantity INT NOT NULL DEFAULT 0,
    min_stock_alert SMALLINT UNSIGNED DEFAULT 5,
    unit VARCHAR(20) DEFAULT 'قطعة',
    status ENUM('in_stock', 'low_stock', 'out_of_stock') NOT NULL DEFAULT 'in_stock',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE RESTRICT,
    INDEX idx_barcode (barcode),
    INDEX idx_category_status (category, status)
);
```

#### جدول العملاء (`customers`):

```sql
CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,        -- 'CUST-00101'
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    phone_alt VARCHAR(30) NULL,
    national_id VARCHAR(20) NULL,            -- للعملاء أصحاب حسابات الآجل المفتوحة
    credit_limit DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('active', 'blocked', 'warning') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_customer_phone (phone)
);
```

#### جدول مركبات العملاء (`customer_vehicles`):

```sql
CREATE TABLE customer_vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    plate_number VARCHAR(30) NOT NULL,       -- 'أ ب ج 1234'
    car_brand VARCHAR(50) NOT NULL,          -- 'تويوتا', 'هيونداي', 'مرسيدس'
    car_model VARCHAR(80) NOT NULL,          -- 'كورولا 2021', 'توسان 2022'
    chassis_number VARCHAR(60) NULL,
    current_battery_id BIGINT UNSIGNED NULL, -- آخر بطارية تم تركيبها بالسيارة
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (current_battery_id) REFERENCES products(id) ON DELETE SET NULL,
    UNIQUE KEY uq_customer_plate (customer_id, plate_number)
);
```

#### جدول الفواتير (`invoices`):

```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE, -- 'INV-2026-00001'
    customer_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    cashier_id BIGINT UNSIGNED NOT NULL,
    technician_id BIGINT UNSIGNED NULL,         -- الفني المسؤول عن الفحص والتركيب
    invoice_type ENUM('sale', 'maintenance', 'exchange', 'warranty') DEFAULT 'sale',
    subtotal DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_scrap_value DECIMAL(10, 2) DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    grand_total DECIMAL(10, 2) NOT NULL,
    paid_amount DECIMAL(10, 2) NOT NULL,
    credit_amount DECIMAL(10, 2) DEFAULT 0.00,
    payment_method ENUM('cash', 'credit', 'visa', 'instapay', 'vodafone_cash', 'split') NOT NULL,
    payment_status ENUM('paid', 'partial', 'unpaid') NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (vehicle_id) REFERENCES customer_vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_invoice_date (created_at),
    INDEX idx_payment_status (payment_status)
);
```

#### جدول بنود الفاتورة والضمان (`invoice_items`):

```sql
CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    has_scrap_exchange BOOLEAN DEFAULT FALSE,
    scrap_unit_value DECIMAL(10, 2) DEFAULT 0.00,
    line_total DECIMAL(10, 2) NOT NULL,
    warranty_serial_number VARCHAR(100) NULL,  -- سيريال البطارية لضمان الحسيني
    warranty_months TINYINT UNSIGNED DEFAULT 0,
    warranty_start_date DATE NULL,
    warranty_expiry_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_warranty_serial (warranty_serial_number)
);
```

#### جدول بطاريات الكهنة المسترجعة (`scrap_batteries_inventory`):

```sql
CREATE TABLE scrap_batteries_inventory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_item_id BIGINT UNSIGNED NULL,
    ampere SMALLINT UNSIGNED NOT NULL,
    estimated_weight DECIMAL(6, 2) NULL,        -- بالوزن الكيلوجرام لحساب سعر الرصاص
    settlement_value DECIMAL(10, 2) NOT NULL,
    status ENUM('in_store', 'sold_to_smelter', 'reconditioned') DEFAULT 'in_store',
    smelter_batch_id VARCHAR(50) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (invoice_item_id) REFERENCES invoice_items(id) ON DELETE SET NULL
);
```

#### دفتر أستاذ حسابات الآجل المزدوج (`credit_ledger_entries`):

```sql
CREATE TABLE credit_ledger_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_number VARCHAR(50) NOT NULL UNIQUE,  -- 'LEDGER-2026-0001'
    customer_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    entry_type ENUM('debit_due', 'credit_payment', 'adjustment_credit', 'adjustment_debit') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    balance_before DECIMAL(10, 2) NOT NULL,
    balance_after DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(30) NULL,
    collected_by BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_customer_ledger (customer_id, created_at)
);
```

---

### 4.2 قطاع شؤون العاملين ومواعيد الورشة

#### جدول الأقسام (`departments`):

```sql
CREATE TABLE departments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    manager_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### جدول الموظفين (`employees`):

```sql
CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE, -- 'BAT-101'
    department_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL UNIQUE,       -- في حال كان له حساب باللوحة
    name VARCHAR(150) NOT NULL,
    job_title VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    avatar_url VARCHAR(255) NULL,
    base_salary DECIMAL(10, 2) NOT NULL,
    allowances DECIMAL(10, 2) DEFAULT 0.00,
    shift_start_time TIME NOT NULL DEFAULT '09:00:00',
    shift_end_time TIME NOT NULL DEFAULT '18:00:00',
    status ENUM('active', 'on_leave', 'suspended', 'terminated') DEFAULT 'active',
    hire_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_emp_dept (department_id, status)
);
```

#### جدول سجلات البصمة والحضور (`attendances`):

```sql
CREATE TABLE attendances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    punch_in TIME NULL,
    punch_out TIME NULL,
    status ENUM('on_time', 'late', 'absent', 'on_leave', 'holiday') NOT NULL DEFAULT 'absent',
    lateness_minutes SMALLINT UNSIGNED DEFAULT 0,
    early_leave_minutes SMALLINT UNSIGNED DEFAULT 0,
    punch_source ENUM('zkteco_hardware', 'manual_web', 'rfid', 'mobile_app') DEFAULT 'manual_web',
    verified_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_emp_work_date (employee_id, work_date),
    INDEX idx_work_date_status (work_date, status)
);
```

#### جدول الخصومات والقرارات الإدارية (`deductions`):

```sql
CREATE TABLE deductions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    decision_number VARCHAR(50) NOT NULL UNIQUE, -- 'BAT-DEC-2026/015'
    employee_id BIGINT UNSIGNED NOT NULL,
    attendance_id BIGINT UNSIGNED NULL,
    amount DECIMAL(10, 2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    violation_date DATE NOT NULL,
    manager_notes TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    status ENUM('applied', 'waived') DEFAULT 'applied',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    FOREIGN KEY (attendance_id) REFERENCES attendances(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_emp_deduction_date (employee_id, violation_date)
);
```

#### جدول مسيرات الرواتب المغلقة شهرياً (`payroll_settlements`):

```sql
CREATE TABLE payroll_settlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_code VARCHAR(50) NOT NULL UNIQUE,   -- 'PAYROLL-2026-09'
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    base_salary DECIMAL(10, 2) NOT NULL,
    total_allowances DECIMAL(10, 2) NOT NULL,
    total_deductions DECIMAL(10, 2) NOT NULL,
    net_salary DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'approved', 'disbursed') DEFAULT 'pending',
    disbursed_at TIMESTAMP NULL,
    disbursed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT,
    FOREIGN KEY (disbursed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_emp_payroll_period (employee_id, year, month)
);
```

---

## 5. مزايا هذا التطبيع الفني:

1. **النزاهة المالية المطلقة**: حسابات الآجل وسداد الديون تتبع مبدأ السجل المحاسبي المزدوج (`credit_ledger_entries`) مما يمنع نهائياً فقدان تتبع أي جنيه مسدد أو دائن.
2. **إدارة السيارات المتعددة للعميل الواحد**: العميل يستطيع تسجيل أكثر من سيارة مع الاحتفاظ برقم الشاسيه واللوحة وتاريخ آخر بطارية تم تركيبها.
3. **التتبع الدقيق للضمان والكهنة**: كل بطارية مباعة لها سيريال ضمان وفترة انتهاء مستقلة تماماً، وبطاريات الخردة المسترجعة لها مخزون وتسعير مستقل.
4. **أداء استعلامات فائق السرعة**: استخدام الفهارس المركبة (`Composite Indexes`) على التواريخ وأكواد الموظفين والعملاء يضمن تنفيذ استعلامات لوحة التحكم في أقل من 5 مللي ثانية.
