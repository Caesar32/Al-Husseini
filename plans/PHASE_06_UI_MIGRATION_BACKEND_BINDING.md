# المرحلة السادسة: ربط الواجهات بالخادم وإلغاء LocalStorage (UI Frontend-to-Backend Binding)

> **طبيعة الملف:** دليل تقني خطوة بخطوة لتحويل الواجهات الحالية في قوالب Blade من الاعتماد على التخزين المحلي الوهمي (`localStorage` عبر `hr-store.js` و `sales-store.js`) إلى الاستهلاك المباشر لنقاط الـ API و الـ Controller Endpoints بواسطة `Axios` وتمرير الـ CSRF Tokens.

---

## 1. خطة الاستبدال (Migration Matrix)

| الملف الحالي | الحالة الراهنة | التحويل المطلوب |
|---|---|---|
| `public/assets/js/hr-store.js` | يحفظ الموظفين والغياب في `localStorage` | **حذف / تحييد** واستبداله بـ `HrApiClient.js` يرسل طلبات Axios للـ Controller |
| `public/assets/js/sales-store.js` | يحفظ فواتير الكاش في `localStorage` | **حذف / تحييد** واستبداله بـ `PosApiClient.js` يرسل طلبات لـ `pos.checkout` |
| `resources/views/admin/hr/employees.blade.php` | يرسم الجدول بـ JS Loop من المتصفح | تحويله لـ Server-Side Blade Loop مع `@foreach($employees as $employee)` ودعم الترقيم `->links()` |
| `resources/views/admin/pos/index.blade.php` | يخصم وهمياً من ذاكرة المتصفح | إرسال الحمولة (Payload) إلى `route('pos.checkout')` وطباعة إيصال الفاتورة |

---

## 2. تهيئة Axios و الـ CSRF Token عالمياً

إنشاء أو تحديث `resources/js/bootstrap.js` أو ملف مخصص `public/assets/js/api-client.js`:

```javascript
// public/assets/js/api-client.js
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const tokenMeta = document.querySelector('meta[name="csrf-token"]');
if (tokenMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Interceptor لمعالجة الأخطاء وظهور تنبيهات SweetAlert2 تلقائياً
window.axios.interceptors.response.use(
    response => response,
    error => {
        let errorMsg = 'حدث خطأ غير متوقع أثناء معالجة الطلب.';
        if (error.response && error.response.data) {
            if (error.response.data.message) {
                errorMsg = error.response.data.message;
            } else if (error.response.data.errors) {
                const firstKey = Object.keys(error.response.data.errors)[0];
                errorMsg = error.response.data.errors[firstKey][0];
            }
        }
        Swal.fire({
            icon: 'error',
            title: 'تنبيه النظام',
            text: errorMsg,
            confirmButtonColor: '#003366',
            confirmButtonText: 'حسناً'
        });
        return Promise.reject(error);
    }
);
```

---

## 3. تحويل واجهة الموظفين (Employees View Refactoring)

تحديث `resources/views/admin/hr/employees.blade.php` ليعتمد على محرك Blade بدلاً من بناء الجدول بالـ DOM:

```blade
@extends('admin.layouts.master')
@section('title', 'إدارة الموظفين والفنيين')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="card-title mb-0 fw-bold text-navy">
            <i class="ri-user-settings-line me-1 text-gold"></i> سجل الموظفين وفنيي البطاريات
        </h5>
        <button type="button" class="btn btn-primary bg-navy border-0" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
            <i class="ri-user-add-line me-1"></i> إضافة موظف جديد
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>الكود</th>
                        <th>الاسم الكامل</th>
                        <th>الوظيفة</th>
                        <th>الفرع</th>
                        <th>الراتب الأساسي</th>
                        <th>ساعات الشفت</th>
                        <th>معرف البصمة (PIN)</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                    <tr>
                        <td class="fw-bold">{{ $employee->employee_code }}</td>
                        <td>{{ $employee->full_name }}</td>
                        <td><span class="badge bg-soft-info text-info">{{ $employee->jobTitle->title }}</span></td>
                        <td>{{ $employee->branch->name }}</td>
                        <td class="text-success fw-bold">{{ number_format($employee->currentSalary?->basic_salary ?? 0, 2) }} ج.م</td>
                        <td>{{ substr($employee->shift_start_time, 0, 5) }} - {{ substr($employee->shift_end_time, 0, 5) }}</td>
                        <td><code class="text-dark">{{ $employee->zkteco_pin ?? 'غير مربوط' }}</code></td>
                        <td>
                            @if($employee->status === 'active')
                                <span class="badge bg-success">نشط</span>
                            @else
                                <span class="badge bg-secondary">{{ $employee->status }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('hr.employees.edit', $employee->id) }}" class="btn btn-sm btn-outline-primary"><i class="ri-edit-line"></i></a>
                            <button class="btn btn-sm btn-outline-danger delete-emp-btn" data-id="{{ $employee->id }}"><i class="ri-delete-bin-line"></i></button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">لا يوجد موظفون مسجلون في هذا الفرع.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $employees->links() }}
        </div>
    </div>
</div>
@endsection
```

---

## 4. تحويل منطق شاشة الكاشير ونقطة البيع (POS UI Integration)

كود جافاسكريبت `public/assets/js/pos-checkout.js` لربط السلة بالفاتورة عبر الـ API:

```javascript
// استدعاء Checkout الفاتورة
async function processInvoiceCheckout(orderPayload) {
    Swal.fire({
        title: 'جاري إتمام البيع...',
        html: 'يتم الآن خصم المخزون وإصدار شهادة الضمان',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const response = await axios.post('/admin/pos/checkout', orderPayload);
        const data = response.data;

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'تم إصدار الفاتورة!',
                text: `رقم الفاتورة: ${data.invoice_number}`,
                showCancelButton: true,
                confirmButtonText: 'طباعة الإيصال (80mm)',
                cancelButtonText: 'فاتورة جديدة',
                confirmButtonColor: '#003366',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open(data.print_url, '_blank', 'width=400,height=600');
                }
                // إعادة تصفير سلة نقطة البيع
                resetPosCart();
            });
        }
    } catch (error) {
        console.error('POS Checkout Failed:', error);
    }
}
```
