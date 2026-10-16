<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Contracts\Dashboard\DashboardMetricsInterface;
use App\Http\Controllers\Controller;
use App\Support\MoneyHelper;
use App\Support\OwnerPulseCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardMetricsInterface $metrics) {}

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
            $safeCashTotal = $this->metrics->safeCash($today->copy()->startOfDay(), $today->copy()->endOfDay(), $branchId);
            $safeCashFormatted = MoneyHelper::formatCompactCurrency($safeCashTotal);

            // 2. Sales Today & Comparison to Yesterday
            $salesTodayRaw = $this->metrics->netSales($today->copy()->startOfDay(), $today->copy()->endOfDay(), $branchId);
            $invoicesCountToday = $this->metrics->invoiceCount($today->copy()->startOfDay(), $today->copy()->endOfDay(), $branchId);
            $salesYesterdayRaw = $this->metrics->netSales($yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay(), $branchId);

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
            $activeAttendance = $this->metrics->openAttendance($today, $branchId);

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
                $latestTodayInvoice = $this->metrics->latestInvoiceOn($today, $branchId);

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
            $totalEmployees = $this->metrics->activeEmployeesCount($branchId);
            $presentCount = $this->metrics->presentOrLateCount($today, $branchId);

            $attendanceRate = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100) . '%' : '0%';

            // 5. Critical Alerts
            $lowStockCount = $this->metrics->lowStockCount(true);
            $returnsCount = $this->metrics->returnsOn($today, $branchId);
            $unsettledCreditCount = $this->metrics->creditCustomersCount(true);

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
            // Owner periods: "today" is the calendar day; week/month/year start at their calendar start.
            [$from, $to] = match ($period) {
                'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
                'week'  => [Carbon::now()->startOfWeek(), null],
                'month' => [Carbon::now()->startOfMonth(), null],
                'year'  => [Carbon::now()->startOfYear(), null],
                default => [null, null],
            };

            $revenueRaw = $this->metrics->netSales($from, $to, $branchId);
            $totalSalesCount = $this->metrics->invoiceCount($from, $to, $branchId);
            $creditCollectedRaw = $this->metrics->creditCollected($from, $to);

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
