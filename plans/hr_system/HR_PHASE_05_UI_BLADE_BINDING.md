# المرحلة 5 لنظام شؤون الموظفين: ربط واجهات Blade الحقيقية
### (HR Phase 05: Real Blade Views & Admin Topbar Notifications)

> **طبيعة الملف:** وثيقة تقنية تحدد خطوات تحويل قوالب Blade وإلغاء `hr-store.js` و `localStorage` كلياً، وربط جرس التنبيهات في الـ Topbar بقاعدة البيانات.

---

## 1. تعديلات قوالب Blade:
- `resources/views/admin/hr/employees.blade.php`: عرض الموظفين عبر الـ Controller الحقيقي بدلاً من مصفوفة JS.
- `resources/views/admin/hr/attendance.blade.php`: جدول حضور اليوم المباشر، وأزرار تسجيل الحضور السريع.
- `resources/views/admin/hr/payroll.blade.php`: كروت الرواتب وزر "توليد مسير الشهر" وجدول البنود مع الطباعة.
- `resources/views/admin/layouts/partials/topbar.blade.php`: ربط جرس الإشعارات بقائمة `auth()->user()->unreadNotifications`.

---

## 2. إلغاء الاعتماد على LocalStorage:
إزالة أو تفريغ `public/assets/js/hr-store.js` حتى لا يحدث أي تضارب مع بيانات الخادم وقاعدة البيانات MySQL.
