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
use App\Http\Controllers\Auth\LockScreenController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('admin.login');
});

// تبديل اللغة (Language Switcher)
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back();
})->name('switch-lang');

// مسارات لوحة التحكم (Admin Panel Routes)
Route::prefix('admin')->name('admin.')->group(function () {

    // مسارات المصادقة للضيوف (Guest Authentication)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
        Route::get('/register', function () {
            return view('admin.auth.register');
        })->name('register');
    });

    // تسجيل الخروج للمستخدمين المسجلين
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    // مسارات لوحة التحكم المحمية بالكامل (Authenticated Admin Routes)
    Route::middleware('auth')->group(function () {

        Route::get('/', function () {
            return view('admin.dashboard');
        })->name('dashboard')->middleware('can:dashboard.view');

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

        // إعدادات النظام والمنشأة (System Settings)
        Route::get('/settings', [SettingController::class, 'index'])->name('settings')->middleware('can:settings.manage');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update')->middleware('can:settings.manage');

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

            // تقارير الموارد البشرية
            Route::get('/reports', function () {
                return view('admin.hr.reports');
            })->name('reports')->middleware('can:reports.hr');

            // إشعارات الإدارة
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index')->middleware('can:notifications.view');
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read')->middleware('can:notifications.view');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll')->middleware('can:notifications.view');
        });

        // المبيعات ونقاط البيع والعملاء والمنتجات (Sales & Operations)
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::get('/pos', function () {
                return view('admin.sales.pos');
            })->name('pos')->middleware('can:pos.access');

            Route::get('/invoices', function () {
                return view('admin.sales.invoices');
            })->name('invoices')->middleware('can:invoices.view');

            Route::get('/credit', function () {
                return view('admin.sales.credit');
            })->name('credit')->middleware('can:credit.view');

            Route::get('/customers', function () {
                return view('admin.sales.customers');
            })->name('customers')->middleware('can:customers.view');

            Route::get('/products', function () {
                return view('admin.sales.products');
            })->name('products')->middleware('can:products.view');
        });
    });
});
