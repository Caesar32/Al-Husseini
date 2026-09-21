<?php

namespace App\Providers;

use App\Contracts\Hr\EmployeeServiceInterface;
use App\Contracts\Hr\AttendanceServiceInterface;
use App\Contracts\Hr\PayrollServiceInterface;
use App\Contracts\Hr\DeductionServiceInterface;
use App\Contracts\Hr\LeaveServiceInterface;
use App\Contracts\Hr\NotificationServiceInterface;
use App\Services\Hr\EmployeeService;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\PayrollService;
use App\Services\Hr\DeductionService;
use App\Services\Hr\LeaveService;
use App\Services\Hr\NotificationService;
use Illuminate\Support\ServiceProvider;

class HrServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        EmployeeServiceInterface::class => EmployeeService::class,
        AttendanceServiceInterface::class => AttendanceService::class,
        PayrollServiceInterface::class => PayrollService::class,
        DeductionServiceInterface::class => DeductionService::class,
        LeaveServiceInterface::class => LeaveService::class,
        NotificationServiceInterface::class => NotificationService::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
