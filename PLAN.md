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
للاطلاع على المخطط الفني الكامل بكل تفاصيل الجداول والأكواد البرمجية: افتح الملف [plans/PLAN.md](plans/PLAN.md).
