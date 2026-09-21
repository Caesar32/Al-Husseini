<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Invoice;
use App\Models\Attendance;
use App\Observers\InvoiceObserver;
use App\Observers\AttendanceObserver;

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
        Invoice::observe(InvoiceObserver::class);
        Attendance::observe(AttendanceObserver::class);
    }
}
