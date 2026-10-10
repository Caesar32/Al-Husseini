<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Owner\AuthController;
use App\Http\Controllers\Api\V1\Owner\DashboardController;
use App\Http\Controllers\Api\V1\Owner\SalesController;
use App\Http\Controllers\Api\V1\Owner\CashDrawerController;
use App\Http\Controllers\Api\V1\Owner\InventoryController;
use App\Http\Controllers\Api\V1\Owner\StaffController;
use App\Http\Controllers\Api\V1\Owner\CreditController;

/*
|--------------------------------------------------------------------------
| API Routes - V1 Owner Executive Mobile App
|--------------------------------------------------------------------------
|
| Exclusively dedicated to executive remote monitoring by the business owner.
| All endpoints are read-heavy, low-latency, and scoped with 'owner:monitor'.
|
*/

Route::prefix('v1/owner')->group(function () {

    // Public Guest Route (Rate-limited: 5 attempts per minute)
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:owner-login')
        ->name('api.v1.owner.login');

    // Protected Routes (Requires Bearer token with 'owner:monitor' ability)
    Route::middleware(['auth:sanctum', 'ability:owner:monitor'])->group(function () {

        // Authentication & Session
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.owner.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.owner.me');
        Route::post('auth/device-token', [AuthController::class, 'registerDeviceToken'])->name('api.v1.owner.device_token');

        // Executive Pulse & Dashboards
        Route::get('dashboard/live', [DashboardController::class, 'live'])->name('api.v1.owner.dashboard.live');
        Route::get('dashboard/periods', [DashboardController::class, 'periods'])->name('api.v1.owner.dashboard.periods');

        // Live Sales & Invoices Feed
        Route::get('sales/recent-invoices', [SalesController::class, 'recentInvoices'])->name('api.v1.owner.sales.recent');
        Route::get('sales/invoices/{id}', [SalesController::class, 'showInvoice'])->name('api.v1.owner.sales.show');
        Route::get('sales/returns', [SalesController::class, 'recentReturns'])->name('api.v1.owner.sales.returns');

        // Cash Drawer & Shift Surveillance
        Route::get('cash-drawer/current-shift', [CashDrawerController::class, 'currentShift'])->name('api.v1.owner.cash_drawer.shift');

        // Operational Health: Inventory & Alerts
        Route::get('inventory/alerts', [InventoryController::class, 'alerts'])->name('api.v1.owner.inventory.alerts');

        // Staff & Workshop Live Attendance
        Route::get('staff/today-attendance', [StaffController::class, 'todayAttendance'])->name('api.v1.owner.staff.attendance');

        // Customer Receivables (الآجل)
        Route::get('credit/overview', [CreditController::class, 'overview'])->name('api.v1.owner.credit.overview');

    });

});
