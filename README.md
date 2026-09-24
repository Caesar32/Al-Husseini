# 🏢 نظام مجموعة الحسيني لإدارة الفروع ونقاط البيع والموارد البشرية
### Al-Husseini ERP & POS Management System

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-%5E8.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Pest_Tests-91_Passed-00D084?style=for-the-badge&logo=pest&logoColor=white" alt="Pest Tests">
  <img src="https://img.shields.io/badge/Branch-دمياط_الجديدة-0D6EFD?style=for-the-badge" alt="Active Branch">
  <img src="https://img.shields.io/badge/Search-Spotlight_Ctrl+K-F59E0B?style=for-the-badge" alt="Spotlight Search">
</p>

---

## 📌 عن النظام (About The System)

**نظام مجموعة الحسيني** هو نظام تخطيط موارد مؤسسي ونقاط بيع متكامل (**ERP & POS**) متخصص في تجارة وخدمات بطاريات السيارات، الزيوت، الفلاتر، وإدارة ورش الصيانة وخدمات الإنقاذ السريع، بالإضافة إلى منظومة كاملة لإدارة الموارد البشرية والشؤون المالية والمخزون.

* **الفرع التشغيلي النشط حالياً:** `فرع دمياط الجديدة` (شارع المحجوب، دمياط الجديدة، محافظة دمياط - كود: `MAIN`).
* **قابلية التوسع:** النظام مهيأ بالكامل لإدارة الفروع المتعددة (**Multi-Branch Ready**).

---

## 🚀 الميزات الرئيسية (Key Modules & Features)

### 1. شؤون العاملين والموارد البشرية (HR Management)
* **دليل الموظفين:** ملف شامل لكل موظف، عقود العمل، البدلات، الشفتات ومواعيد الحضور وفترات السماح.
* **محرك الحضور والانصراف الذكي:**
  - مكافحة البصمة المزدوجة وفترة تبريد 5 دقائق (Anti-Passback Debounce).
  - منع تسجيل الحضور المكرر بعد مرور أكثر من 5 دقائق.
  - احتساب دقائق التأخير التلقائي وتطبيق الجزاءات الإدارية المعتمدة آلياً.
  - الحفاظ على سلامة التسلسل الزمني وبصمات أيام الإجازات كعمل إضافي (Overtime).
* **مسيرات الرواتب (Monthly Payroll):**
  - دورة حياة ثلاثية المراحل: `draft` مسودة -> `approved` اعتماد -> `disbursed` صرف وإغلاق مالي.
  - استعلامات مجمعة عالية الأداء (Bulk Queries) تمنع بطء الحسابات واستعلامات N+1.
* **التقارير والإحصائيات التفاعلية (HR Reports):**
  - فلاتر يومية وشهرية وفترات مخصصة آمنة التوقيت بدون أي انزياح زمني.
  - رسوم بيانية تفاعلية (ApexCharts) لمعدلات الانضباط وتوزيع الغياب والتأخير.
  - طباعة كارت الموظف الفردي بشكل مستقل، وتصدير إكسيل عربي معتمد (`UTF-8 BOM`).

### 2. المبيعات ونقاط البيع السريعة (POS & Sales)
* إصدار فواتير بيع وتركيب فورية.
* خصم قيمة البطاريات القديمة (الكهنة) وتوجيهها لمخزن الكهنة (`ScrapBatteriesInventory`).
* تسجيل وتوليد شهادات الضمان الرقمية التلقائية وربطها بسيارة العميل والشاسيه.
* دفتر أستاذ الحسابات الآجلة والحدود الائتمانية للعملاء (`CreditLedgerEntry`).
* احتساب وتوزيع عمولات الفنيين على الفواتير المعتمدة.

### 3. منظومة الفهارس والبحث الشامل (Global Spotlight Search)
* **اختصار سريع:** فتح نافذة البحث الفوري من أي شاشة عبر `Ctrl + K` أو `Cmd + K`.
* **مطابقة عربية ذكية (Bidirectional Normalization):** تجاوز أخطاء الهمزات (`أ/إ/آ`) والتاء المربوطة (`ة/ه`) والياء والألف المقصورة (`ي/ى`).
* **فهارس قواعد بيانات مركبة:** استجابة استعلامات البحث خلال أقل من 15ms.

---

## 🏛️ المعمارية البرمجية (Architecture & Engineering Standards)

النظام مبني وفق معمارية برمجية صارمة وعالية الموثوقية:
* **Service-Layer Pattern & Contracts:** تجريد كامل لمنطق الأعمال داخل `app/Services/Hr/*` ومطابقة العقود في `app/Contracts/Hr/*`.
* **Database Transactions:** حماية جميع الحركات المالية، الخصومات، والرواتب داخل `DB::transaction`.
* **N+1 Prevention:** تفعيل `Model::preventLazyLoading(!app()->isProduction())` لمنع أي استعلامات كسلية غير مصرح بها.
* **Role-Based Access Control:** تحكم دقيق في الصلاحيات باستخدام حزمة `spatie/laravel-permission`.

---

## 🛠️ متطلبات التشغيل والتثبيت (Quick Start)

### المتطلبات (Prerequisites):
* PHP >= 8.2 (مع امتدادات: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `sqlite3` أو `pdo_mysql`)
* Composer >= 2.x
* Node.js & NPM

### خطوات التشغيل:
```bash
# 1. استنساخ المشروع وتثبيت الحزم
git clone <repository-url>
cd Al-Husseini
composer install
npm install

# 2. ملف البيئة وتوليد المفتاح
cp .env.example .env
php artisan key:generate

# 3. ترحيل قاعدة البيانات وتغذية البيانات الأساسية
php artisan migrate --seed

# 4. بناء الأصول وتشغيل خادم التطوير
npm run build
# أو في بيئة التطوير المباشر:
composer run dev
```

---

## 🧪 حزمة الاختبارات الآلية (Automated Testing)

يحتوي المشروع على حزمة اختبارات شاملة ومكثفة لاختبار سيناريوهات دورة حياة الموظف، حسابات الرواتب، قيود الحضور، والبحث الشامل:

```bash
# تشغيل كامل حزمة الاختبارات:
php artisan test

# نتيجة الاختبارات الحالية:
# Tests:    91 passed (312 assertions)
# Duration: ~5.6s
```

---

## 📚 وثائق المشروع الفنية (Technical Documentation)

* [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md): **المرجع التقني الشامل** للنظام (خريطة الكيانات، القواعد الصارمة، الفهارس، ومعايير Eager Loading).
* [docs/SALES_AND_PURCHASES_SPEC.md](docs/SALES_AND_PURCHASES_SPEC.md): **وثيقة المعمارية الهندسية وقواعد الأعمال:** قطاع المبيعات ونقاط البيع والتوريدات والموردين المتعددين والكهنة والضمانات.
* [docs/SALES_PURCHASES_SCENARIOS.md](docs/SALES_PURCHASES_SCENARIOS.md): **موسوعة السيناريوهات التشغيلية والحالات الحدية:** حالات التوريد، الكاشير، الكهنة، الضمان، وتزامن المخزون.
* [plans/PLAN.md](plans/PLAN.md): خطة العمل والمراحل التنفيذية المتكاملة لمشروع التطوير والترقية.

---
**حقوق التطوير والتوزيع © 2026 مجموعة الحسيني — جميع الحقوق محفوظة.**
