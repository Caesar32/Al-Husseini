<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance;
use App\Observers\AttendanceObserver;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

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

        // Owner Mobile App login: 5 attempts / 15 minutes, keyed by email+ip combined so a
        // distributed attacker can't use a shared IP bucket and a single email can't be used
        // to lock the real owner out from a different IP.
        RateLimiter::for('owner-login', function (Request $request) {
            return Limit::perMinutes(15, 5)
                ->by(strtolower((string) $request->input('login', $request->input('email', ''))) . '|' . $request->ip())
                ->response(fn () => response()->json([
                    'status'  => 'error',
                    'code'    => 'TOO_MANY_ATTEMPTS',
                    'message' => 'محاولات تسجيل دخول كثيرة جدًا. حاول مرة أخرى بعد قليل.',
                ], 429));
        });
    }
}
