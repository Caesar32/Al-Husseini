# المرحلة الثامنة: البث اللحظي والتنبيهات الحية (Real-Time WebSockets & Laravel Reverb)

> **طبيعة الملف:** وثيقة تقنية لإعداد نظام التنبيهات والبث الفوري للأحداث (Real-Time Event Broadcasting) باستخدام محرك **Laravel Reverb** و **Laravel Echo** لتحديث لوحات المتابعة وشاشات الحضور والمخزون دون الحاجة لإعادة تحميل الصفحة.

---

## 1. تثبيت وتهيئة Laravel Reverb

```bash
composer require laravel/reverb
php artisan reverb:install
```

### إعدادات البيئة في ملف `.env`:
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=alhusseini_app
REVERB_APP_KEY=alhusseini_key
REVERB_APP_SECRET=alhusseini_secret
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## 2. فئات أحداث البث (Broadcast Events)

### 1. حدث تأخر الموظف عن الشفت `app/Events/EmployeeLateEvent.php`
```php
<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeLateEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Attendance $attendance) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('branch.' . $this->attendance->employee->branch_id),
            new PrivateChannel('hr-dashboard'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'employee_name' => $this->attendance->employee->full_name,
            'job_title' => $this->attendance->employee->jobTitle->title,
            'late_minutes' => $this->attendance->late_minutes,
            'check_in_time' => $this->attendance->check_in->format('H:i A'),
            'work_date' => $this->attendance->work_date->toDateString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'employee.late';
    }
}
```

---

### 2. حدث نقص مخزون البطاريات `app/Events/LowStockEvent.php`
```php
<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Product $product) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('inventory-alerts'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'brand' => $this->product->brand,
            'current_stock' => $this->product->current_stock,
            'reorder_threshold' => $this->product->reorder_threshold,
        ];
    }

    public function broadcastAs(): string
    {
        return 'product.low_stock';
    }
}
```

---

## 3. تصاريح القنوات في `routes/channels.php`

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('branch.{branchId}', function ($user, $branchId) {
    return (int) $user->branch_id === (int) $branchId || $user->is_super_admin;
});

Broadcast::channel('hr-dashboard', function ($user) {
    return $user->can('view-hr-dashboard') || $user->is_super_admin;
});

Broadcast::channel('inventory-alerts', function ($user) {
    return $user->can('manage-inventory') || $user->is_super_admin;
});
```

---

## 4. استماع العميل اللحظي (Echo Client Listener)

تضمين الكود في الواجهة الرئيسية عبر `resources/views/admin/layouts/partials/vendor-scripts.blade.php`:

```javascript
// الاستماع لتنبيهات تأخر الموظفين لحظياً
if (window.Echo) {
    window.Echo.private('hr-dashboard')
        .listen('.employee.late', (e) => {
            // تشغيل تنبيه صوتي خفيف
            const audio = new Audio('/assets/sounds/notification.mp3');
            audio.play().catch(() => {});

            // عرض Toast إشعار مباشر في الزاوية
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'warning',
                title: `تنبيه تأخير: ${e.employee_name}`,
                text: `تأخر ${e.late_minutes} دقيقة (الحضور: ${e.check_in_time})`,
                showConfirmButton: false,
                timer: 6000,
                timerProgressBar: true
            });

            // تحديث عدادات الحضور إذا كانت الصفحة مفتوحة
            if (typeof refreshAttendanceCounters === 'function') {
                refreshAttendanceCounters();
            }
        });

    window.Echo.private('inventory-alerts')
        .listen('.product.low_stock', (e) => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'تنبيه مخزون حرج!',
                text: `أوشك رصيد ${e.product_name} على النفاد (المتبقي: ${e.current_stock})`,
                showConfirmButton: false,
                timer: 7000
            });
        });
}
```
