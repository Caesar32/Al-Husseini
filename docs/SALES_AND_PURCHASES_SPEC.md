# 🚗 Al-Husseini Management System
# 📑 وثيقة المعمارية الهندسية وقواعد الأعمال: قطاع المبيعات ونقاط البيع والتوريدات والموردين
### Sales, POS, Purchasing & Multi-Supplier Architectural & Business Logic Specification

> **الهدف من الوثيقة:**
> تحديد البنية الهندسية الشاملة، ومخطط قواعد البيانات العلائقية، والصيغ المحاسبية الدقيقة، ودورات العمل التشغيلية لإدارة المبيعات، ونقاط البيع السريعة، والتوريدات متعددة الموردين، وإدارة بطاريات الكهنة، والضمانات، ومدفوعات الآجل، وعمولات الفنيين في مركز الحسيني (فرع دمياط الجديدة).

---

## 📌 1. المبادئ التشغيلية المعتمدة (Decided Business Invariants)

بناءً على جلسة المراجعة التشغيلية الفائقة (**Grill Me Session**)، تم اعتماد المعايير التالية كأساس بنيوي لا يقبل التجاوز:

1. **علاقة المنتجات بالموردين (Multi-Supplier Catalog):**
   - العلاقة بين المنتجات والموردين هي علاقة متعدد إلى متعدد (**Many-to-Many**) من خلال جدول وسيط مخصص `supplier_products`، يحفظ كود المنتج لدى كل مورد، وآخر سعر شراء، والمورد المفضل، والحد الأدنى للطلب.
2. **سياسة تقييم المخزون (Inventory Valuation Method):**
   - اعتماد طريقة **المتوسط المرجح (Weighted Average Cost - WAC)** لإعادة احتساب تكلفة وحدة المنتج تلقائياً مع كل شحنة توريد جديدة.
3. **دورة حياة الرقم التسلسلي والضمان (Serial Number & Warranty):**
   - يتم إدخال ومسح سريال البطارية **لحظة البيع فقط على الكاشير** لتسريع حركة التوريد والمخزن، ويولد النظام تلقائياً شهادة ضمان إلكترونية سارية.
   - بيانات سيارة العميل: رقم اللوحة ونوع وموديل السيارة أساسيان، ورقم الشاسيه **اختياري** لمنع تعطيل الكاشير.
   - دعم الطباعة المزدوجة: إيصال حراري سريع (80mm) مدمج به باركود الضمان، مع زر لطباعة شهادة ضمان رسمية مستقلة (A4/A5).
4. **تسعير استبدال البطارية القديمة (الكهنة Scrap Deduction):**
   - **التزام صارم وغير قابل للتعديل اليدوي من الكاشير:** يعتمد الخصم حصراً على **جدول تسعير مرجعي آلي حسب سعة الأمبير (Ah)** (`scrap_pricing_tiers`) لمنع التلاعب في أسعار الرصاص القديم، وتدخل البطارية القديمة تلقائياً إلى مخزن الكهنة.
   - تخصيص قسم وشاشة مستقلة لمخزن الكهنة وتجارة الرصاص في القائمة الجانبية (`admin/scrap-inventory`).
5. **دورة المشتريات والتوريد (Purchase Lifecycle):**
   - **فاتورة توريد مباشرة وسريعة:** إدخال الشحنة واعتمادها فوراً بضغطة زر لتحديث المخزون ومتوسط التكلفة ومديونية المورد لحظياً.
6. **إدارة حسابات ومدفوعات الموردين (Supplier Ledger):**
   - كشف حساب أستاذ بنظام **الرصيد المفتوح (Open Balance)** مع إمكانية ربط سند الصرف بفواتير توريد محددة.
7. **مطالبات الضمان للبطاريات المعيبة (Warranty Claims):**
   - استبدال البطارية التالفة للعميل فوراً ببطارية جديدة من المخزن، وإيداع البطارية المعيبة في **مخزن عهدة الضمان تحت المطالبة** لمطالبة المورد/الشركة المصنعة بالتعويض لاحقاً.
8. **مرونة المدفوعات وسقف الائتمان (Split Payment & Credit Control):**
   - دعم **الدفع المجزأ/المركب (Split Payment)** في نفس الفاتورة (كاش + شبكة/فيزا + آجل).
   - تجاوز سقف الائتمان للعميل مشروط بموافقة المدير (**Manager Override Authorization**).
9. **عمولات الفنيين (Technician Commissions):**
   - احتساب مبالغ قطعية ثابتة محددة لكل نوع خدمة (تركيب بطارية، غيار زيت، فحص دينامو) وترحيلها تلقائياً لمسير الرواتب الشهري (HR Payroll).

---

## 🏛️ 2. الهيكل البياني والعلاقات (Database Schema Blueprint)

