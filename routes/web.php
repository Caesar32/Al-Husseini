<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\LeaveController;
use App\Http\Controllers\Hr\DeductionController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Hr\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\PurchaseInvoiceController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\SalesInvoiceController;
use App\Http\Controllers\Admin\WarrantyController;
use App\Http\Controllers\Admin\ScrapInventoryController;
use App\Http\Controllers\Admin\CreditCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SystemDiagnosticController;
use App\Http\Controllers\Auth\LockScreenController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (!\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('admin.login');
    }
    /** @var \App\Models\User $user */
    $user = \Illuminate\Support\Facades\Auth::user();
    if ($user->hasRole('cashier') || (!$user->can('dashboard.view') && $user->can('pos.access'))) {
        return redirect()->route('admin.pos.index');
    }
    return redirect()->route('admin.dashboard');
});

// تبديل اللغة (Language Switcher)
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back();
})->name('switch-lang');

// تجديد رمز الحماية وإبقاء الجلسة نشطة لمنع انتهاء الجلسة أثناء العمل طوال اليوم (CSRF & Session Keep-Alive)
Route::get('/refresh-csrf', function () {
    return response()->json([
        'csrf_token' => csrf_token(),
        'status' => 'active',
    ]);
})->name('refresh_csrf');

// مسارات لوحة التحكم (Admin Panel Routes)
Route::prefix('admin')->name('admin.')->group(function () {

    // مسارات المصادقة للضيوف (Guest Authentication)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        Route::get('/register', function () {
            return redirect()->route('admin.login')->with('info', 'تسجيل الحسابات مخصص للإدارة العامة فقط. تواصل مع المشرف العام للحصول على بيانات حسابك.');
        })->name('register');
    });

    // تسجيل الخروج للمستخدمين المسجلين
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    // مسارات لوحة التحكم المحمية بالكامل (Authenticated Admin Routes)
    Route::middleware('auth')->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard')->middleware('can:dashboard.view');

        // البحث الفوري الشامل (Global Spotlight Search)
        Route::get('/global-search', [SearchController::class, 'globalSearch'])->name('global_search');

        // قفل الشاشة (Lock Screen)
        Route::get('/lockscreen', [LockScreenController::class, 'show'])->name('lockscreen');
        Route::post('/lockscreen/unlock', [LockScreenController::class, 'unlock'])->name('lockscreen.unlock');

        // الملف الشخصي (User Profile)
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::put('/profile/info', [ProfileController::class, 'updateInfo'])->name('profile.info');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::get('/profile/avatar/{user}', [ProfileController::class, 'showAvatar'])->name('profile.avatar.show');

        // إعدادات النظام والمنشأة (System Settings)
        Route::get('/settings', [SettingController::class, 'index'])->name('settings')->middleware('can:settings.manage');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update')->middleware('can:settings.manage');

        // فحص وتشخيص النظام الحي والمحاكاة الشاملة (System Diagnostics & Live Simulation)
        Route::get('/system-diagnostics', [SystemDiagnosticController::class, 'index'])->name('diagnostics.index')->middleware('can:settings.manage');
        Route::post('/system-diagnostics/audit', [SystemDiagnosticController::class, 'runAudit'])->name('diagnostics.run_audit')->middleware('can:settings.manage');
        Route::post('/system-diagnostics/simulate', [SystemDiagnosticController::class, 'runSimulation'])->name('diagnostics.run_simulation')->middleware('can:settings.manage');

        // إدارة الأدوار وتحديد الصلاحيات (Roles & Permissions)
        Route::resource('roles', RoleController::class)->except(['show'])->middleware('can:roles.manage');

        // إدارة المستخدمين وحسابات الموظفين (User Accounts & Role Assignments)
        Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('can:users.manage');
        Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('can:users.manage');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('can:users.manage');
        Route::post('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role')->middleware('can:users.manage');
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status')->middleware('can:users.manage');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password')->middleware('can:users.manage');

        Route::get('/starter', function () {
            return view('admin.starter');
        })->name('starter');

        Route::get('/404', function () {
            return view('admin.errors.404');
        })->name('error.404');

        // إدارة شؤون الموظفين والعمليات (HR Management)
        Route::prefix('hr')->name('hr.')->group(function () {
            // الموظفون
            Route::get('/employees', [EmployeeController::class, 'index'])->name('employees')->middleware('can:employees.view');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store')->middleware('can:employees.create');
            Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show')->middleware('can:employees.view');
            Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->middleware('can:employees.edit');
            Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->middleware('can:employees.delete');

            // الحضور والانصراف
            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance')->middleware('can:attendance.view');
            Route::post('/attendance/punch', [AttendanceController::class, 'recordManual'])->name('attendance.punch')->middleware('can:attendance.manual_punch');
            Route::post('/attendance/mark-absent', [AttendanceController::class, 'markAbsent'])->name('attendance.mark_absent')->middleware('can:attendance.manual_punch');

            // الإجازات
            Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index')->middleware('can:leaves.manage');
            Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store')->middleware('can:leaves.manage');
            Route::post('/leaves/{leave}/status', [LeaveController::class, 'updateStatus'])->name('leaves.status')->middleware('can:leaves.manage');

            // الجزاءات والخصومات
            Route::get('/deductions', [DeductionController::class, 'index'])->name('deductions.index')->middleware('can:deductions.manage');
            Route::post('/deductions', [DeductionController::class, 'store'])->name('deductions.store')->middleware('can:deductions.manage');
            Route::post('/deductions/{deduction}/status', [DeductionController::class, 'updateStatus'])->name('deductions.status')->middleware('can:deductions.manage');

            // مسيرات الرواتب
            Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll')->middleware('can:payroll.generate');
            Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate')->middleware('can:payroll.generate');
            Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show')->middleware('can:payroll.generate');
            Route::post('/payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve')->middleware('can:payroll.approve');
            Route::post('/payroll/{payroll}/disburse', [PayrollController::class, 'disburse'])->name('payroll.disburse')->middleware('can:payroll.disburse');

            // ── تقارير الموارد البشرية (Phase 8 / FE-03: بيانات حقيقية من قاعدة البيانات) ──
            Route::middleware('can:reports.hr')->group(function () {
                Route::get('/reports', [\App\Http\Controllers\Hr\HrReportController::class, 'index'])->name('reports');
                Route::get('/reports/daily', [\App\Http\Controllers\Hr\HrReportController::class, 'daily'])->name('reports.daily');
                Route::get('/reports/monthly', [\App\Http\Controllers\Hr\HrReportController::class, 'monthly'])->name('reports.monthly');
                Route::get('/reports/range', [\App\Http\Controllers\Hr\HrReportController::class, 'range'])->name('reports.range');
                Route::get('/reports/employees/{employee}', [\App\Http\Controllers\Hr\HrReportController::class, 'employee'])->name('reports.employee');
            });
            // ── نهاية تقارير الموارد البشرية ──

            // إشعارات الإدارة
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('can:notifications.view');
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read')->middleware('can:notifications.view');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll')->middleware('can:notifications.view');
        });

        // قطاع الموردين والتوريدات
        // Supplier creation happens through the modal on the index page (there is no create view).
        Route::resource('suppliers', SupplierController::class)
            ->except(['create'])
            ->middlewareFor(['index', 'show'], 'can:suppliers.view')
            ->middlewareFor('store', 'can:suppliers.create')
            ->middlewareFor(['edit', 'update'], 'can:suppliers.edit')
            ->middlewareFor('destroy', 'can:suppliers.delete');
        Route::get('suppliers/{supplier}/ledger', [SupplierController::class, 'ledger'])->name('suppliers.ledger')->middleware('can:suppliers.view');
        Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'recordPayment'])->name('suppliers.payments')->middleware('can:purchases.settle_payment');

        // فواتير المشتريات والتوريد
        Route::resource('purchases', PurchaseInvoiceController::class)
            ->except(['edit', 'update', 'destroy'])
            ->middlewareFor(['index', 'show'], 'can:purchases.view')
            ->middlewareFor(['create', 'store'], 'can:purchases.create');
        Route::get('purchases/{purchase}/print', [PurchaseInvoiceController::class, 'print'])->name('purchases.print')->middleware('can:purchases.view');

        // نقطة البيع ومبيعات الكاشير
        Route::get('pos', [PosController::class, 'index'])->name('pos.index')->middleware('can:pos.access');
        Route::post('pos', [PosController::class, 'store'])->name('pos.store')->middleware('can:pos.access');
        Route::get('pos/{invoice}/receipt', [PosController::class, 'receipt'])->name('pos.receipt')->middleware('can:invoices.print');
        Route::get('pos/{invoice}/warranty', [PosController::class, 'warrantyCert'])->name('pos.warranty_cert')->middleware('can:warranties.view');

        // فواتير المبيعات وسجل العمليات
        Route::get('invoices', [SalesInvoiceController::class, 'index'])->name('invoices.index')->middleware('can:invoices.view');
        Route::get('invoices/{invoice}', [SalesInvoiceController::class, 'show'])->name('invoices.show')->middleware('can:invoices.view');
        Route::post('invoices/{invoice}/return', [SalesInvoiceController::class, 'processReturn'])->name('invoices.return')->middleware('can:invoices.cancel');

        // الضمانات والبطاريات التالفة
        Route::get('warranties', [WarrantyController::class, 'index'])->name('warranties.index')->middleware('can:warranties.view');
        Route::get('warranties/verify', [WarrantyController::class, 'verify'])->name('warranties.verify')->middleware('can:warranties.view');
        Route::post('warranties/claims', [WarrantyController::class, 'storeClaim'])->name('warranties.claims.store')->middleware('can:warranties.claim');
        Route::post('warranties/claims/{claim}/settle', [WarrantyController::class, 'settleSupplier'])->name('warranties.claims.settle')->middleware('can:warranties.approve_replace');

        // مخزن الكهنة وتجارة الرصاص
        Route::get('scrap-inventory', [ScrapInventoryController::class, 'index'])->name('scrap.index')->middleware('can:scrap.view');
        Route::post('scrap-inventory/sell-batch', [ScrapInventoryController::class, 'sellBatch'])->name('scrap.sell_batch')->middleware('can:scrap.transfer');
        Route::put('scrap-inventory/tiers', [ScrapInventoryController::class, 'updateTiers'])->name('scrap.update_tiers')->middleware('can:settings.manage');

        // إدارة عملاء الآجل والمديونيات
        Route::get('credit', [CreditCustomerController::class, 'index'])->name('credit.index')->middleware('can:credit.view');
        Route::post('credit/settle', [CreditCustomerController::class, 'settlePayment'])->name('credit.settle')->middleware('can:credit.settle');
        Route::get('credit/{customer}/statement', [CreditCustomerController::class, 'statement'])->name('credit.statement')->middleware('can:credit.view');

        // ─── BEGIN Phase 4: credit collections history (server-backed) ──────────────────────
        Route::get('credit/payments', [CreditCustomerController::class, 'payments'])->name('credit.payments')->middleware('can:credit.view');
        // ─── END Phase 4 ─────────────────────────────────────────────────────────────────────

        // التوافق مع المسارات السابقة (Backwards Compatibility Route Aliases)
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::get('/pos', [PosController::class, 'index'])->name('pos')->middleware('can:pos.access');
            Route::get('/invoices', [SalesInvoiceController::class, 'index'])->name('invoices')->middleware('can:invoices.view');
            Route::get('/credit', [CreditCustomerController::class, 'index'])->name('credit')->middleware('can:credit.view');
            Route::post('/credit/settle', [CreditCustomerController::class, 'settlePayment'])->name('credit.settle')->middleware('can:credit.settle');
            Route::get('/credit/{customer}/statement', [CreditCustomerController::class, 'statement'])->name('credit.statement')->middleware('can:credit.view');
            Route::get('/customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers')->middleware('can:customers.view');
            Route::get('/products', [\App\Http\Controllers\Admin\ProductController::class, 'index'])->name('products')->middleware('can:products.view');
        });

        // ─── BEGIN Phase 3: catalog backend (products / customers / vehicles) ───────────────
        Route::get('products/search', [\App\Http\Controllers\Admin\ProductController::class, 'search'])->name('products.search')->middleware('can:products.view');
        Route::post('products', [\App\Http\Controllers\Admin\ProductController::class, 'store'])->name('products.store')->middleware('can:products.create');
        Route::put('products/{product}', [\App\Http\Controllers\Admin\ProductController::class, 'update'])->name('products.update')->middleware('can:products.edit');
        Route::delete('products/{product}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy'])->name('products.destroy')->middleware('can:products.delete');

        Route::get('customers/search', [\App\Http\Controllers\Admin\CustomerController::class, 'search'])->name('customers.search')->middleware('can:customers.view');
        Route::post('customers', [\App\Http\Controllers\Admin\CustomerController::class, 'store'])->name('customers.store')->middleware('can:customers.create');
        Route::get('customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show')->middleware('can:customers.view');
        Route::put('customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'update'])->name('customers.update')->middleware('can:customers.edit');
        Route::delete('customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('can:customers.delete');
        Route::post('customers/{customer}/vehicles', [\App\Http\Controllers\Admin\CustomerController::class, 'storeVehicle'])->name('customers.vehicles.store')->middleware('can:customers.edit');
        Route::put('customers/{customer}/vehicles/{vehicle}', [\App\Http\Controllers\Admin\CustomerController::class, 'updateVehicle'])->name('customers.vehicles.update')->middleware('can:customers.edit')->scopeBindings();
        Route::delete('customers/{customer}/vehicles/{vehicle}', [\App\Http\Controllers\Admin\CustomerController::class, 'destroyVehicle'])->name('customers.vehicles.destroy')->middleware('can:customers.edit')->scopeBindings();
        // ─── END Phase 3 ─────────────────────────────────────────────────────────────────────
    });
});
