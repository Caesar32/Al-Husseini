<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Contracts\Purchases\SupplierServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Purchases\StorePurchaseInvoiceRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        protected PurchaseServiceInterface $purchaseService,
        protected SupplierServiceInterface $supplierService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->only(['search', 'supplier_id', 'branch_id', 'payment_status', 'date_from', 'date_to']);
        $invoices = $this->purchaseService->getPaginatedInvoices($filters);

        if ($request->wantsJson()) {
            return response()->json($invoices);
        }

        $suppliers = $this->supplierService->getAllActiveSuppliers();
        $branches = Branch::where('is_active', true)->get();

        return view('admin.purchases.index', compact('invoices', 'suppliers', 'branches'));
    }

    public function create(): View
    {
        $suppliers = $this->supplierService->getAllActiveSuppliers();
        $products = Product::where('is_active', true)->with(['category', 'suppliers'])->get();
        $branches = Branch::where('is_active', true)->get();

        return view('admin.purchases.create', compact('suppliers', 'products', 'branches'));
    }

    public function store(StorePurchaseInvoiceRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $invoice = $this->purchaseService->createDirectPurchase($request->validated(), (int) auth()->id());
        } catch (\InvalidArgumentException|\DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['purchase_error' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل وحفظ فاتورة المشتريات وتحديث المخزون بنجاح.',
                'data'    => $invoice,
            ], 201);
        }

        return redirect()->route('admin.purchases.show', $invoice)
            ->with('status', "تم تسجيل فاتورة الشراء رقم {$invoice->invoice_number} وتحديث المخزون والمتوسط المرجح بنجاح.");
    }

    public function show(PurchaseInvoice $purchase): View
    {
        $purchase->load([
            'supplier',
            'branch',
            'receivedByUser',
            'items.product.category',
            'ledgerEntries',
        ]);

        return view('admin.purchases.show', compact('purchase'));
    }

    public function print(PurchaseInvoice $purchase): View
    {
        $purchase->load([
            'supplier',
            'branch',
            'receivedByUser',
            'items.product',
        ]);

        return view('admin.purchases.print', compact('purchase'));
    }
}
