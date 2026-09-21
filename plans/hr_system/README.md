# نظام شؤون العاملين والورشة والحضور والانصراف والرواتب والإشعارات
### (HR, Attendance, Payroll, Deductions & Admin Notifications System)

> **طبيعة المجلد:** مجلد فني مخصص ومستقل يركز بنسبة 100% على التنفيذ الفعلي والتطبيقي لكافة مكونات شؤون الموظفين وفنيي الورشة والرواتب والجزاءات والإشعارات الفورية للإدارة في مركز الحسيني.

---

## 📑 فهرس مراحل تنفيذ نظام شؤون الموظفين (HR Phases)

| المرحلة | اسم الملف | المحتوى التقني بالتفصيل |
|---|---|---|
| **المرحلة 1** | [HR_PHASE_01_FORM_REQUESTS.md](file:///d:/Projects/Al-Husseini/plans/hr_system/HR_PHASE_01_FORM_REQUESTS.md) | طلبات التحقق والمدخلات (`StoreEmployeeRequest`, `RecordPunchRequest`, `StoreLeaveRequest`, `StoreDeductionRequest`, `GeneratePayrollRequest`). |
| **المرحلة 2** | [HR_PHASE_02_SERVICES_LOGIC.md](file:///d:/Projects/Al-Husseini/plans/hr_system/HR_PHASE_02_SERVICES_LOGIC.md) | المحركات الرياضية (`AttendanceService`, `PayrollService`, `DeductionService`) مع المعادلات المحاسبية الصارمة. |
| **المرحلة 3** | [HR_PHASE_03_NOTIFICATIONS.md](file:///d:/Projects/Al-Husseini/plans/hr_system/HR_PHASE_03_NOTIFICATIONS.md) | إشعارات الإدارة في قاعدة البيانات والـ Realtime (`EmployeeLateNotification`, `LeaveRequestNotification`, `PayrollReadyNotification`). |
| **المرحلة 4** | [HR_PHASE_04_CONTROLLERS_ROUTES.md](file:///d:/Projects/Al-Husseini/plans/hr_system/HR_PHASE_04_CONTROLLERS_ROUTES.md) | وحدات التحكم والمسارات مع الصلاحيات وحماية العمليات (`EmployeeController`, `AttendanceController`, `PayrollController`). |
| **المرحلة 5** | [HR_PHASE_05_UI_BLADE_BINDING.md](file:///d:/Projects/Al-Husseini/plans/hr_system/HR_PHASE_05_UI_BLADE_BINDING.md) | ربط قوالب Blade وإلغاء `hr-store.js` و `localStorage` وربط جرس التنبيهات في الـ Topbar. |

---

## 🛠️ تدفق العمليات الإدارية (Business Workflow)

```mermaid
sequenceDiagram
    autonumber
    actor Tech as فني الورشة / الموظف
    participant Bio as جهاز البصمة ZKTeco
    participant AttService as خدمة الحضور (AttendanceService)
    participant Notif as إشعارات الإدارة (Admin Notifications)
    actor Admin as المشرف العام / مدير الفرع
    participant PayService as محرك الرواتب (PayrollService)

    Tech->>Bio: تسجيل بصمة الحضور
    Bio->>AttService: إرسال بيانات البصمة لحظياً
    AttService->>AttService: فحص بداية الشفت + سماحية 15 دقيقة
    alt متأخر أكثر من 15 دقيقة
        AttService->>Notif: إرسال إشعار تأخير فوري
        Notif->>Admin: تنبيه صوتي + إشعار بالـ Topbar
        AttService->>AttService: إنشاء مقترح جزاء مالي تلقائي
    end

    Note over Admin,PayService: نهاية الشهر الميلادي
    Admin->>PayService: طلب توليد مسير الرواتب
    PayService->>PayService: الراتب الأساسي + البدلات + الأوفرتايم + عمولات البطاريات - الغياب - الجزاءات
    PayService->>Admin: عرض مسودة المسير للاعتماد
    Admin->>PayService: اعتماد المسير وصرف الرواتب
```
