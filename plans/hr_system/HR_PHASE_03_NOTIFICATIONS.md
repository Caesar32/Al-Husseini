# المرحلة 3 لنظام شؤون الموظفين: إشعارات الإدارة والتنبيهات الحية
### (HR Phase 03: Admin Notifications & Real-Time Alerts)

> **طبيعة الملف:** وثيقة تقنية تحدد معمارية الإشعارات الإدارية (Database Notifications + Realtime Events) لربط حركات الموظفين بالمدير العام ومدير الفرع.

---

## 1. جدول الإشعارات في قاعدة البيانات (`notifications`):
استخدام نظام إشعارات Laravel القياسي:
```bash
php artisan notifications:table
php artisan migrate
```

---

## 2. فئات الإشعارات المطلوبة (Notification Classes):
1. **`EmployeeLateNotification`**:
   - يُرسل عند تسجيل موظف حضوراً متأخراً بعد فترة السماح.
   - القنوات: `database` (تظهر في جرس التنبيهات في الـ Topbar) + تنبيه Toast مباشر.
   - المحتوى: اسم الموظف، الفرع، وقت الحضور، وعدد دقائق التأخير.

2. **`LeaveRequestedNotification`**:
   - يُرسل للإدارة فور تقديم موظف طلب إجازة للموافقة أو الرفض.

3. **`PayrollGeneratedNotification`**:
   - يُرسل للمشرف العام لاعتماد مسير الرواتب الشهري بعد توليده من مدير الفرع.
