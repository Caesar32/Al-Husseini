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
        Route::get('/employees', function () {
            return view('admin.hr.employees');
        })->name('employees');

        Route::get('/attendance', function () {
            return view('admin.hr.attendance');
        })->name('attendance');

        Route::get('/payroll', function () {
            return view('admin.hr.payroll');
        })->name('payroll');

        Route::get('/reports', function () {
            return view('admin.hr.reports');
        })->name('reports');
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
