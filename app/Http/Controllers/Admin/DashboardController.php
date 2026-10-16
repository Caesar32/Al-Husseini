<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Dashboard\DashboardMetricsInterface;
use App\Http\Controllers\Controller;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const ARABIC_DAYS = [
        'Saturday' => 'السبت', 'Sunday' => 'الأحد', 'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة',
    ];

    public function __construct(private DashboardMetricsInterface $metrics) {}

    /**
     * Display live management dashboard with real metrics from database.
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        // key => [start (null = all time), label, badge, sublabel]. "week" = last 7 days including today.
        $periods = [
            'today' => [$today->copy()->startOfDay(), 'اليوم', 'مبيعات اليوم', 'اليوم'],
            'week' => [$today->copy()->subDays(6)->startOfDay(), 'أسبوع', 'آخر 7 أيام', 'هذا الأسبوع'],
            'month' => [$today->copy()->startOfMonth(), 'شهر', 'الشهر الحالي', 'هذا الشهر'],
            'year' => [$today->copy()->startOfYear(), 'سنة', 'السنة الحالية', 'هذا العام'],
            'all' => [null, 'الكل', 'الإجمالي العام', 'منذ البداية'],
        ];

        $salesPeriods = [];
        foreach ($periods as $key => [$start, $label, $badge, $sublabel]) {
            $total = round($this->metrics->netSales($start), 2);
            // Revenue = payments actually received in the period (credit method excluded).
            $revenue = round($this->metrics->cashCollected($start), 2);
            $credit = round($this->metrics->creditCollected($start), 2);
            $salesPeriods[$key] = [
                'key' => $key, 'label' => $label, 'badge' => $badge, 'sublabel' => $sublabel,
                'total' => $total,
                'total_formatted' => number_format($total, 2).' ج.م',
                'total_compact' => MoneyHelper::formatCompactCurrency($total)['compact'],
                'count' => $this->metrics->invoiceCount($start),
                'invoices_url' => $start
                    ? route('admin.sales.invoices', ['date_from' => $start->toDateString(), 'date_to' => $today->toDateString()])
                    : route('admin.sales.invoices'),
                'revenue' => $revenue,
                'revenue_formatted' => number_format($revenue, 2).' ج.م',
                'revenue_compact' => MoneyHelper::formatCompactCurrency($revenue)['compact'],
                'credit_collected' => $credit,
                'credit_collected_fmt' => number_format($credit, 2).' ج.م',
                'credit_collected_compact' => MoneyHelper::formatCompactCurrency($credit)['compact'],
            ];
        }

        $selectedPeriodKey = $request->query('sales_period', 'all');
        if (! array_key_exists($selectedPeriodKey, $salesPeriods)) {
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

        $totalCredit = $this->metrics->totalCreditBalance();
        ['compact' => $totalCreditCompact, 'exact' => $totalCreditExact] = MoneyHelper::formatCompactCurrency($totalCredit);
        $creditCustomersCount = $this->metrics->creditCustomersCount(false);
        $customersCount = $this->metrics->customersCount();
        $vehiclesCount = $this->metrics->vehiclesCount();
        $productsCount = $this->metrics->productsCount();
        $lowStockCount = $this->metrics->lowStockCount(false);

        // Category sub-summaries: countable invoices, line value net of returned units.
        ['battery' => $catStatBatteries, 'oil' => $catStatOils, 'service' => $catStatServices,
            'grease' => $catStatGreases, 'scrap' => $catStatScrap] = $this->metrics->categorySales();

        $totalCatSales = $catStatBatteries + $catStatOils + $catStatGreases + $catStatServices;
        if ($totalCatSales > 0) {
            $pctBatteries = round(($catStatBatteries / $totalCatSales) * 100);
            $pctOils = round(($catStatOils / $totalCatSales) * 100);
            $pctGreases = round(($catStatGreases / $totalCatSales) * 100);
            $pctServices = max(0, 100 - ($pctBatteries + $pctOils + $pctGreases));
        } else {
            [$pctBatteries, $pctOils, $pctGreases, $pctServices] = [50, 30, 10, 10];
        }

        // Weekly sales trend chart (last 7 days).
        $trend = $this->metrics->categoryTrend($today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay());
        $trendCategories = array_map(fn ($date) => self::ARABIC_DAYS[Carbon::parse($date)->format('l')], array_keys($trend));
        $trendBatteries = array_column($trend, 'battery');
        $trendOils = array_column($trend, 'oil');
        $trendServices = array_column($trend, 'service');

        $recentInvoices = $this->metrics->recentInvoices(6);
        $debtors = $this->metrics->topDebtors(5);
        $lowStockProducts = $this->metrics->lowStockProducts(6);
        ['present' => $presentCount, 'late' => $lateCount, 'absent' => $absentCount] = $this->metrics->attendanceOn($today);
        $workshopTechs = $this->metrics->activeEmployeesWithAttendance($today, 6);

        return view('admin.dashboard', compact(
            'totalSales', 'invoicesCount', 'totalCredit', 'totalCreditCompact', 'totalCreditExact',
            'creditCustomersCount', 'customersCount', 'vehiclesCount', 'productsCount', 'lowStockCount',
            'catStatBatteries', 'catStatOils', 'catStatServices', 'catStatGreases', 'catStatScrap',
            'pctBatteries', 'pctOils', 'pctGreases', 'pctServices',
            'trendCategories', 'trendBatteries', 'trendOils', 'trendServices',
            'recentInvoices', 'debtors', 'lowStockProducts', 'presentCount', 'lateCount', 'absentCount',
            'workshopTechs', 'salesPeriods', 'selectedPeriodKey', 'selectedPeriod'
        ));
    }
}
