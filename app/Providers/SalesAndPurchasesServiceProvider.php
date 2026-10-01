<?php

namespace App\Providers;

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Contracts\Purchases\SupplierServiceInterface;
use App\Contracts\Sales\PosOrderServiceInterface;
use App\Contracts\Sales\ScrapBatteryServiceInterface;
use App\Contracts\Sales\WarrantyServiceInterface;
use App\Services\Purchases\PurchaseService;
use App\Services\Purchases\SupplierService;
use App\Services\Sales\PosOrderService;
use App\Services\Sales\ScrapBatteryService;
use App\Services\Sales\WarrantyService;
use Illuminate\Support\ServiceProvider;

class SalesAndPurchasesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SupplierServiceInterface::class, SupplierService::class);
        $this->app->bind(PurchaseServiceInterface::class, PurchaseService::class);
        $this->app->bind(PosOrderServiceInterface::class, PosOrderService::class);
        $this->app->bind(WarrantyServiceInterface::class, WarrantyService::class);
        $this->app->bind(ScrapBatteryServiceInterface::class, ScrapBatteryService::class);
        $this->app->bind(\App\Contracts\Catalog\ProductServiceInterface::class, \App\Services\Catalog\ProductService::class);
        $this->app->bind(\App\Contracts\Sales\CustomerServiceInterface::class, \App\Services\Sales\CustomerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
