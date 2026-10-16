<?php

namespace App\Contracts\Dashboard;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * KPI aggregates shared by the admin dashboard and the owner API. Callers pass explicit
 * period bounds ($from/$to, null = unbounded) because the two surfaces define "week",
 * "today" and cash differently; each controller keeps computing its own boundaries.
 */
interface DashboardMetricsInterface
{
    /** Countable invoices created in [$from, $to]. */
    public function invoiceCount(?CarbonInterface $from, ?CarbonInterface $to = null, ?int $branchId = null): int;

    /** Net sales (final - refunded) of countable invoices created in [$from, $to]. */
    public function netSales(?CarbonInterface $from, ?CarbonInterface $to = null, ?int $branchId = null): float;

    /** Non-credit payments of countable invoices received in [$from, $to]. */
    public function cashCollected(?CarbonInterface $from, ?CarbonInterface $to = null, ?int $branchId = null): float;

    /** Customer ledger payment_collection entries in [$from, $to]. Ledger rows carry no branch. */
    public function creditCollected(?CarbonInterface $from, ?CarbonInterface $to = null): float;

    /** Owner "safe cash": cashCollected + creditCollected for the same period. */
    public function safeCash(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): float;

    /** $activeOnly: owner excludes inactive products, admin counts them. */
    public function lowStockCount(bool $activeOnly): int;

    /** @return Collection<int, Product> lowest stock first */
    public function lowStockProducts(int $limit): Collection;

    public function productsCount(): int;

    public function customersCount(): int;

    public function vehiclesCount(): int;

    public function totalCreditBalance(): float;

    /** $activeOnly: owner counts active customers only, admin counts all. */
    public function creditCustomersCount(bool $activeOnly): int;

    /** @return Collection<int, Customer> highest balance first */
    public function topDebtors(int $limit): Collection;

    /** @return Collection<int, Invoice> latest countable invoices */
    public function recentInvoices(int $limit): Collection;

    /**
     * All-time net line sales per category of countable invoices, plus scrap deductions.
     *
     * @return array{battery: float, oil: float, service: float, grease: float, scrap: float}
     */
    public function categorySales(): array;

    /**
     * Net line sales per day and category, zero-filled, from ONE query.
     *
     * @return array<string, array{battery: float, oil: float, service: float}> keyed Y-m-d
     */
    public function categoryTrend(CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Admin attendance tiles for a day ("absent" includes leave).
     *
     * @return array{present: int, late: int, absent: int}
     */
    public function attendanceOn(CarbonInterface $day): array;

    /** @return Collection<int, Employee> with jobTitle and that day's attendances */
    public function activeEmployeesWithAttendance(CarbonInterface $day, int $limit): Collection;

    /** Owner: active employees, optionally of one branch. */
    public function activeEmployeesCount(?int $branchId = null): int;

    /** Owner: attendances on $day with status present or late. */
    public function presentOrLateCount(CarbonInterface $day, ?int $branchId = null): int;

    /** Owner: latest attendance on $day that is checked in and not checked out. */
    public function openAttendance(CarbonInterface $day, ?int $branchId = null): ?Attendance;

    /** Owner: latest countable invoice created on $day, with its cashier. */
    public function latestInvoiceOn(CarbonInterface $day, ?int $branchId = null): ?Invoice;

    /** Owner: refunded / partially refunded invoices last updated on $day. */
    public function returnsOn(CarbonInterface $day, ?int $branchId = null): int;
}
