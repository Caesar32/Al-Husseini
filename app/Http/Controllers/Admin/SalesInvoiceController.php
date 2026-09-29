<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function __construct(
        protected PosOrderServiceInterface $posOrderService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->only(['search', 'status', 'branch_id', 'date_from', 'date_to']);
        $invoices = $this->posOrderService->getPaginatedInvoices($filters);

        if ($request->wantsJson()) {
            return response()->json($invoices);
        }

        // Stats matching active date/branch/search filters
        $statsQuery = Invoice::query();
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $statsQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }
        if (!empty($filters['status'])) {
            $statsQuery->where('status', $filters['status']);
        }
        if (!empty($filters['branch_id'])) {
            $statsQuery->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['date_from'])) {
            $statsQuery->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $statsQuery->whereDate('created_at', '<=', $filters['date_to']);
        }

        $stats = [
            'total_sales'            => (float) (clone $statsQuery)->sum('final_amount'),
            'invoices_count'         => (int) (clone $statsQuery)->count(),
            'credit_invoices_count'  => (int) (clone $statsQuery)->where('remaining_amount', '>', 0)->count(),
            'total_remaining_credit' => (float) (clone $statsQuery)->sum('remaining_amount'),
            'scrap_count'            => (int) (clone $statsQuery)->where('scrap_deduction_amount', '>', 0)->count(),
        ];

        $branches = Branch::where('is_active', true)->get();
        $viewName = view()->exists('admin.invoices.index') ? 'admin.invoices.index' : 'admin.sales.invoices';

        return view($viewName, compact('invoices', 'branches', 'stats', 'filters'));
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load([
            'customer',
            'customerVehicle',
            'cashier',
            'technician',
            'branch',
            'items.product.category',
            'payments',
            'scrapBattery',
            'technicianCommission.employee',
        ]);

        return view('admin.invoices.show', compact('invoice'));
    }

    public function processReturn(Request $request, Invoice $invoice): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reason'             => ['required', 'string', 'max:500'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ], [
            'reason.required'    => 'سبب المرتجع مطلوب.',
            'items.required'     => 'يرجى تحديد الأصناف المراد إرجاعها.',
        ]);

        try {
            $updatedInvoice = $this->posOrderService->processSalesReturn(
                $invoice->id,
                $validated['items'],
                $validated['reason'],
                auth()->id() ?? 1
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم معالجة مرتجع الفاتورة وإعادة البضاعة للمخزن بنجاح.',
                    'data'    => $updatedInvoice,
                ]);
            }

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('status', 'تم تسجيل مرتجع الفاتورة بنجاح.');
        } catch (\DomainException $e) {
            return redirect()->back()->withErrors(['return_error' => $e->getMessage()]);
        }
    }
}
