<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CreditLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCustomerController extends Controller
{
    public function __construct(
        protected PosOrderServiceInterface $posOrderService
    ) {}

    /**
     * عرض كشف حسابات الآجل والمديونيات (بيانات حقيقية من قاعدة البيانات).
     */
    public function index(Request $request): View|JsonResponse
    {
        // جلب العملاء المدينين مع إحصائياتهم - Eager Loading للعلاقات
        $customers = Customer::where('current_credit_balance', '>', 0)
            ->with([
                'vehicles:id,customer_id,car_brand,car_model,plate_number',
                'creditLedgers' => function ($q) {
                    $q->orderByDesc('id')->limit(5);
                },
            ])
            ->orderByDesc('current_credit_balance')
            ->get();

        // إحصائيات الإجمالي
        $totalOutstanding    = $customers->sum('current_credit_balance');
        $customersCount      = $customers->count();
        $exceededLimitCount  = $customers->filter(fn ($c) =>
            $c->credit_limit > 0 && $c->current_credit_balance > $c->credit_limit
        )->count();

        if ($request->wantsJson()) {
            return response()->json([
                'customers'          => $customers,
                'total_outstanding'  => $totalOutstanding,
                'customers_count'    => $customersCount,
                'exceeded_limit_count' => $exceededLimitCount,
            ]);
        }

        return view('admin.sales.credit', compact(
            'customers',
            'totalOutstanding',
            'customersCount',
            'exceededLimitCount'
        ));
    }

    /**
     * تحصيل دفعة آجل من عميل.
     */
    public function settlePayment(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer'],
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'customer_id.required'    => 'يجب تحديد العميل.',
            'customer_id.exists'      => 'العميل غير موجود في النظام.',
            'amount.required'         => 'مبلغ التحصيل مطلوب.',
            'amount.min'              => 'مبلغ التحصيل يجب أن يكون أكبر من الصفر.',
            'payment_method.required' => 'طريقة الدفع مطلوبة.',
            'payment_method.in'       => 'طريقة الدفع غير مدعومة.',
        ]);

        try {
            $ledgerEntry = $this->posOrderService->settleCustomerDebt(
                customerId:    (int) $validated['customer_id'],
                amount:        (float) $validated['amount'],
                paymentMethod: $validated['payment_method'],
                collectedBy:   auth()->id() ?? 1,
                receiptNumber: $validated['receipt_number'] ?? null,
                notes:         $validated['notes'] ?? null,
            );

            $customer = Customer::find($validated['customer_id']);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'         => true,
                    'message'         => "تم تسجيل تحصيل مبلغ " . number_format($validated['amount'], 2) . " ج.م من العميل {$customer->name} بنجاح.",
                    'new_balance'     => (float) $ledgerEntry->balance_after,
                    'receipt_number'  => $ledgerEntry->receipt_number,
                ]);
            }

            return redirect()->route('admin.sales.credit')
                ->with('status', "تم تسجيل تحصيل مبلغ " . number_format($validated['amount'], 2) . " ج.م بنجاح.");

        } catch (\DomainException|\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['credit_error' => $e->getMessage()]);
        }
    }

    /**
     * كشف حساب تفصيلي للعميل مع جميع حركات الآجل.
     */
    public function statement(Request $request, Customer $customer): View|JsonResponse
    {
        $customer->load('vehicles:id,customer_id,car_brand,car_model,plate_number');

        $invoices = $customer->invoices()
            ->where('remaining_amount', '>', 0.01)
            ->with(['payments', 'items.product:id,name,is_battery'])
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'invoices_page')
            ->withQueryString();

        $ledgers = $customer->creditLedgers()
            ->with('collectedByUser:id,name')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'ledgers_page')
            ->withQueryString();

        // For JSON, return paginated structure
        if ($request->wantsJson()) {
            return response()->json([
                'customer' => $customer,
                'invoices' => $invoices,
                'credit_ledgers' => $ledgers,
            ]);
        }

        // Keep backward compat: set relations to paginator items for Blade @forelse
        $customer->setRelation('invoices', $invoices->getCollection());
        $customer->setRelation('creditLedgers', $ledgers->getCollection());

        return view('admin.credit.statement', compact('customer', 'invoices', 'ledgers'));
    }
}
