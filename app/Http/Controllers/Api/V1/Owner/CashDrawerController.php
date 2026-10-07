<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CreditLedgerEntry;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashDrawerController extends Controller
{
    /**
     * Surveillance of Cash Drawer & Current Shift.
     * Provides instant live audit of drawer physical cash vs expected totals.
     */
    public function currentShift(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        // 1. Determine active shift and cashier
        $activeAttendanceQuery = Attendance::query()
            ->where('work_date', $today)
            ->whereNotNull('check_in')
            ->whereNull('check_out');
        if ($branchId !== null) {
            $activeAttendanceQuery->whereHas('employee', fn($q) => $q->where('branch_id', $branchId));
        }
        $activeAttendance = $activeAttendanceQuery
            ->with(['employee.jobTitle', 'employee.user'])
            ->latest('check_in')
            ->first();

        $cashierName = 'كاشير المحل';
        $shiftStatus = 'open';
        $openedAt = null;
        $duration = null;
        $openingBalance = 0.00;

        if ($activeAttendance && $activeAttendance->check_in) {
            $cashierName = $activeAttendance->employee?->full_name ?? 'كاشير الوردية';
            $checkInTime = $activeAttendance->check_in;
            $openedAt = $checkInTime->format('h:i') . ' ' . ($checkInTime->format('A') === 'AM' ? 'ص' : 'م');
            $diffMinutes = (int) $checkInTime->diffInMinutes(now());
            $hours = intdiv($diffMinutes, 60);
            $mins = $diffMinutes % 60;
            $duration = $hours > 0 ? "{$hours} ساعة و {$mins} دقيقة" : "{$mins} دقيقة";
        } else {
            $latestInvoiceQuery = Invoice::countable()->whereDate('created_at', $today);
            if ($branchId !== null) {
                $latestInvoiceQuery->where('branch_id', $branchId);
            }
            $latestInvoice = $latestInvoiceQuery->with('cashier')->latest('id')->first();
            if ($latestInvoice && $latestInvoice->cashier) {
                $cashierName = $latestInvoice->cashier->name;
                $openedAt = $latestInvoice->created_at->format('h:i') . ' ' . ($latestInvoice->created_at->format('A') === 'AM' ? 'ص' : 'م');
                $duration = 'مستمر';
            } else {
                $shiftStatus = 'no_active_shift';
            }
        }

        // 2. Calculate drawer figures
        $paymentsQuery = InvoicePayment::active()->whereDate('created_at', $today);
        if ($branchId !== null) {
            $paymentsQuery->whereHas('invoice', fn($q) => $q->where('branch_id', $branchId));
        }

        // Only real payment_method enum values written by PosOrderService/validation rules
        // (cash, card, bank_transfer, credit) are queried here — 'visa'/'mastercard'/'pos'/
        // 'instapay' never occur in invoice_payments and would silently always sum to zero.
        $cashSales = (float) (clone $paymentsQuery)->where('payment_method', 'cash')->sum('amount');
        $cardSales = (float) (clone $paymentsQuery)->where('payment_method', 'card')->sum('amount');
        $bankTransferSales = (float) (clone $paymentsQuery)->where('payment_method', 'bank_transfer')->sum('amount');

        $creditQuery = CreditLedgerEntry::where('entry_type', 'payment_collection')->whereDate('created_at', $today);
        $creditCollectionsCash = (float) $creditQuery->sum('amount');

        $expensesPaid = 0.00;

        $expectedDrawerCash = $openingBalance + $cashSales + $creditCollectionsCash - $expensesPaid;

        return response()->json([
            'status' => 'success',
            'data'   => [
                'cashier_name'            => $cashierName,
                'shift_status'            => $shiftStatus,
                'opened_at'               => $openedAt,
                'duration'                => $duration,
                'opening_balance'         => $openingBalance,
                'cash_sales'              => $cashSales,
                'card_sales'              => $cardSales,
                'bank_transfer_sales'     => $bankTransferSales,
                'credit_collections_cash' => $creditCollectionsCash,
                'expenses_paid'           => $expensesPaid,
                'expected_drawer_cash'    => $expectedDrawerCash,
                'audit_notes'             => 'الرصيد الافتتاحي والمصروفات النثرية غير مسجلة بنظام الكاشير حالياً؛ الكاش المتوقع يمثل صافي المقبوضات النقدية والتحصيلات فقط.',
                'formatted'               => [
                    'opening_balance'         => number_format($openingBalance, 2) . ' ج.م',
                    'cash_sales'              => number_format($cashSales, 2) . ' ج.م',
                    'card_sales'              => number_format($cardSales, 2) . ' ج.م',
                    'bank_transfer_sales'     => number_format($bankTransferSales, 2) . ' ج.م',
                    'credit_collections_cash' => number_format($creditCollectionsCash, 2) . ' ج.م',
                    'expenses_paid'           => number_format($expensesPaid, 2) . ' ج.م',
                    'expected_drawer_cash'    => number_format($expectedDrawerCash, 2) . ' ج.م',
                ],
            ],
        ]);
    }
}
