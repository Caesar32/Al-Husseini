<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance;
use App\Observers\AttendanceObserver;

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

        // NOTE: Invoice لا يحتاج Observer - منطق خصم المخزون والضمانات والآجل وعمولة الفني
        // محكوم بالكامل داخل PosOrderService::processPosSale() مع Invoice::withoutEvents()
        // لضمان Atomicity الكاملة وتجنب التكرار المحاسبي.
        // NOTE: PurchaseInvoice لا يحتاج Observer - منطق WAC والمخزون والأستاذ محكوم
        // بالكامل داخل PurchaseService::createDirectPurchase() لضمان Atomicity وتجنب التكرار.
        Attendance::observe(AttendanceObserver::class);
        \App\Models\WarrantyClaim::observe(\App\Observers\WarrantyClaimObserver::class);
    }
}
