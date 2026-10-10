<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Contracts\Purchases\SupplierServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Admin\Suppliers\UpdateSupplierRequest;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(
        protected SupplierServiceInterface $supplierService,
        protected PurchaseServiceInterface $purchaseService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $suppliers = $this->supplierService->getPaginatedSuppliers($request->only(['search', 'is_active']));

        if ($request->wantsJson()) {
            return response()->json($suppliers);
        }

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        $products = Product::where('is_active', true)->orderBy('name')->get();
        return view('admin.suppliers.create', compact('products'));
    }

    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $supplier = $this->supplierService->createSupplier($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل المورد بنجاح.',
                'data'    => $supplier,
            ], 201);
        }

        return redirect()->route('admin.suppliers.index')
            ->with('status', 'تمت إضافة المورد الجديد بنجاح.');
    }

    public function show(Supplier $supplier): View
    {
        // Eager load products relation to satisfy preventLazyLoading and to render catalog table
        $supplier->load('products');
        $stats = $this->supplierService->getSupplierStatistics($supplier->id);
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('admin.suppliers.show', compact('supplier', 'stats', 'products'));
    }

    public function edit(Supplier $supplier): View
    {
        $supplier->load('products');
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('admin.suppliers.edit', compact('supplier', 'products'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $updated = $this->supplierService->updateSupplier($supplier->id, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المورد بنجاح.',
                'data'    => $updated,
            ]);
        }

        return redirect()->route('admin.suppliers.show', $supplier)
            ->with('status', 'تم تحديث بيانات المورد بنجاح.');
    }

    public function destroy(Supplier $supplier): RedirectResponse|JsonResponse
    {
        try {
            $this->supplierService->deleteSupplier($supplier->id);

            if (request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'تم حذف المورد بنجاح.']);
            }

            return redirect()->route('admin.suppliers.index')
                ->with('status', 'تم حذف المورد بنجاح.');
        } catch (\DomainException $e) {
            if (request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function ledger(Request $request, Supplier $supplier): View
    {
        $statement = $this->purchaseService->getSupplierLedgerStatement(
            $supplier->id,
            $request->get('date_from'),
            $request->get('date_to')
        );

        return view('admin.suppliers.ledger', compact('supplier', 'statement'));
    }

    public function recordPayment(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque'],
            'cheque_number'  => ['nullable', 'string', 'max:50'],
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required'         => 'مبلغ الدفعة مطلوب.',
            'payment_method.required' => 'طريقة السداد مطلوبة.',
        ]);

        try {
            $entry = $this->purchaseService->recordSupplierPayment(
                $supplier->id,
                (float) $validated['amount'],
                $validated['payment_method'],
                [
                    'cheque_number'  => $validated['cheque_number'] ?? null,
                    'receipt_number' => $validated['receipt_number'] ?? null,
                    'notes'          => $validated['notes'] ?? null,
                    'paid_by'        => auth()->id(),
                ]
            );
        } catch (\DomainException|\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['payment_error' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل سند الصرف وسداد دفعة المورد بنجاح.',
                'data'    => $entry,
            ]);
        }

        return redirect()->back()->with('status', 'تم تسجيل سند الصرف وسداد الدفعة بنجاح.');
    }
}