```
┌─────────────────┐       ┌──────────────────────┐       ┌─────────────────┐
│    suppliers    │ 1───N │  supplier_products   │ N───1 │    products     │
└────────┬────────┘       └──────────────────────┘       └────────┬────────┘
         │ 1                                                      │ 1
         │                                                        │
         ├────────────────────┐                                   ├────────────────────┐
         │                    │                                   │                    │
         ▼ N                  ▼ N                                 ▼ N                  ▼ N
┌─────────────────┐  ┌─────────────────┐                 ┌─────────────────┐  ┌─────────────────┐
│purchase_invoices│  │ supplier_ledger │                 │  invoice_items  │  │   warranties    │
└────────┬────────┘  └─────────────────┘                 └────────┬────────┘  └─────────────────┘
         │ 1                                                      │ N
         ▼ N                                                      │
┌───────────────────────┐                                         │ 1
│purchase_invoice_items │                                ┌────────┴────────┐
└───────────────────────┘                                │    invoices     │
                                                         └────────┬────────┘
                                                                  │ 1
                                       ┌──────────────────────────┼──────────────────────────┐
                                       │                          │                          │
                                       ▼ N                        ▼ N                        ▼ 1
                             ┌───────────────────┐      ┌───────────────────┐      ┌───────────────────┐
                             │ invoice_payments  │      │  tech_commissions │      │  credit_ledger    │
                             │  (Split Payment)  │      │ (Payroll Bridge)  │      │   (Customer A/R)  │
                             └───────────────────┘      └───────────────────┘      └───────────────────┘
```

### أ. جدول وسيط كتالوج الموردين والمنتجات (`supplier_products`):
```sql
CREATE TABLE supplier_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    supplier_sku VARCHAR(100) NULL,             -- كود الصنف الخاص بالمورد
    last_purchase_price DECIMAL(10, 2) NOT NULL, -- آخر سعر شراء من هذا المورد
    min_order_qty INT UNSIGNED DEFAULT 1,        -- أقل كمية للطلب
    lead_time_days SMALLINT UNSIGNED DEFAULT 1,  -- زمن التوريد المتوقع بالأيام
    is_primary_supplier BOOLEAN DEFAULT FALSE,   -- هل هو المورد الأساسي المفضل؟
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uk_supplier_product (supplier_id, product_id),
    INDEX idx_supplier_sku (supplier_sku)
);
```

