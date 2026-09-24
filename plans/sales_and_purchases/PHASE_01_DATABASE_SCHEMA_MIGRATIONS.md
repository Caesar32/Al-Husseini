# 🧱 المرحلة 1: طبقة هيكل قاعدة البيانات والتهيئة الأولية
### Phase 01: Database Schema, Migrations & Seeders

> **الهدف الفني:**
> إنشاء وتجهيز الجداول العلائقية الجديدة المعتمدة في وثيقة المعمارية الهندسية، بما في ذلك الكتالوج المشترك بين الموردين والمنتجات، جدول تسعير الكهنة المرجعي الصارم، جدول المدفوعات المجزأة، وتذاكر عهدة وضمان البطاريات المعيبة، مع تغذيتها بالبيانات التأسيسية المعتمدة لفرع دمياط الجديدة.

---

## 📑 1. ملفات الـ Migrations المستهدفة:

### 1. جدول الكتالوج المشترك للموردين والمنتجات (`supplier_products`):
* **اسم الملف المقترح:** `2026_09_23_000002_create_supplier_products_table.php`
* **المواصفات التقنية:**
  ```php
  Schema::create('supplier_products', function (Blueprint $table) {
      $table->id();
      $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
      $table->foreignId('product_id')->constrained()->cascadeOnDelete();
      $table->string('supplier_sku', 100)->nullable()->index();
      $table->decimal('last_purchase_price', 10, 2);
      $table->unsignedInteger('min_order_qty')->default(1);
      $table->unsignedSmallInteger('lead_time_days')->default(1);
      $table->boolean('is_primary_supplier')->default(false)->index();
      $table->text('notes')->nullable();
      $table->timestamps();

      $table->unique(['supplier_id', 'product_id'], 'uk_supplier_product');
      $table->index(['product_id', 'is_primary_supplier']);
  });
  ```

---

### 2. جدول تسعير الكهنة المرجعي الصارم (`scrap_pricing_tiers`):
* **اسم الملف المقترح:** `2026_09_23_000003_create_scrap_pricing_tiers_table.php`
* **المواصفات التقنية:**
  ```php
  Schema::create('scrap_pricing_tiers', function (Blueprint $table) {
      $table->id();
      $table->unsignedInteger('capacity_min_ah');
      $table->unsignedInteger('capacity_max_ah');
      $table->string('tier_name', 100);
      $table->decimal('default_scrap_price', 10, 2);
      $table->boolean('is_active')->default(true)->index();
      $table->timestamps();

      $table->index(['capacity_min_ah', 'capacity_max_ah', 'is_active'], 'idx_scrap_tier_lookup');
  });
  ```

---

### 3. جدول تفصيل المدفوعات المجزأة للفواتير (`invoice_payments`):
* **اسم الملف المقترح:** `2026_09_23_000004_create_invoice_payments_table.php`
* **المواصفات التقنية:**
  ```php
  Schema::create('invoice_payments', function (Blueprint $table) {
      $table->id();
      $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
      $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'credit'])->index();
      $table->decimal('amount', 10, 2);
      $table->string('transaction_reference', 100)->nullable();
      $table->string('notes', 255)->nullable();
      $table->timestamps();

      $table->index(['invoice_id', 'payment_method']);
  });
  ```

---

### 4. جدول عهدة الضمان ومطالبات الموردين للبطاريات المعيبة (`warranty_claims`):
* **اسم الملف المقترح:** `2026_09_23_000005_create_warranty_claims_table.php`
* **المواصفات التقنية:**
  ```php
  Schema::create('warranty_claims', function (Blueprint $table) {
      $table->id();
      $table->string('claim_number', 50)->unique()->index();
      $table->foreignId('warranty_id')->constrained()->restrictOnDelete();
      $table->foreignId('customer_id')->constrained()->restrictOnDelete();
      $table->foreignId('branch_id')->constrained()->restrictOnDelete();
      $table->string('defective_battery_serial', 100)->index();
      $table->foreignId('replacement_product_id')->constrained('products')->restrictOnDelete();
      $table->string('replacement_battery_serial', 100)->index();
      $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
      $table->enum('status', [
          'received', 
          'sent_to_supplier', 
          'settled_replacement', 
          'settled_credit_note', 
          'rejected'
      ])->default('received')->index();
      $table->text('technician_inspection_notes');
      $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
      $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('received_at')->useCurrent();
      $table->timestamp('resolved_at')->nullable();
      $table->timestamps();
  });
  ```

