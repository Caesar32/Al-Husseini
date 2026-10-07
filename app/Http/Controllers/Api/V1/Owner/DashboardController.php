<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\CreditLedgerEntry;
use App\Models\Product;
use App\Support\MoneyHelper;
use App\Support\OwnerPulseCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Live Pulse Dashboard API (Ultra low-latency < 50ms).
     * Provides an executive snapshot of current shop pulse.
     */
    public function live(Request $request): JsonResponse
    {
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $pulseData = OwnerPulseCache::live($branchId, function () use ($branchId) {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();

            // 1. Safe cash today (Invoice cash payments + Credit cash collections)
            $cashQuery = InvoicePayment::active()->cash()->whereDate('created_at', $today);
            if ($branchId !== null) {
                $cashQuery->whereHas('invoice', fn($q) => $q->where('branch_id', $branchId));
            }
            $paymentsCash = (float) $cashQuery->sum('amount');

            $creditCollectionsCash = (float) CreditLedgerEntry::whereIn('entry_type', ['payment_collection', 'payment'])
                ->whereDate('created_at', $today)
                ->sum('amount');

            $safeCashTotal = $paymentsCash + $creditCollectionsCash;
            $safeCashFormatted = MoneyHelper::formatCompactCurrency($safeCashTotal);

            // 2. Sales Today & Comparison to Yesterday
            $invoicesToday = Invoice::countable()->whereDate('created_at', $today);
            $invoicesYesterday = Invoice::countable()->whereDate('created_at', $yesterday);

            if ($branchId !== null) {
                $invoicesToday->where('branch_id', $branchId);
                $invoicesYesterday->where('branch_id', $branchId);
            }

            $salesTodayRaw = Invoice::sumNetAmount($invoicesToday);
            $invoicesCountToday = (int) (clone $invoicesToday)->count();

            $salesYesterdayRaw = Invoice::sumNetAmount($invoicesYesterday);

            $comparedPercent = '+0.0%';
            if ($salesYesterdayRaw > 0) {
                $diff = (($salesTodayRaw - $salesYesterdayRaw) / $salesYesterdayRaw) * 100;
                $sign = $diff >= 0 ? '+' : '';
                $comparedPercent = $sign . round($diff, 1) . '%';
            } elseif ($salesTodayRaw > 0) {
                $comparedPercent = '+100%';
            }

            $salesTodayFormatted = MoneyHelper::formatCompactCurrency($salesTodayRaw);

            // 3. Active Shift Surveillance
            $activeAttendance = Attendance::query()
                ->where('work_date', $today)
                ->whereNotNull('check_in')
                ->whereNull('check_out')
                ->with(['employee.user', 'employee.jobTitle'])
                ->latest('check_in')
                ->first();

            $shiftData = [
                'is_open'        => false,
                'cashier_name'   => null,
                'opened_at'      => null,
                'duration_hours' => null,
            ];

            if ($activeAttendance && $activeAttendance->check_in) {
                $diffMinutes = (int) $activeAttendance->check_in->diffInMinutes(now());
                $hours = intdiv($diffMinutes, 60);
                $mins = $diffMinutes % 60;
                $durationStr = $hours > 0 ? "{$hours} ساعة و {$mins} دقيقة" : "{$mins} دقيقة";

                $checkInTime = $activeAttendance->check_in;
                $openedAtStr = $checkInTime->format('h:i') . ' ' . ($checkInTime->format('A') === 'AM' ? 'ص' : 'م');

                $shiftData = [
                    'is_open'        => true,
                    'cashier_name'   => $activeAttendance->employee?->full_name ?? 'مسؤول الوردية',
                    'opened_at'      => $openedAtStr,
                    'duration_hours' => $durationStr,
                ];
            } else {
                // Check if any invoice was issued today to identify today's active cashier
                $latestTodayInvoice = Invoice::whereDate('created_at', $today)
                    ->with('cashier')
                    ->latest('id')
                    ->first();

                if ($latestTodayInvoice && $latestTodayInvoice->cashier) {
                    $shiftData = [
                        'is_open'        => true,
                        'cashier_name'   => $latestTodayInvoice->cashier->name,
                        'opened_at'      => $latestTodayInvoice->created_at->format('h:i') . ' ' . ($latestTodayInvoice->created_at->format('A') === 'AM' ? 'ص' : 'م'),
                        'duration_hours' => 'مفتوح',
                    ];
                }
            }

            // 4. Workshop Attendance Pulse
            $empQuery = Employee::active();
            if ($branchId !== null) {
                $empQuery->where('branch_id', $branchId);
            }
            $totalEmployees = (int) $empQuery->count();

            $attendanceQuery = Attendance::where('work_date', $today)
                ->whereIn('status', ['present', 'late']);
            if ($branchId !== null) {
                $attendanceQuery->whereHas('employee', fn($q) => $q->where('branch_id', $branchId));
            }
            $presentCount = (int) $attendanceQuery->count();

            $attendanceRate = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100) . '%' : '0%';

            // 5. Critical Alerts
            $lowStockCount = (int) Product::active()->lowStock()->count();

            $returnsQuery = Invoice::whereDate('updated_at', $today)
                ->whereIn('status', ['refunded', 'partially_refunded']);
            if ($branchId !== null) {
                $returnsQuery->where('branch_id', $branchId);
            }
            $returnsCount = (int) $returnsQuery->count();

            $unsettledCreditCount = (int) Customer::where('is_active', true)->where('current_credit_balance', '>', 0)->count();

            return [
                'safe_cash_now' => [
                    'raw'       => $safeCashTotal,
                    'formatted' => $safeCashFormatted['compact'],
                    'exact'     => $safeCashFormatted['exact'],
                    'label'     => 'الكاش الفعلي في الخزينة الآن',
                ],
                'sales_today' => [
                    'raw'                   => $salesTodayRaw,
                    'formatted'             => $salesTodayFormatted['compact'],
                    'exact'                 => $salesTodayFormatted['exact'],
                    'invoices_count'        => $invoicesCountToday,
                    'compared_to_yesterday' => $comparedPercent,
                ],
                'active_shift' => $shiftData,
                'workshop_attendance' => [
                    'present_count'   => $presentCount,
                    'total_employees' => $totalEmployees,
                    'attendance_rate' => $attendanceRate,
                ],
                'critical_alerts' => [
                    'low_stock_count'        => $lowStockCount,
                    'returns_today_count'    => $returnsCount,
                    'unsettled_credit_count' => $unsettledCreditCount,
                ],
            ];
        });

        return response()->json([
            'status'    => 'success',
            'timestamp' => now()->toIso8601String(),
            'data'      => $pulseData,
        ]);
    }

    /**
     * Periods Metrics Breakdown (today, week, month, year, all).
     * Reconciles 1:1 with the Executive Dashboard.
     */
    public function periods(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        if (!in_array($period, ['today', 'week', 'month', 'year', 'all'], true)) {
            $period = 'today';
        }

        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $periodLabels = [
            'today' => 'اليوم',
            'week'  => 'هذا الأسبوع',
            'month' => 'هذا الشهر',
            'year'  => 'هذا العام',
            'all'   => 'جميع الفترات',
        ];

        $data = OwnerPulseCache::periods($period, $branchId, function () use ($period, $periodLabels, $branchId) {
            $invoicesQuery = Invoice::countable();
            $creditQuery = CreditLedgerEntry::whereIn('entry_type', ['payment_collection', 'payment']);

            if ($branchId !== null) {
                $invoicesQuery->where('branch_id', $branchId);
            }

            switch ($period) {
                case 'today':
                    $invoicesQuery->whereDate('created_at', Carbon::today());
                    $creditQuery->whereDate('created_at', Carbon::today());
                    break;
                case 'week':
                    $startOfWeek = Carbon::now()->startOfWeek();
                    $invoicesQuery->where('created_at', '>=', $startOfWeek);
                    $creditQuery->where('created_at', '>=', $startOfWeek);
                    break;
                case 'month':
                    $startOfMonth = Carbon::now()->startOfMonth();
                    $invoicesQuery->where('created_at', '>=', $startOfMonth);
                    $creditQuery->where('created_at', '>=', $startOfMonth);
                    break;
                case 'year':
                    $startOfYear = Carbon::now()->startOfYear();
                    $invoicesQuery->where('created_at', '>=', $startOfYear);
                    $creditQuery->where('created_at', '>=', $startOfYear);
                    break;
                case 'all':
                default:
                    // No date bounds
                    break;
            }

            $revenueRaw = Invoice::sumNetAmount($invoicesQuery);
            $totalSalesCount = (int) (clone $invoicesQuery)->count();
            $creditCollectedRaw = (float) $creditQuery->sum('amount');

            $revenueFormatted = MoneyHelper::formatCompactCurrency($revenueRaw);
            $creditFormatted = MoneyHelper::formatCompactCurrency($creditCollectedRaw);

            return [
                'period'            => $period,
                'period_label'      => $periodLabels[$period] ?? $period,
                'revenue'           => [
                    'raw'     => $revenueRaw,
                    'compact' => $revenueFormatted['compact'],
                    'exact'   => $revenueFormatted['exact'],
                ],
                'total_sales_count' => $totalSalesCount,
                'credit_collected'  => [
                    'raw'     => $creditCollectedRaw,
                    'compact' => $creditFormatted['compact'],
                    'exact'   => $creditFormatted['exact'],
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }
}
