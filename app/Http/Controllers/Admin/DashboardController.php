<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Product;
use App\Models\InvoiceItem;
use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display live management dashboard with real metrics from database.
     */
    public function index(Request $request)
    {
        // 1. KPI Stats
        $validInvoices = Invoice::where('status', '!=', 'cancelled');
        $totalSales = (float) $validInvoices->sum('final_amount');
        $invoicesCount = (int) $validInvoices->count();

        $totalCredit = (float) Customer::sum('current_credit_balance');
        $creditCustomersCount = (int) Customer::where('current_credit_balance', '>', 0)->count();

        $customersCount = (int) Customer::count();
        $vehiclesCount = (int) CustomerVehicle::count();

        $productsCount = (int) Product::count();
        $lowStockCount = (int) Product::whereColumn('current_stock', '<=', 'reorder_threshold')->count();

        // 2. Category Sub-summaries
        $catStatBatteries = (float) InvoiceItem::whereHas('product', fn($q) => $q->where('is_battery', true))->sum('total_price');
        $catStatOils = (float) InvoiceItem::whereHas('product', fn($q) => $q->where('is_battery', false)->whereHas('category', fn($c) => $c->where('slug', 'oils')))->sum('total_price');
        $catStatServices = (float) InvoiceItem::whereHas('product', fn($q) => $q->whereHas('category', fn($c) => $c->where('slug', 'services')))->sum('total_price');
        $catStatGreases = (float) InvoiceItem::whereHas('product', fn($q) => $q->where('is_battery', false)->whereHas('category', fn($c) => $c->where('slug', 'greases')))->sum('total_price');
        $catStatScrap = (float) Invoice::where('status', '!=', 'cancelled')->sum('scrap_deduction_amount');

        // Percentages for Donut
        $totalCatSales = $catStatBatteries + $catStatOils + $catStatGreases + $catStatServices;
        if ($totalCatSales > 0) {
            $pctBatteries = round(($catStatBatteries / $totalCatSales) * 100);
            $pctOils = round(($catStatOils / $totalCatSales) * 100);
            $pctGreases = round(($catStatGreases / $totalCatSales) * 100);
            $pctServices = max(0, 100 - ($pctBatteries + $pctOils + $pctGreases));
        } else {
            $pctBatteries = 50;
            $pctOils = 30;
            $pctGreases = 10;
            $pctServices = 10;
        }

        // 3. Weekly Sales Trend Chart (Last 7 Days)
        $trendCategories = [];
        $trendBatteries = [];
        $trendOils = [];
        $trendServices = [];

        $arabicDays = [
            'Saturday' => 'السبت',
            'Sunday' => 'الأحد',
            'Monday' => 'الاثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
        ];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayEnglish = $date->format('l');
            $trendCategories[] = $arabicDays[$dayEnglish] ?? $dayEnglish;

            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            // Daily sales for batteries
            $dayBat = (float) InvoiceItem::whereHas('invoice', fn($q) => $q->whereBetween('created_at', [$dayStart, $dayEnd])->where('status', '!=', 'cancelled'))
                ->whereHas('product', fn($q) => $q->where('is_battery', true))
                ->sum('total_price');

            // Daily sales for oils
            $dayOil = (float) InvoiceItem::whereHas('invoice', fn($q) => $q->whereBetween('created_at', [$dayStart, $dayEnd])->where('status', '!=', 'cancelled'))
                ->whereHas('product', fn($q) => $q->where('is_battery', false)->whereHas('category', fn($c) => $c->where('slug', 'oils')))
                ->sum('total_price');

            // Daily sales for services
            $daySrv = (float) InvoiceItem::whereHas('invoice', fn($q) => $q->whereBetween('created_at', [$dayStart, $dayEnd])->where('status', '!=', 'cancelled'))
                ->whereHas('product', fn($q) => $q->whereHas('category', fn($c) => $c->where('slug', 'services')))
                ->sum('total_price');

            $trendBatteries[] = $dayBat;
            $trendOils[] = $dayOil;
            $trendServices[] = $daySrv;
        }

        // 4. Recent Invoices
        $recentInvoices = Invoice::with(['customer', 'customerVehicle', 'items.product'])
            ->where('status', '!=', 'cancelled')
            ->latest('id')
            ->take(6)
            ->get();

        // 5. Debtors / Credit Dues
        $debtors = Customer::with('vehicles')
            ->where('current_credit_balance', '>', 0)
            ->orderByDesc('current_credit_balance')
            ->take(5)
            ->get();

        // 6. Low Stock Products
        $lowStockProducts = Product::with('category')
            ->whereColumn('current_stock', '<=', 'reorder_threshold')
            ->orderBy('current_stock')
            ->take(6)
            ->get();

        // 7. Workshop Attendance for Today
        $todayStr = Carbon::today()->toDateString();
        $presentCount = Attendance::where('work_date', $todayStr)->where('status', 'present')->count();
        $lateCount = Attendance::where('work_date', $todayStr)->where('status', 'late')->count();
        $absentCount = Attendance::where('work_date', $todayStr)->whereIn('status', ['absent', 'leave'])->count();

        $workshopTechs = Employee::with(['jobTitle', 'attendances' => fn($q) => $q->where('work_date', $todayStr)])
            ->where('status', 'active')
            ->take(6)
            ->get();

        return view('admin.dashboard', compact(
            'totalSales',
            'invoicesCount',
            'totalCredit',
            'creditCustomersCount',
            'customersCount',
            'vehiclesCount',
            'productsCount',
            'lowStockCount',
            'catStatBatteries',
            'catStatOils',
            'catStatServices',
            'catStatGreases',
            'catStatScrap',
            'pctBatteries',
            'pctOils',
            'pctGreases',
            'pctServices',
            'trendCategories',
            'trendBatteries',
            'trendOils',
            'trendServices',
            'recentInvoices',
            'debtors',
            'lowStockProducts',
            'presentCount',
            'lateCount',
            'absentCount',
            'workshopTechs'
        ));
    }
}
