<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
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
        $this->app->bind(
            \App\Contracts\SearchServiceInterface::class,
            \App\Services\SearchService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ─── حماية صارمة من مشاكل N+1 في بيئة التطوير ───────────────────────────────
        // يرمي Exception فوري إذا تم الوصول إلى أي علاقة بدون Eager Loading مسبق
        Model::preventLazyLoading(! app()->isProduction());

        // منح المشرف العام (super-admin) حق الوصول الكامل لكافة الصلاحيات تلقائياً
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Invoice::observe(InvoiceObserver::class);
        Attendance::observe(AttendanceObserver::class);
        PurchaseInvoice::observe(PurchaseInvoiceObserver::class);
    }
}
