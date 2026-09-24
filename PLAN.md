# 🚀 Al-Husseini Management System — AI Agent Technical Master Plan

تم إعداد وتوثيق الخطة الفنية الهندسية الشاملة بكل مراحلها وخطواتها وأكوادها التفصيلية في المسار التالي:

👉 **[plans/PLAN.md](plans/PLAN.md)**

---

### ملخص المراحل التقنية التسع (Technical Execution Phases):

1. **المرحلة 1: طبقة هيكل قاعدة البيانات والعلاقات (Migrations & Schema)**
   - جداول شؤون العاملين والورشة (`departments`, `employees`, `attendances`, `deductions`).
   - جداول المبيعات ونقاط البيع والمخزن (`products`, `customers`, `invoices`, `invoice_items`, `credit_payments`).

2. **المرحلة 2: طبقة النماذج والعلاقات المتقدمة (Eloquent Models & Observers)**
   - تعريف الـ Models، العلاقات، الـ Typed Casts، والـ Observers لحسابات المخزن والمديونية التلقائية.

3. **المرحلة 3: طبقة التحقق وقواعد الأمان (Form Requests & Validation Rules)**
   - إنشاء Form Requests مخصصة لكل معاملة تجارية وإدارية لمنع التلاعب وتجاوز حدود الائتمان أو المخزون.

4. **المرحلة 4: محرك الخدمات ومنطق الأعمال (Service Layer & Business Logic)**
   - تجريد منطق الأعمال في خدمات مستقلة (`AttendanceService`, `PayrollService`, `PosOrderService`, `CreditLedgerService`).

5. **المرحلة 5: طبقة التحكم ومسارات النظام (Controllers & API/Web Endpoints)**
   - وحدات التحكم الموجهة لكل قطاع تشغيلي وتنظيم مجموعات المسارات الموثقة.

6. **المرحلة 6: ترقية الواجهات واستبدال LocalStorage بروابط حية (UI Migration)**
   - ربط واجهات Blade والمكونات الحالية (الساعة الرقمية، شريط البصمة السريع، قسائم الرواتب، تقارير ApexCharts) بمتحكمات الـ Backend.

7. **المرحلة 7: تكامل الأجهزة والعتاد (Biometrics, Barcode & ESC/POS)**
   - الربط بماكينات البصمة ZKTeco، وقارئ الباركود السريع، وطابعات الفواتير الحرارية 80mm.

8. **المرحلة 8: الأحداث اللحظية والبث المباشر (WebSockets & Live Alerts)**
   - تفعيل خادم Laravel Reverb لنقل تنبيهات التأخير، ونقص المخزون، وسداد الآجل لحظياً.

9. **المرحلة 9: حزمة الاختبارات الشاملة والنشر السحابي (Testing & Cloud Launch)**
   - تغطية المعادلات الحسابية والمسارات باختبارات Pest وضبط إعدادات الإنتاج والـ Caching.

---

### 📦 خطط التنفيذ الفنية التفصيلية حسب القطاعات:

1. **نظام الموارد البشرية والرواتب (HR & Payroll System):**
   👉 **[plans/hr_system/README.md](plans/hr_system/README.md)** *(مكتمل بنسبة 100% ومختبر بـ 91 اختباراً ناجحاً)*

2. **نظام المبيعات ونقاط البيع والتوريدات والموردين (Sales, POS & Multi-Supplier):**
   👉 **[plans/sales_and_purchases/README.md](plans/sales_and_purchases/README.md)** *(خطة هندسية معتمدة مقسمة إلى 7 مراحل تفصيلية)*
   - **المرحلة 1:** [الهيكل وقواعد البيانات والـ Seeders](plans/sales_and_purchases/PHASE_01_DATABASE_SCHEMA_MIGRATIONS.md)
   - **المرحلة 2:** [النماذج والعلاقات والمراقبين](plans/sales_and_purchases/PHASE_02_MODELS_RELATIONS_OBSERVERS.md)
   - **المرحلة 3:** [طلبات التحقق وقواعد الأمان الصارمة](plans/sales_and_purchases/PHASE_03_FORM_REQUESTS_VALIDATION.md)
   - **المرحلة 4:** [محرك الخدمات والمعادلات المحاسبية (WAC)](plans/sales_and_purchases/PHASE_04_SERVICES_BUSINESS_LOGIC.md)
   - **المرحلة 5:** [المتحكمات والمسارات ومصفوفة الصلاحيات](plans/sales_and_purchases/PHASE_05_CONTROLLERS_ROUTES_PERMISSIONS.md)
   - **المرحلة 6:** [واجهات Blade والطباعة المزدوجة ومخزن الكهنة](plans/sales_and_purchases/PHASE_06_UI_BLADE_COMPONENTS_PRINTING.md)
   - **المرحلة 7:** [حزمة اختبارات Pest المؤتمتة والشاملة](plans/sales_and_purchases/PHASE_07_TESTING_VERIFICATION.md)

---
للاطلاع على المخطط الفني الشامل لكامل التطبيق: افتح الملف [plans/PLAN.md](plans/PLAN.md).
