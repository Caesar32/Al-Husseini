<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pos\StorePosInvoiceRequest;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ScrapPricingTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected PosOrderServiceInterface $posOrderService
    ) {}

    public function index(): View
    {
        $products = Product::where('is_active', true)
            ->with([
                'category',
                // FIX-5: تحميل بيانات الموردين مع كود السكو الخاص بالمورد لتفعيل البحث بباركود كراتين المورد
                'suppliers' => function ($q) {
                    $q->select('suppliers.id', 'suppliers.name')
                      ->withPivot(['supplier_sku', 'last_purchase_price', 'is_primary_supplier']);
                },
            ])
            ->orderBy('is_battery', 'desc')
            ->orderBy('name')
            ->get();

        $customers = Customer::where('is_active', true)
            ->with(['vehicles'])
            ->orderBy('name')
            ->get();

        $technicians = Employee::where('status', 'active')
            ->orderBy('full_name')
            ->get();

        $scrapTiers = ScrapPricingTier::where('is_active', true)
            ->orderBy('capacity_min_ah')
            ->get();

        $viewName = view()->exists('admin.pos.index') ? 'admin.pos.index' : 'admin.sales.pos';
        return view($viewName, compact('products', 'customers', 'technicians', 'scrapTiers'));
    }

    public function store(StorePosInvoiceRequest $request): JsonResponse|RedirectResponse
    {
        // Route is behind `auth`; never attribute a sale to a fallback user.
        $cashierUserId = $request->user()->id;

        try {
            $invoice = $this->posOrderService->processPosSale($request->validated(), $cashierUserId);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'            => true,
                    'message'            => "تم إصدار الفاتورة رقم {$invoice->invoice_number} بنجاح.",
                    'invoice_id'         => $invoice->id,
                    'invoice_number'     => $invoice->invoice_number,
                    'receipt_url'        => route('admin.pos.receipt', $invoice),
                    'warranty_cert_url'  => route('admin.pos.warranty_cert', $invoice),
                ], 201);
            }

            return redirect()->route('admin.pos.receipt', $invoice)
                ->with('status', "تم إصدار الفاتورة رقم {$invoice->invoice_number} بنجاح.");
        } catch (\DomainException|\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['pos_error' => $e->getMessage()])->withInput();
        }
    }

    public function receipt(Invoice $invoice): View
    {
        $invoice->load([
            'items.product',
            'customer',
            'customerVehicle',
            'technician',
            'cashier',
            'payments',
            'branch',
            'scrapBattery',
        ]);

        return view('admin.pos.receipt', compact('invoice'));
    }

    public function warrantyCert(Invoice $invoice): View
    {
        $invoice->load([
            'items' => function ($q) {
                $q->whereNotNull('battery_serial_number')->with(['product', 'warranty']);
            },
            'customer',
            'customerVehicle',
            'technician',
            'branch',
        ]);

        return view('admin.pos.warranty_cert', compact('invoice'));
    }
}