### ب. جدول جدول تسعير الكهنة المرجعي (`scrap_pricing_tiers`):
```sql
CREATE TABLE scrap_pricing_tiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    capacity_min_ah INT UNSIGNED NOT NULL,       -- من سعة أمبير (مثلاً 40)
    capacity_max_ah INT UNSIGNED NOT NULL,       -- إلى سعة أمبير (مثلاً 55)
    tier_name VARCHAR(100) NOT NULL,             -- مثل: "بطاريات ملاكي صغيرة 40-55 أمبير"
    default_scrap_price DECIMAL(10, 2) NOT NULL, -- القيمة المرجعية للخصم (مثلاً 600 ج.م)
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### ج. جدول تفصيل المدفوعات المجزأة للفواتير (`invoice_payments`):
```sql
CREATE TABLE invoice_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'credit') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_reference VARCHAR(100) NULL,     -- رقم مرجع إيصال الفيزا أو التحويل
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice_payment_method (invoice_id, payment_method)
);
```

### د. جدول عهدة الضمان ومطالبات الشركات المصنعة (`warranty_claims`):
```sql
CREATE TABLE warranty_claims (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_number VARCHAR(50) UNIQUE NOT NULL,    -- كود المطالبة (CLM-XXXX)
    warranty_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    defective_battery_serial VARCHAR(100) NOT NULL,
    replacement_product_id BIGINT UNSIGNED NOT NULL,
    replacement_battery_serial VARCHAR(100) NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,            -- المورد الموجه له المطالبة
    status ENUM('received', 'sent_to_supplier', 'settled_replacement', 'settled_credit_note', 'rejected') DEFAULT 'received',
    technician_inspection_notes TEXT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    FOREIGN KEY (warranty_id) REFERENCES warranties(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (replacement_product_id) REFERENCES products(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);
```

---

## 📐 3. محرك تقييم المخزون والمتوسط المرجح (WAC Engine)

عند توريد شحنة مشتريات جديدة، يُعاد احتساب تكلفة وحدة المنتج فوراً وفق معادلة المتوسط المرجح الدورية:

$$\text{New Cost Price} = \frac{(\text{Current Stock} \times \text{Current Cost Price}) + (\text{Received Quantity} \times \text{Unit Cost Price})}{\text{Current Stock} + \text{Received Quantity}}$$

### خوارزمية التطبيق في الكود (Service Code Logic):
```php
public function updateWeightedAverageCost(Product $product, int $receivedQty, float $unitCostPrice): void
{
    $currentStock = max(0, $product->current_stock);
    $currentCost = (float) $product->cost_price;

    $totalOldValue = $currentStock * $currentCost;
    $totalNewValue = $receivedQty * $unitCostPrice;
    $totalCombinedQty = $currentStock + $receivedQty;

    if ($totalCombinedQty > 0) {
        $newWeightedCost = ($totalOldValue + $totalNewValue) / $totalCombinedQty;
    } else {
        $newWeightedCost = $unitCostPrice;
    }

    $product->update([
        'cost_price'    => round($newWeightedCost, 2),
        'current_stock' => $totalCombinedQty,
    ]);
}
```

---

## 🔄 4. دورة حياة التوريد والمشتريات (Purchasing & Supplier Flow)

```
[استلام الشحنة من المورد]
          │
          ▼
[إدخال فاتورة الشراء المباشرة] ─── (تحديد المورد، الفروع، المنتجات، الأسعار، الكميات)
          │
          ▼
    DB::transaction
   ┌──────┴────────────────────────────────────────────────┐
   │ 1. إنشاء سجل PurchaseInvoice و PurchaseInvoiceItems    │
   │ 2. تحديث المخزون وحساب المتوسط المرجح (WAC)           │
   │ 3. تحديث أو إنشاء سجل في supplier_products            │
   │ 4. تسجيل قيد مديونية في supplier_ledger_entries       │
   │ 5. تحديث رصيد المورد الإجمالي (current_balance)        │
   └───────────────────────────────────────────────────────┘
```

### معالجة سداد الموردين (Supplier Payouts):
- **طريقة الرصيد المفتوح (Open Balance):** يتم دفع مبلغ نقدي أو شيك أو تحويل بنكي للمورد، فينشأ قيد في `supplier_ledger_entries` يخفض رصيد المورد فورياً.
- **تخصيص السداد:** يمكن اختياريًا تحديد معرف الفاتورة (`purchase_invoice_id`) وتحديث حالة دفع الفاتورة (`paid`, `partially_paid`).

---

## ⚡ 5. محرك نقطة البيع والورشة (POS & Sales Engine)

### أ. خطوات معالجة فاتورة المبيعات:
1. **تحديد العميل والسيارة:** فحص بيانات العميل وسيارته (اللوحة والشاسيه) لربط الضمان.
2. **إضافة المنتجات:** مسح الباركود أو اختيار البطارية وإدخال **الرقم التسلسلي (Serial Number)** للبطارية المبيعة.
3. **فحص خصم الكهنة (Scrap Deduction):**
   - مطابقة سعة البطارية بجدول `scrap_pricing_tiers` واقتراح قيمة الخصم تلقائياً.
   - إمكانية تعديل الكاشير للقيمة مع تسجيل السعة والوزن التقديري.
4. **طريقة الدفع (Payment Distribution):**
   - كاش، أو شبكة (بطاقة بنكية)، أو آجل على حساب العميل (أو توزيع مركب بينهم).
   - إذا وجد جزء آجل: التحقق من `credit_limit`. في حال تجاوزه، يُطلب **رمز موافقة المدير (Manager Override)**.
5. **إسناد الفني وحساب العمولة:**
   - اختيار الفني المسؤول عن التركيب وحساب العمولة المحددة لكل صنف/خدمة وإدراجها في جدول `technician_commissions` بحالة `approved`.
6. **التنفيذ المحاسبي والمخزني المباشر داخل `DB::transaction`:**
   - خصم المخزون `$product->decrement('current_stock', $item->quantity)`.
   - إصدار شهادة الضمان فوراً برقم السيريال وتاريخ البدء والانتهاء.
   - إضافة البطارية القديمة إلى مخزن الكهنة `scrap_batteries_inventory`.
   - تسجيل المديونية في `credit_ledger_entries` وتحديث رصيد العميل.
   - تسجيل عمولة الفني لترحيلها لمسير الراتب في الـ HR.

---

## 🛡️ 6. إدارة الضمانات واستبدال البطاريات المعيبة (Warranty Claims Flow)

```
[عميل يحمل بطارية معيبة خلال فترة الضمان]
                     │
                     ▼
[فحص سيريال البطارية والتحقق من سريان الضمان بالنظام]
                     │
                     ▼
[إثبات عيب الصناعة وفحص شحن الدينامو]
                     │
                     ▼
       تنفيذ استبدال الضمان الفوري
   ┌─────────────────┴────────────────────────────────────────┐
   │ 1. خصم بطارية جديدة بديلة من المخزن بصفر تكلفة على العميل │
   │ 2. إنشاء بطاقة ضمان جديدة بالسيريال الجديد                │
   │ 3. إيداع البطارية المعيبة في مخزن عهدة الضمان             │
   │ 4. فتح تذكرة مطالبة للمورد (Warranty Claim) برقم CLM-XXXX│
   └──────────────────────────────────────────────────────────┘
                     │
                     ▼
[إرسال البطارية المعيبة للشركة المصنعة/المورد]
                     │
        ┌────────────┴────────────┐
        ▼                         ▼
 [استلام بطارية بديلة من المورد]     [إشعار دائن وخصم من رصيد المورد]
(تُدرج كرصيد مخزن بسعر تكلفة صفر)  (قيد في supplier_ledger يخفض المديونية)
```

---

## 💵 7. منظومة الحسابات والقيود المالية المزدوجة (Double-Entry Invariants)

| الحدث التشغيلي | الحساب المدين (Debit +) | الحساب الدائن (Credit -) |
| :--- | :--- | :--- |
| **فاتورة مشتريات بالآجل** | المخزون (Inventory Asset) | رصيد المورد (Accounts Payable) |
| **سداد دفعة نقدية لمورد** | رصيد المورد (Accounts Payable) | الخزينة / البنك (Cash/Bank) |
| **بيع بطارية نقدية مع كهنة** | الخزينة (النقدية المدفوعة) <br> مخزن الكهنة (قيمة خصم الكهنة) | إيراد المبيعات (Sales Revenue) |
| **بيع بطارية بالآجل** | رصيد العميل (Accounts Receivable) | إيراد المبيعات (Sales Revenue) |
| **سداد عميل نقداً لمديونيته** | الخزينة (Cash) | رصيد العميل (Accounts Receivable) |
| **بيع كهنة لمصنع تدوير** | الخزينة / حساب تاجر الكهنة | مخزن الكهنة (Scrap Inventory) <br> أرباح تجارة الخردة |

---

## 🚀 8. خارطة طريق التنفيذ البرمجي (Implementation Roadmap)

### المرحلة 1: طبقة قاعدة البيانات (Migrations & Seeders)
1. إنشاء جدول `supplier_products` لربط المنتجات بالموردين المتعددين مع حقول الكتالوج والأسعار.
2. إنشاء جدول `invoice_payments` لدعم الدفع المركب (Split Payments).
3. إنشاء جدول `scrap_pricing_tiers` لجدول تسعير الكهنة حسب الأمبير.
4. إنشاء جدول `warranty_claims` لإدارة عهدة وضمان البطاريات المعيبة واستبدالها.

### المرحلة 2: طبقة الخدمات والعقود (Contracts & Services)
1. `SupplierServiceInterface` & `SupplierService`: إدارة الموردين، كتالوج الأصناف، وكشوف الحسابات.
2. `PurchaseServiceInterface` & `PurchaseService`: إنشاء فواتير التوريد، واحتساب الـ WAC، وسندات الصرف.
3. `PosOrderServiceInterface` & `PosOrderService`: محرك المبيعات، حجز السريال، خصم الكهنة، والدفع المركب.
4. `WarrantyServiceInterface` & `WarrantyService`: التحقق من الضمان، وتنفيذ الاستبدال، وإدارة عهدة الضمان.
5. `ScrapBatteryServiceInterface` & `ScrapBatteryService`: تسعير ومخزون بطاريات الكهنة وبيع الشحنات.

### المرحلة 3: طبقة التحقق والمتحكمات (Form Requests & Controllers)
1. `StorePurchaseInvoiceRequest`, `StoreSupplierPaymentRequest`.
2. `StorePosInvoiceRequest` مع التحقق الصارم من توفر المخزون وتجاوز الائتمان.
3. `ProcessWarrantyClaimRequest`.

### المرحلة 4: واجهات المستخدم التفاعلية (Blades & UI Components)
1. شاشة الموردين وكشف الحساب التفصيلي (`admin/suppliers`).
2. شاشة فواتير المشتريات والتوريد السريع (`admin/purchases`).
3. شاشة نقطة البيع السريعة للكاشير (`admin/pos`).
4. شاشة فواتير المبيعات وسجل المعاملات والطباعة الحرارية (`admin/invoices`).
5. شاشة متابعة الضمانات والبطاريات المعيبة (`admin/warranties`).
6. شاشة مخزن الكهنة ومتابعة وزن وسعة الرصاص (`admin/scrap-inventory`).

### المرحلة 5: الاختبارات والتوثيق (Testing & Verification)
1. كتابة حزمة اختبارات Pest متكاملة تغطي سيناريوهات التوريد متعدد الموردين، حساب المتوسط المرجح، الدفع المركب، واستبدال الضمان.
2. ربط المسارات بقائمة التنقل في الـ Sidebar والشريط العلوي.

---

**وثيقة معتمدة وموجهة لمهندسي البرمجيات والـ AI Agents للبدء في التنفيذ الفوري.**
