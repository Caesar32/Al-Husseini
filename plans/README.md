# الدليل الفني الشامل لتطوير نظام مركز الحسيني لبطاريات السيارات وإدارة الورشة وشؤون العاملين
### (Al-Husseini Technical Engineering Architecture & Implementation Phases)

مرحباً بك في مستودع الخطط الفنية التفصيلية الخاصة بتطوير النظام من الصفر حتى مرحلة الإنتاج. تم تقسيم هذا الدليل إلى **9 مراحل تقنية مستقلة**، بالإضافة إلى دراسة التطبيع المتقدمة لقاعدة البيانات، بهدف توجيه المطورين ومهندسي الـ AI لتنفيذ كود برمجي صارم وعالي الأداء.

---

## 📑 فهرس المراحل والوثائق التقنية

| # | الوثيقة التقنية | الوصف ومحتوى الملف | الرابط المباشر |
|---|---|---|---|
| 📐 | **تحليل التطبيع (3NF/BCNF)** | دراسة معالجة العيوب والتحويل من 1NF إلى 3NF والتخلص من التكرار والاعتماديات الوظيفية. | [DATABASE_NORMALIZATION_ANALYSIS.md](file:///d:/Projects/Al-Husseini/plans/DATABASE_NORMALIZATION_ANALYSIS.md) |
| 01 | **المرحلة 1: مخطط قاعدة البيانات** | كود الـ Migrations الكامل مع القيود الحسابية، المفاتيح الأجنبية، والفهارس المركبة. | [PHASE_01_DATABASE_SCHEMA.md](file:///d:/Projects/Al-Husseini/plans/PHASE_01_DATABASE_SCHEMA.md) |
| 02 | **المرحلة 2: نماذج Eloquent والمراقبين** | تعريفات الـ Models، العلاقات، الـ Scopes، و الـ Observers لأتمتة المخزون والضمان والآجل. | [PHASE_02_ELOQUENT_MODELS_OBSERVERS.md](file:///d:/Projects/Al-Husseini/plans/PHASE_02_ELOQUENT_MODELS_OBSERVERS.md) |
| 03 | **المرحلة 3: طلبات التحقق وتأمين المدخلات** | فئات الـ Form Requests، قواعد التحقق من سقف الائتمان، وفحص أرقام الباركود ورسائل الخطأ. | [PHASE_03_FORM_REQUESTS_VALIDATION.md](file:///d:/Projects/Al-Husseini/plans/PHASE_03_FORM_REQUESTS_VALIDATION.md) |
| 04 | **المرحلة 4: طبقة الخدمات والمنطق التجاري** | محركات الحسابات الدقيقة (احتساب التأخير، مسير الرواتب، فواتير الكاش والكهنة، تسوية المديونيات). | [PHASE_04_SERVICES_BUSINESS_LOGIC.md](file:///d:/Projects/Al-Husseini/plans/PHASE_04_SERVICES_BUSINESS_LOGIC.md) |
| 05 | **المرحلة 5: وحدات التحكم ومسارات النظام** | الـ Controllers ومسارات الـ `web.php` و `api.php` مع الـ Middleware وتحديد معدل الطلب. | [PHASE_05_CONTROLLERS_ROUTES.md](file:///d:/Projects/Al-Husseini/plans/PHASE_05_CONTROLLERS_ROUTES.md) |
| 06 | **المرحلة 6: ربط الواجهات بالخادم** | دليل تحويل قوالب Blade وإلغاء `hr-store.js` و `sales-store.js` واستبدالها بـ Axios و Blade Loop. | [PHASE_06_UI_MIGRATION_BACKEND_BINDING.md](file:///d:/Projects/Al-Husseini/plans/PHASE_06_UI_MIGRATION_BACKEND_BINDING.md) |
| 07 | **المرحلة 7: تكامل العتاد والأجهزة** | برمجة قارئ الباركود USB HID، طابعات الفواتير الحرارية 80mm ESC/POS، وجهاز بصمة ZKTeco. | [PHASE_07_HARDWARE_INTEGRATION.md](file:///d:/Projects/Al-Husseini/plans/PHASE_07_HARDWARE_INTEGRATION.md) |
| 08 | **المرحلة 8: البث اللحظي والتنبيهات الحية** | إعداد Laravel Reverb و Laravel Echo لبث أحداث التأخير ونقص المخزون لحظياً للوحة التحكم. | [PHASE_08_WEBSOCKETS_REALTIME.md](file:///d:/Projects/Al-Husseini/plans/PHASE_08_WEBSOCKETS_REALTIME.md) |
| 09 | **المرحلة 9: الاختبارات ونشر الإنتاج** | جناح اختبارات Pest PHP للعمليات الحساسة، وإعدادات خادم الإنتاج و Supervisor و Cron Jobs. | [PHASE_09_TESTING_DEPLOYMENT.md](file:///d:/Projects/Al-Husseini/plans/PHASE_09_TESTING_DEPLOYMENT.md) |

---

## 🛠️ مواصفات التنفيذ القياسية للمشروع
- **لغة ومحرك التطوير:** PHP 8.2+ / Laravel 11.x
- **قاعدة البيانات الموصى بها:** PostgreSQL 16+ أو MySQL 8.0+
- **نظام كاشير فوري:** استجابة مسح الباركود أقل من 10ms.
- **سلامة الحسابات:** استخدام `DB::transaction` و `lockForUpdate` لكافة العمليات المالية ونقاط البيع.
