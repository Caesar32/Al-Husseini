<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Language Switcher Route
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['ar', 'en'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back();
})->name('switch-lang');

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::get('/starter', function () {
        return view('admin.starter');
    })->name('starter');

    Route::get('/login', function () {
        return view('admin.auth.login');
    })->name('login');

    Route::get('/register', function () {
        return view('admin.auth.register');
    })->name('register');

    Route::get('/404', function () {
        return view('admin.errors.404');
    })->name('error.404');

    // HR Management Routes
    Route::prefix('hr')->name('hr.')->group(function () {
        // الموظفون
        Route::get('/employees', [\App\Http\Controllers\Hr\EmployeeController::class, 'index'])->name('employees');
        Route::post('/employees', [\App\Http\Controllers\Hr\EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [\App\Http\Controllers\Hr\EmployeeController::class, 'show'])->name('employees.show');
        Route::put('/employees/{employee}', [\App\Http\Controllers\Hr\EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [\App\Http\Controllers\Hr\EmployeeController::class, 'destroy'])->name('employees.destroy');

        // الحضور والانصراف
        Route::get('/attendance', [\App\Http\Controllers\Hr\AttendanceController::class, 'index'])->name('attendance');
        Route::post('/attendance/punch', [\App\Http\Controllers\Hr\AttendanceController::class, 'recordManual'])->name('attendance.punch');

        // الإجازات
        Route::get('/leaves', [\App\Http\Controllers\Hr\LeaveController::class, 'index'])->name('leaves.index');
        Route::post('/leaves', [\App\Http\Controllers\Hr\LeaveController::class, 'store'])->name('leaves.store');
        Route::post('/leaves/{leave}/status', [\App\Http\Controllers\Hr\LeaveController::class, 'updateStatus'])->name('leaves.status');

        // الجزاءات والخصومات
        Route::get('/deductions', [\App\Http\Controllers\Hr\DeductionController::class, 'index'])->name('deductions.index');
        Route::post('/deductions', [\App\Http\Controllers\Hr\DeductionController::class, 'store'])->name('deductions.store');
        Route::post('/deductions/{deduction}/status', [\App\Http\Controllers\Hr\DeductionController::class, 'updateStatus'])->name('deductions.status');

        // مسيرات الرواتب
        Route::get('/payroll', [\App\Http\Controllers\Hr\PayrollController::class, 'index'])->name('payroll');
        Route::post('/payroll/generate', [\App\Http\Controllers\Hr\PayrollController::class, 'generate'])->name('payroll.generate');
        Route::get('/payroll/{payroll}', [\App\Http\Controllers\Hr\PayrollController::class, 'show'])->name('payroll.show');
        Route::post('/payroll/{payroll}/approve', [\App\Http\Controllers\Hr\PayrollController::class, 'approve'])->name('payroll.approve');
        Route::post('/payroll/{payroll}/disburse', [\App\Http\Controllers\Hr\PayrollController::class, 'disburse'])->name('payroll.disburse');

        // تقارير الموارد البشرية
        Route::get('/reports', function () {
            return view('admin.hr.reports');
        })->name('reports');

        // إشعارات الإدارة
        Route::get('/notifications', [\App\Http\Controllers\Hr\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Hr\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [\App\Http\Controllers\Hr\NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    });

    // Sales, Customers, Invoices, Products & Credit (الآجل) Routes
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/pos', function () {
            return view('admin.sales.pos');
        })->name('pos');

        Route::get('/invoices', function () {
            return view('admin.sales.invoices');
        })->name('invoices');

        Route::get('/credit', function () {
            return view('admin.sales.credit');
        })->name('credit');

        Route::get('/customers', function () {
            return view('admin.sales.customers');
        })->name('customers');

        Route::get('/products', function () {
            return view('admin.sales.products');
        })->name('products');
    });
});
