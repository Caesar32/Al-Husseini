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
use App\Services\Sales\CustomerService;
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
        // The browser receives only the fields the cashier screen needs (no cost prices,
        // national ids or other internal columns). Ids are the real database ids.
        $products = Product::where('is_active', true)
            ->with([
                'category:id,slug,name',
                'suppliers' => fn ($q) => $q->select('suppliers.id')->withPivot('supplier_sku'),
            ])
            ->orderBy('is_battery', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'brand'           => $p->brand,
                'sku'             => $p->sku,
                'barcode'         => $p->barcode,
                'category'        => $p->category?->slug,
                'category_name'   => $p->category?->name,
                'is_battery'      => (bool) $p->is_battery,
                'capacity_ah'     => $p->capacity_ah,
                'stock'           => (int) $p->current_stock,
                'price'           => (float) $p->retail_price,
                'warranty_months' => (int) $p->warranty_months,
                // Supplier carton codes so a scanned supplier barcode resolves to the product.
                'supplier_skus'   => $p->suppliers->pluck('pivot.supplier_sku')->filter()->values()->all(),
            ])
            ->values();

        $customers = Customer::where('is_active', true)
            ->where('phone', '!=', CustomerService::WALK_IN_PHONE)
            ->with('vehicles:id,customer_id,plate_number,car_brand,car_model')
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'phone'          => $c->phone,
                'credit_limit'   => (float) $c->credit_limit,
                'credit_balance' => (float) $c->current_credit_balance,
                'vehicles'       => $c->vehicles->map(fn ($v) => [
                    'id'           => $v->id,
                    'plate_number' => $v->plate_number,
                    'car'          => trim($v->car_brand . ' ' . $v->car_model),
                ])->values()->all(),
            ])
            ->values();

        $technicians = Employee::where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code']);

        $scrapTiers = ScrapPricingTier::where('is_active', true)
            ->orderBy('capacity_min_ah')
            ->get()
            ->map(fn (ScrapPricingTier $t) => [
                'min_ah' => (int) $t->capacity_min_ah,
                'max_ah' => (int) $t->capacity_max_ah,
                'name'   => $t->tier_name,
                'price'  => (float) $t->default_scrap_price,
            ])
            ->values();

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
