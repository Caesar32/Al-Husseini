# المرحلة الخامسة: وحدات التحكم ومسارات النظام (Controllers & Route Definitions)

> **طبيعة الملف:** وثيقة تقنية لمطوري الواجهات الخلفية تحدد كود الـ Controllers ومسارات الـ Routing مجمعة بالميدلوير (Middleware) والحمايات وتحديد معدل الطلبات (Throttling).

---

## 1. شجرة وحدات التحكم (Controllers Structure)

```
app/Http/Controllers/
├── Pos/
│   ├── PosController.php
│   └── CreditCustomerController.php
├── Hr/
│   ├── EmployeeController.php
│   ├── AttendanceController.php
│   ├── DeductionController.php
│   └── PayrollController.php
├── Api/
│   └── ZktecoWebhookController.php
└── Inventory/
    ├── ProductController.php
    └── WarrantyController.php
```

---

## 2. وحدات التحكم لنقاط البيع (POS Controllers)

### `app/Http/Controllers/Pos/PosController.php`
```php
<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\CreatePosInvoiceRequest;
use App\Services\Pos\PosOrderService;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(protected PosOrderService $posOrderService) {}

    public function index(): View
    {
        $technicians = Employee::technicians()->active()->get();
        $recentInvoices = Invoice::with(['customer', 'items.product'])->latest()->take(10)->get();

        return view('admin.pos.index', compact('technicians', 'recentInvoices'));
    }

    public function searchProduct(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $products = Product::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                  ->orWhere('sku', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%")
                  ->orWhere('brand', 'like', "%{$query}%");
            })
            ->take(15)
            ->get();

        return response()->json($products);
    }

    public function searchCustomer(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $customers = Customer::with('vehicles')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('phone', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            })
            ->take(10)
            ->get();

        return response()->json($customers);
    }

    public function checkout(CreatePosInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->posOrderService->createInvoice(
            $request->validated(),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الفاتورة بنجاح وتحديث المخزون والضمان.',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'print_url' => route('pos.invoice.print', $invoice->id),
        ]);
    }

    public function printThermal(Invoice $invoice): View
    {
        $invoice->load(['customer', 'customerVehicle', 'items.product', 'cashier', 'technician']);
        return view('admin.pos.receipt_80mm', compact('invoice'));
    }
}
```

---

## 3. وحدات التحكم لشؤون الموظفين والرواتب (HR & Payroll Controllers)

### `app/Http/Controllers/Hr/PayrollController.php`
```php
<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\PayrollService;
use App\Models\Payroll;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payrollService) {}

    public function index(): View
    {
        $branches = Branch::where('is_active', true)->get();
        $payrolls = Payroll::with(['branch', 'approvedBy'])->latest()->paginate(15);

        return view('admin.hr.payroll.index', compact('branches', 'payrolls'));
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'year' => ['required', 'integer', 'min:2025', 'max:2035'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $payroll = $this->payrollService->generateMonthlyPayroll(
                $request->branch_id,
                $request->year,
                $request->month
            );

            return response()->json([
                'success' => true,
                'message' => 'تم احتساب مسير الرواتب بنجاح.',
                'payroll_id' => $payroll->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Payroll $payroll): View
    {
        $payroll->load(['branch', 'items.employee.jobTitle', 'approvedBy']);
        return view('admin.hr.payroll.show', compact('payroll'));
    }

    public function approve(Payroll $payroll): JsonResponse
    {
        if ($payroll->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'المسير معتمد مسبقاً.'], 422);
        }

        $payroll->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'تم اعتماد مسير الرواتب رسمياً.']);
    }
}
```

---

## 4. وحدة تحكم استقبال نبضات البصمة (Biometric Webhook)

### `app/Http/Controllers/Api/ZktecoWebhookController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RecordPunchRequest;
use App\Services\Hr\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ZktecoWebhookController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService) {}

    public function handlePunch(RecordPunchRequest $request): JsonResponse
    {
        try {
            $attendance = $this->attendanceService->recordPunch(
                $request->pin,
                Carbon::parse($request->timestamp),
                (int) $request->punch_state
            );

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Punch recorded successfully',
                'attendance_id' => $attendance->id,
            ]);
        } catch (\Exception $e) {
            Log::error("ZKTeco Webhook Error: {$e->getMessage()}", $request->all());
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
```

---

## 5. تعريفات المسارات الكاملة (Routes Architecture)

### `routes/web.php`
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\CreditCustomerController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\DeductionController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\WarrantyController;

Route::redirect('/', '/admin/dashboard');

Route::middleware(['auth'])->prefix('admin')->group(function () {
    // لوحة التحكم العامة
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

    // مسارات نقطة البيع (POS)
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/search-product', [PosController::class, 'searchProduct'])->name('search.product');
        Route::get('/search-customer', [PosController::class, 'searchCustomer'])->name('search.customer');
        Route::post('/checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::get('/invoice/{invoice}/print', [PosController::class, 'printThermal'])->name('invoice.print');

        // إدارة الآجل والمديونيات
        Route::get('/credit-customers', [CreditCustomerController::class, 'index'])->name('credit.index');
        Route::get('/credit-customers/{customer}/ledger', [CreditCustomerController::class, 'statement'])->name('credit.statement');
        Route::post('/credit-customers/settle', [CreditCustomerController::class, 'settlePayment'])->name('credit.settle');
    });

    // مسارات شؤون الموظفين والورشة (HR & Workshop Management)
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::resource('employees', EmployeeController::class);
        
        // الحضور والانصراف
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('attendance/manual-punch', [AttendanceController::class, 'storeManual'])->name('attendance.manual');
        Route::get('attendance/today-status', [AttendanceController::class, 'todayStatusJson'])->name('attendance.today');

        // الجزاءات والخصومات
        Route::resource('deductions', DeductionController::class);

        // الرواتب ومسيرات الأجور
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
    });

    // مسارات المخزون والضمانات
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('products', ProductController::class);
        Route::get('warranties/check/{serial}', [WarrantyController::class, 'checkSerial'])->name('warranties.check');
        Route::post('warranties/claim', [WarrantyController::class, 'submitClaim'])->name('warranties.claim');
    });
});
```

### `routes/api.php`
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ZktecoWebhookController;

// مسار استقبال بيانات أجهزة البصمة محمي بـ Throttle والـ Webhook Secret
Route::prefix('v1/hardware')->middleware(['throttle:60,1'])->group(function () {
    Route::post('/zkteco/punch', [ZktecoWebhookController::class, 'handlePunch']);
});
```
