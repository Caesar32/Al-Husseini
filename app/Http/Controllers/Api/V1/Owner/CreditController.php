<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Owner\DebtorResource;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Support\MoneyHelper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    /**
     * Customer Receivables & Debt Surveillance (الآجل).
     * Provides an overview of market credit, today's collections, and top debtors.
     * Note: national_id is strictly omitted from all queries and responses.
     */
    public function overview(Request $request): JsonResponse
    {
        $today = Carbon::today();

        // 1. Total outstanding credit in the market
        $totalDebtRaw = (float) Customer::inDebt()->sum('current_credit_balance');
        $totalDebtFormatted = MoneyHelper::formatCompactCurrency($totalDebtRaw);

        // 2. Collections received today
        $collectedTodayRaw = (float) CreditLedgerEntry::whereIn('entry_type', ['payment_collection', 'payment'])
            ->whereDate('created_at', $today)
            ->sum('amount');
        $collectedTodayFormatted = MoneyHelper::formatCompactCurrency($collectedTodayRaw);

        // 3. Number of customers with open debt
        $debtorsCount = (int) Customer::inDebt()->count();

        // 4. Top 5 debtors (strictly excluding sensitive national_id)
        $topDebtors = Customer::inDebt()
            ->select(['id', 'name', 'phone', 'current_credit_balance', 'credit_limit'])
            ->orderByDesc('current_credit_balance')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'total_outstanding' => [
                    'raw'     => $totalDebtRaw,
                    'compact' => $totalDebtFormatted['compact'],
                    'exact'   => $totalDebtFormatted['exact'],
                ],
                'collected_today'   => [
                    'raw'     => $collectedTodayRaw,
                    'compact' => $collectedTodayFormatted['compact'],
                    'exact'   => $collectedTodayFormatted['exact'],
                ],
                'debtors_count'     => $debtorsCount,
                'top_debtors'       => DebtorResource::collection($topDebtors),
            ],
        ]);
    }
}
