<?php

namespace App\Services\Dashboard;

use App\Contracts\Dashboard\DashboardMetricsInterface;
use App\Models\Attendance;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardMetricsService implements DashboardMetricsInterface
{
    public function invoiceCount(?CarbonInterface $from, ?CarbonInterface $to = null, ?int $branchId = null): int
    {
        return (int) $this->countableInvoices($from, $to, $branchId)->count();
    }

    public function netSales(?CarbonInterface $from, ?CarbonInterface $to = null, ?int $branchId = null): float
    {
        return Invoice::sumNetAmount($this->countableInvoices($from, $to, $branchId));
    }

    public function cashCollected(?CarbonInterface $from, ?CarbonInterface $to = null, ?int $branchId = null): float
    {
        $query = $this->between(InvoicePayment::active()->cash(), $from, $to);
        if ($branchId !== null) {
            $query->whereHas('invoice', fn ($q) => $q->where('branch_id', $branchId));
        }

        return (float) $query->sum('amount');
    }

    public function creditCollected(?CarbonInterface $from, ?CarbonInterface $to = null): float
    {
        return (float) $this->between(CreditLedgerEntry::where('entry_type', 'payment_collection'), $from, $to)->sum('amount');
    }

    public function safeCash(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): float
    {
        return $this->cashCollected($from, $to, $branchId) + $this->creditCollected($from, $to);
    }

    public function lowStockCount(bool $activeOnly): int
    {
        $query = Product::query()->lowStock();
        if ($activeOnly) {
            $query->active();
        }

        return (int) $query->count();
    }

    public function lowStockProducts(int $limit): Collection
    {
        return Product::with('category')->lowStock()->orderBy('current_stock')->take($limit)->get();
    }

    public function productsCount(): int
    {
        return (int) Product::count();
    }

    public function customersCount(): int
    {
        return (int) Customer::count();
    }

    public function vehiclesCount(): int
    {
        return (int) CustomerVehicle::count();
    }

    public function totalCreditBalance(): float
    {
        return (float) Customer::sum('current_credit_balance');
    }

    public function creditCustomersCount(bool $activeOnly): int
    {
        $query = Customer::where('current_credit_balance', '>', 0);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return (int) $query->count();
    }

    public function topDebtors(int $limit): Collection
    {
        return Customer::with('vehicles')
            ->where('current_credit_balance', '>', 0)
            ->orderByDesc('current_credit_balance')
            ->take($limit)
            ->get();
    }

    public function recentInvoices(int $limit): Collection
    {
        return Invoice::with(['customer', 'customerVehicle', 'items.product'])
            ->countable()
            ->latest('id')
            ->take($limit)
            ->get();
    }

    public function categorySales(): array
    {
        $row = $this->categoryLines()
            ->whereHas('invoice', fn ($q) => $q->countable())
            ->selectRaw($this->categorySums(withGrease: true))
            ->first();

        return [
            'battery' => (float) $row->battery,
            'oil' => (float) $row->oil,
            'service' => (float) $row->service,
            'grease' => (float) $row->grease,
            'scrap' => (float) Invoice::countable()->sum('scrap_deduction_amount'),
        ];
    }

    public function categoryTrend(CarbonInterface $from, CarbonInterface $to): array
    {
        $day = 'DATE(invoices.created_at)';
        $rows = $this->categoryLines()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereHas('invoice', fn ($q) => $q->whereBetween('created_at', [$from, $to])->countable())
            ->selectRaw("{$day} as day, ".$this->categorySums(withGrease: false))
            ->groupByRaw($day)
            ->get()
            ->keyBy('day');

        $trend = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $date) {
            $row = $rows->get($date->toDateString());
            $trend[$date->toDateString()] = [
                'battery' => (float) ($row->battery ?? 0),
                'oil' => (float) ($row->oil ?? 0),
                'service' => (float) ($row->service ?? 0),
            ];
        }

        return $trend;
    }

    public function attendanceOn(CarbonInterface $day): array
    {
        $date = $day->toDateString();

        return [
            'present' => Attendance::whereDate('work_date', $date)->where('status', 'present')->count(),
            'late' => Attendance::whereDate('work_date', $date)->where('status', 'late')->count(),
            'absent' => Attendance::whereDate('work_date', $date)->whereIn('status', ['absent', 'leave'])->count(),
        ];
    }

    public function activeEmployeesWithAttendance(CarbonInterface $day, int $limit): Collection
    {
        $date = $day->toDateString();

        return Employee::with(['jobTitle', 'attendances' => fn ($q) => $q->whereDate('work_date', $date)])
            ->where('status', 'active')
            ->take($limit)
            ->get();
    }

    public function activeEmployeesCount(?int $branchId = null): int
    {
        $query = Employee::active();
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return (int) $query->count();
    }

    public function presentOrLateCount(CarbonInterface $day, ?int $branchId = null): int
    {
        $query = Attendance::where('work_date', $day)->whereIn('status', ['present', 'late']);
        if ($branchId !== null) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId));
        }

        return (int) $query->count();
    }

    public function openAttendance(CarbonInterface $day, ?int $branchId = null): ?Attendance
    {
        $query = Attendance::query()
            ->where('work_date', $day)
            ->whereNotNull('check_in')
            ->whereNull('check_out');
        if ($branchId !== null) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId));
        }

        return $query->with(['employee.user', 'employee.jobTitle'])->latest('check_in')->first();
    }

    public function latestInvoiceOn(CarbonInterface $day, ?int $branchId = null): ?Invoice
    {
        $query = Invoice::countable()->whereDate('created_at', $day);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->with('cashier')->latest('id')->first();
    }

    public function returnsOn(CarbonInterface $day, ?int $branchId = null): int
    {
        $query = Invoice::whereDate('updated_at', $day)->whereIn('status', ['refunded', 'partially_refunded']);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return (int) $query->count();
    }

    private function countableInvoices(?CarbonInterface $from, ?CarbonInterface $to, ?int $branchId): Builder
    {
        $query = $this->between(Invoice::countable(), $from, $to);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    private function between(Builder $query, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Invoice lines joined to their product and category. Products are filtered like
     * whereHas('product') would (soft-deleted ones excluded); invoices keep their global
     * scopes through the caller's whereHas('invoice').
     */
    private function categoryLines(): Builder
    {
        return InvoiceItem::query()
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereNull('products.deleted_at');
    }

    /**
     * One SUM per category. A line may count in more than one category (e.g. a battery in
     * the services category), exactly like the separate per-category queries it replaces.
     */
    private function categorySums(bool $withGrease): string
    {
        $net = InvoiceItem::NET_LINE_SQL;
        $sums = [
            "SUM(CASE WHEN products.is_battery = 1 THEN {$net} ELSE 0 END) as battery",
            "SUM(CASE WHEN products.is_battery = 0 AND categories.slug = 'oils' THEN {$net} ELSE 0 END) as oil",
            "SUM(CASE WHEN categories.slug = 'services' THEN {$net} ELSE 0 END) as service",
        ];
        if ($withGrease) {
            $sums[] = "SUM(CASE WHEN products.is_battery = 0 AND categories.slug = 'greases' THEN {$net} ELSE 0 END) as grease";
        }

        return implode(', ', $sums);
    }
}
