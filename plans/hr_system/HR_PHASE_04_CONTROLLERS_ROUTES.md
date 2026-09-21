# المرحلة 4 لنظام شؤون الموظفين: وحدات التحكم والمسارات
### (HR Phase 04: Controllers, Middlewares & Routes Architecture)

> **طبيعة الملف:** وثيقة تقنية تحدد الـ Controllers ومسارات الـ Routing لشؤون الموظفين مع حمايتها بصلاحيات Spatie.

---

## 1. وحدات التحكم (Controllers):
- `App\Http\Controllers\Hr\EmployeeController`: إدارة الموظفين، كروت الرواتب، وتاريخ الترقية.
- `App\Http\Controllers\Hr\AttendanceController`: جدول الحضور اليومي، تسجيل بصمة يدوية، وإحصائيات الحضور والغياب.
- `App\Http\Controllers\Hr\LeaveController`: استعراض طلبات الإجازات، والموافقة أو الرفض مع الملاحظات.
- `App\Http\Controllers\Hr\DeductionController`: إدارة الجزاءات، إلغاء جزاء، وتطبيق الجزاء في الراتب.
- `App\Http\Controllers\Hr\PayrollController`: توليد المسير الشهري، المراجعة، الاعتماد، وطباعة كشف المرتب (Payslip).
- `App\Http\Controllers\Hr\NotificationController`: جلب إشعارات الإدارة، ووضع علامة "تمت القراءة".

---

## 2. مصفوفة المسارات المحمية (Route Groups):
تجمع تحت بادئة `admin/hr` ومحمية بـ `auth` وصلاحيات `employees.view`, `attendance.view`, `payroll.generate`, إلخ.
