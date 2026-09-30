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

        // Unified filtering via InvoiceFilter (FIN-C02, FIN-M02, FIN-M03, FIN-M05, FIN-H02)
        try {
            $invoices = $this->posOrderService->getPaginatedInvoices($filters);
            $stats = $this->posOrderService->getInvoiceStats($filters);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'فلتر التواريخ غير صحيح.',
                    'errors' => $e->errors(),
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json($invoices);
        }

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
