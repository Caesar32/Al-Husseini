<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\CreditLedgerEntry;
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
        // 1. KPI Stats & Sales Periods Calculations
        $todayStart = Carbon::today()->startOfDay();
        $weekStart  = Carbon::today()->subDays(6)->startOfDay(); // آخر 7 أيام شاملة اليوم
        $monthStart = Carbon::today()->startOfMonth();
        $yearStart  = Carbon::today()->startOfYear();

        $baseInvoices = Invoice::whereNotIn('status', ['cancelled', 'refunded', 'partially_refunded']);

        // ─── مبيعات الفترة (قيمة الفواتير الصادرة - دفترية) ────────────────────────────
        $salesToday        = (float) (clone $baseInvoices)->where('created_at', '>=', $todayStart)->sum('final_amount');
        $invoicesCountToday = (int)  (clone $baseInvoices)->where('created_at', '>=', $todayStart)->count();

        $salesWeek         = (float) (clone $baseInvoices)->where('created_at', '>=', $weekStart)->sum('final_amount');
        $invoicesCountWeek  = (int)  (clone $baseInvoices)->where('created_at', '>=', $weekStart)->count();

        $salesMonth         = (float) (clone $baseInvoices)->where('created_at', '>=', $monthStart)->sum('final_amount');
        $invoicesCountMonth  = (int)  (clone $baseInvoices)->where('created_at', '>=', $monthStart)->count();

        $salesYear          = (float) (clone $baseInvoices)->where('created_at', '>=', $yearStart)->sum('final_amount');
        $invoicesCountYear   = (int)  (clone $baseInvoices)->where('created_at', '>=', $yearStart)->count();

        $salesAll           = (float) (clone $baseInvoices)->sum('final_amount');
        $invoicesCountAll    = (int)  (clone $baseInvoices)->count();

        // ─── الإيراد النقدي الفعلي = مجموع الدفعات المقبوضة فعلاً في الفترة ─────────────
        // نقرأ من InvoicePayment بتاريخ الدفع الفعلي (created_at) لا بتاريخ إنشاء الفاتورة.
        // نستثني method='credit' لأنه يمثّل الجزء الآجل غير المقبوض بعد (ليس نقدًا فعليًا).
        // عند تحصيل الآجل لاحقًا بـ settleCustomerDebt، يُسجَّل InvoicePayment بطريقة فعلية
        // (cash/instapay/...) وبتاريخ التحصيل، فيُحتسب تلقائياً في الإيراد.
        $basePayments = InvoicePayment::active()->cash();

        $cashToday  = (float) (clone $basePayments)->where('created_at', '>=', $todayStart)->sum('amount');
        $cashWeek   = (float) (clone $basePayments)->where('created_at', '>=', $weekStart)->sum('amount');
        $cashMonth  = (float) (clone $basePayments)->where('created_at', '>=', $monthStart)->sum('amount');
        $cashYear   = (float) (clone $basePayments)->where('created_at', '>=', $yearStart)->sum('amount');
        $cashAll    = (float) (clone $basePayments)->sum('amount');

        // تحصيلات الآجل من دفتر الأستاذ (payment_collection entries غير مرتبطة بفاتورة محددة)
        // هذه احتياطية فقط — settleCustomerDebt يُسجّل InvoicePayment مباشرة على الفواتير
        $baseLedger = CreditLedgerEntry::where('entry_type', 'payment_collection');
        $creditCollectedToday = (float) (clone $baseLedger)->where('created_at', '>=', $todayStart)->sum('amount');
        $creditCollectedWeek  = (float) (clone $baseLedger)->where('created_at', '>=', $weekStart)->sum('amount');
        $creditCollectedMonth = (float) (clone $baseLedger)->where('created_at', '>=', $monthStart)->sum('amount');
        $creditCollectedYear  = (float) (clone $baseLedger)->where('created_at', '>=', $yearStart)->sum('amount');
        $creditCollectedAll   = (float) (clone $baseLedger)->sum('amount');

        // الإيراد الإجمالي = دفعات InvoicePayment + تحصيلات دفتر الأستاذ (إن وُجد فارق)
        // ملاحظة: settleCustomerDebt الآن يُسجّل InvoicePayment على الفواتير المفتوحة،
        // لذا cashXxx تشمله بالفعل، و creditCollectedXxx احتياط للقيود غير المرتبطة بفاتورة.
        $revenueToday = round($cashToday, 2);
        $revenueWeek  = round($cashWeek, 2);
        $revenueMonth = round($cashMonth, 2);
        $revenueYear  = round($cashYear, 2);
        $revenueAll   = round($cashAll, 2);
        // ────────────────────────────────────────────────────────────────────────────────


        $salesPeriods = [
            'today' => [
                'key'                    => 'today',
                'label'                  => 'اليوم',
                'badge'                  => 'مبيعات اليوم',
                'sublabel'               => 'اليوم',
                'total'                  => round($salesToday, 2),
                'total_formatted'        => number_format(round($salesToday, 2), 2) . ' ج.م',
                'count'                  => $invoicesCountToday,
                'invoices_url'           => route('admin.sales.invoices', ['date_from' => $todayStart->toDateString(), 'date_to' => Carbon::today()->toDateString()]),
                'revenue'                => $revenueToday,
                'revenue_formatted'      => number_format($revenueToday, 2) . ' ج.م',
                'credit_collected'       => round($creditCollectedToday, 2),
                'credit_collected_fmt'   => number_format(round($creditCollectedToday, 2), 2) . ' ج.م',
            ],
            'week' => [
                'key'                    => 'week',
                'label'                  => 'أسبوع',
                'badge'                  => 'آخر 7 أيام',
                'sublabel'               => 'هذا الأسبوع',
                'total'                  => round($salesWeek, 2),
                'total_formatted'        => number_format(round($salesWeek, 2), 2) . ' ج.م',
                'count'                  => $invoicesCountWeek,
                'invoices_url'           => route('admin.sales.invoices', ['date_from' => $weekStart->toDateString(), 'date_to' => Carbon::today()->toDateString()]),
                'revenue'                => $revenueWeek,
                'revenue_formatted'      => number_format($revenueWeek, 2) . ' ج.م',
                'credit_collected'       => round($creditCollectedWeek, 2),
                'credit_collected_fmt'   => number_format(round($creditCollectedWeek, 2), 2) . ' ج.م',
            ],
            'month' => [
                'key'                    => 'month',
                'label'                  => 'شهر',
                'badge'                  => 'الشهر الحالي',
                'sublabel'               => 'هذا الشهر',
                'total'                  => round($salesMonth, 2),
                'total_formatted'        => number_format(round($salesMonth, 2), 2) . ' ج.م',
                'count'                  => $invoicesCountMonth,
                'invoices_url'           => route('admin.sales.invoices', ['date_from' => $monthStart->toDateString(), 'date_to' => Carbon::today()->toDateString()]),
                'revenue'                => $revenueMonth,
                'revenue_formatted'      => number_format($revenueMonth, 2) . ' ج.م',
                'credit_collected'       => round($creditCollectedMonth, 2),
                'credit_collected_fmt'   => number_format(round($creditCollectedMonth, 2), 2) . ' ج.م',
            ],
            'year' => [
                'key'                    => 'year',
                'label'                  => 'سنة',
                'badge'                  => 'السنة الحالية',
                'sublabel'               => 'هذا العام',
                'total'                  => round($salesYear, 2),
                'total_formatted'        => number_format(round($salesYear, 2), 2) . ' ج.م',
                'count'                  => $invoicesCountYear,
                'invoices_url'           => route('admin.sales.invoices', ['date_from' => $yearStart->toDateString(), 'date_to' => Carbon::today()->toDateString()]),
                'revenue'                => $revenueYear,
                'revenue_formatted'      => number_format($revenueYear, 2) . ' ج.م',
                'credit_collected'       => round($creditCollectedYear, 2),
                'credit_collected_fmt'   => number_format(round($creditCollectedYear, 2), 2) . ' ج.م',
            ],
            'all' => [
                'key'                    => 'all',
                'label'                  => 'الكل',
                'badge'                  => 'الإجمالي العام',
                'sublabel'               => 'منذ البداية',
                'total'                  => round($salesAll, 2),
                'total_formatted'        => number_format(round($salesAll, 2), 2) . ' ج.م',
                'count'                  => $invoicesCountAll,
                'invoices_url'           => route('admin.sales.invoices'),
                'revenue'                => $revenueAll,
                'revenue_formatted'      => number_format($revenueAll, 2) . ' ج.م',
                'credit_collected'       => round($creditCollectedAll, 2),
                'credit_collected_fmt'   => number_format(round($creditCollectedAll, 2), 2) . ' ج.م',
            ],
        ];

        $selectedPeriodKey = $request->query('sales_period', 'all');
        if (!array_key_exists($selectedPeriodKey, $salesPeriods)) {
            $selectedPeriodKey = 'all';
        }

        $selectedPeriod = $salesPeriods[$selectedPeriodKey];
        $totalSales = $selectedPeriod['total'];
        $invoicesCount = $selectedPeriod['count'];

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'selected_period' => $selectedPeriodKey,
                'data' => $selectedPeriod,
                'all_periods' => $salesPeriods,
            ]);
        }


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
        $catStatScrap = (float) Invoice::whereNotIn('status', ['cancelled', 'refunded', 'partially_refunded'])->sum('scrap_deduction_amount');

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
            $dayBat = (float) InvoiceItem::whereHas('invoice', fn($q) => $q->whereBetween('created_at', [$dayStart, $dayEnd])->whereNotIn('status', ['cancelled', 'refunded', 'partially_refunded']))
                ->whereHas('product', fn($q) => $q->where('is_battery', true))
                ->sum('total_price');

            // Daily sales for oils
            $dayOil = (float) InvoiceItem::whereHas('invoice', fn($q) => $q->whereBetween('created_at', [$dayStart, $dayEnd])->whereNotIn('status', ['cancelled', 'refunded', 'partially_refunded']))
                ->whereHas('product', fn($q) => $q->where('is_battery', false)->whereHas('category', fn($c) => $c->where('slug', 'oils')))
                ->sum('total_price');

            // Daily sales for services
            $daySrv = (float) InvoiceItem::whereHas('invoice', fn($q) => $q->whereBetween('created_at', [$dayStart, $dayEnd])->whereNotIn('status', ['cancelled', 'refunded', 'partially_refunded']))
                ->whereHas('product', fn($q) => $q->whereHas('category', fn($c) => $c->where('slug', 'services')))
                ->sum('total_price');

            $trendBatteries[] = $dayBat;
            $trendOils[] = $dayOil;
            $trendServices[] = $daySrv;
        }

        // 4. Recent Invoices
        $recentInvoices = Invoice::with(['customer', 'customerVehicle', 'items.product'])
            ->whereNotIn('status', ['cancelled', 'refunded', 'partially_refunded'])
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
            'workshopTechs',
            'salesPeriods',
            'selectedPeriodKey',
            'selectedPeriod'
        ));
    }
}