---

## 🔍 2. منظومة فهارس البحث السريع (Search Indexing Blueprint):

لضمان عمل البحث الشامل (Spotlight) وشاشات الكاشير والموردين بسرعة استجابة أقل من **15ms**، يتم تزويد الجداول الجديدة بفهارس بحث مركبة مخصصة:

| الجدول | الفهارس المنفذة (Indexes) | الغرض التشغيلي وسيناريو البحث |
| :--- | :--- | :--- |
| **`supplier_products`** | `supplier_sku` <br> `[supplier_id, supplier_sku]` <br> `[product_id, is_primary_supplier]` | البحث الفوري عن المنتجات باستخدام **كود الصنف لدى المورد**، واسترجاع المورد الأساسي لكل بطارية. |
| **`warranty_claims`** | `claim_number` <br> `defective_battery_serial` <br> `replacement_battery_serial` <br> `[customer_id, status]` | البحث السريع برقم تذكرة الضمان (`CLM-XXXX`) أو بسيريال البطارية التالفة أو البديلة للتحقق من المورد. |
| **`purchase_invoices`** | `invoice_number` <br> `[supplier_id, invoice_date]` <br> `[branch_id, payment_status]` | البحث برقم فاتورة الشراء وتصفية شحنات المورد وتواريخ التوريد وحالة السداد. |
| **`purchase_invoice_items`**| `batch_number` <br> `[product_id, purchase_invoice_id]` | البحث والتتبع الفوري بأرقام الشحنات والتشغيلات (Batch Tracking) لبطاريات معيبة. |
| **`scrap_pricing_tiers`** | `[capacity_min_ah, capacity_max_ah, is_active]` | المطابقة اللحظية لسعر خصم الكهنة بمجرد إدخال سعة الأمبير على الكاشير بدون Full Scan. |
| **`invoice_payments`** | `transaction_reference` <br> `[invoice_id, payment_method]` | البحث برقم مرجع عملية الدفع الإلكتروني (إيصال الفيزا/التحويل البنكي) عند مراجعة الخزينة. |

---

## 🧪 3. ملفات الـ Seeders والتغذية التأسيسية:

1. **`ScrapPricingTiersSeeder`:**
   - تغذية الشرائح المعتمدة لسوق بطاريات السيارات المصري:
     - 40 إلى 55 أمبير (ملاكي صغير): **600 ج.م**.
     - 56 إلى 75 أمبير (ملاكي متوسط - الحجم القياسي الشائع): **800 ج.م**.
     - 76 إلى 100 أمبير (سيارات دفع رباعي وميكروباص): **1,100 ج.م**.
     - 101 إلى 150 أمبير (نصف نقل وجامبو): **1,700 ج.م**.
     - 151 إلى 225 أمبير (نقل ثقيل وتريلات ومعدات): **2,500 ج.م**.

2. **`SupplierProductsSeeder`:**
   - ربط منتجات البطاريات والزيوت الموجودة بالموردين الأساسيين (كلورايد، فارتا، التعاون، شل) مع تسجيل أسعار التوريد الابتدائية وأكواد الموردين.

3. **تحديث `DatabaseSeeder`:** استدعاء هذه الـ Seeders ضمن الترتيب المنطقي.

---

## ✅ معايير التحقق والاعتماد (Phase 01 Verification):
- [x] تنفيذ أمر `php artisan migrate` بنجاح دون أي أخطاء مفاتيح خارجية.
- [x] إنشاء نماذج `ScrapPricingTier`, `SupplierProduct`, `InvoicePayment`.
- [x] تنفيذ أمر `php artisan db:seed` وتأكيد وجود بيانات جدول الكهنة والكتالوج.
- [x] التحقق التفاعلي في Tinker من مطابقة أسعار الكهنة لسعات 70Ah و 50Ah و 120Ah.
- [x] تشغيل حزمة الاختبارات القائمة (`php artisan test`): **91 passed (312 assertions)** دون أي أخطاء.
