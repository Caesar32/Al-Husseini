<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Invoice;
use App\Models\Attendance;
use App\Models\PurchaseInvoice;
use App\Observers\InvoiceObserver;
use App\Observers\AttendanceObserver;
use App\Observers\PurchaseInvoiceObserver;

use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // منح المشرف العام (super-admin) حق الوصول الكامل لكافة الصلاحيات تلقائياً
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Invoice::observe(InvoiceObserver::class);
        Attendance::observe(AttendanceObserver::class);
        PurchaseInvoice::observe(PurchaseInvoiceObserver::class);
    }
}
