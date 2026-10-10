<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pos\StorePosInvoiceRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ScrapPricingTier;
use App\Services\Sales\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PosController extends Controller
{
    private const CACHE_TTL_SECONDS = 300;
    private const FEATURED_PRODUCTS_LIMIT = 36;

    /** Emoji shown on a category pill; unlisted categories fall back to a generic icon. */
    private const CATEGORY_ICONS = [
        'batteries'  => '🔋',
        'oils'       => '🛢️',
        'filters'    => '🧰',
        'brakes'     => '🛑',
        'suspension' => '⚙️',
        'belts'      => '🔗',
        'electrical' => '⚡',
        'engine'     => '🔧',
        'cooling-ac' => '❄️',
        'greases'    => '🧪',
        'services'   => '🛠️',
        'general'    => '📦',
    ];

    public function __construct(
        protected PosOrderServiceInterface $posOrderService
    ) {}

    public function index(): View
    {
        // Instant paint only: a small curated set, never the full catalog (was 7,340 products /
        // 5MB+ embedded JSON — the live catalog now loads on demand via searchProducts()).
        $products = $this->featuredProducts(self::FEATURED_PRODUCTS_LIMIT)
            ->map(fn (Product $p) => $this->mapPosProduct($p))
            ->values();

        $categories = $this->categoryStats();

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
        return view($viewName, compact('products', 'customers', 'technicians', 'scrapTiers', 'categories'));
    }

    /**
     * Live catalog search behind the POS screen: paginated, indexed-column search, with an
     * `exact_barcode` fast path for hardware scanners. Replaces embedding all 7,340 products.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'             => ['nullable', 'string', 'max:100'],
            'category'      => ['nullable', 'string', 'max:50'],
            'page'          => ['nullable', 'integer', 'min:1'],
            'per_page'      => ['nullable', 'integer', 'min:1', 'max:100'],
            'exact_barcode' => ['nullable', 'boolean'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $category = $validated['category'] ?? 'all';
        $perPage = (int) ($validated['per_page'] ?? self::FEATURED_PRODUCTS_LIMIT);
        $page = (int) ($validated['page'] ?? 1);
        $exactBarcode = (bool) ($validated['exact_barcode'] ?? false);

        $products = Product::query()
            ->select(['id', 'name', 'brand', 'sku', 'barcode', 'capacity_ah', 'retail_price', 'current_stock', 'category_id', 'is_battery', 'warranty_months'])
            ->where('is_active', true)
            ->with('category:id,slug,name');

        if ($exactBarcode) {
            // A scanned code may be the product's own barcode/sku, or a supplier's carton code.
            if ($query === '') {
                $products->whereRaw('1 = 0'); // nothing to match; return an empty result cleanly
            } else {
                $products->where(function ($w) use ($query) {
                    $w->where('barcode', $query)
                      ->orWhere('sku', $query)
                      ->orWhereHas('suppliers', fn ($s) => $s->where('supplier_products.supplier_sku', $query));
                });
            }
        } else {
            if ($category !== 'all' && $category !== '') {
                $products->whereHas('category', fn ($c) => $c->where('slug', $category));
            }
            if ($query !== '') {
                $products->where(function ($w) use ($query) {
                    $w->where('name', 'like', "%{$query}%")
                      ->orWhere('brand', 'like', "%{$query}%")
                      ->orWhere('sku', 'like', "%{$query}%")
                      ->orWhere('barcode', 'like', "%{$query}%");
                });
            }
        }

        $products->orderByDesc('is_battery')->orderBy('name');

        $paginator = $products->paginate($perPage, ['*'], 'page', $page);
        $stats = $this->categoryStats();
        $categoryCounts = $stats->keyBy('slug')->map(fn ($c) => $c['count'])->put('all', $stats->sum('count'));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Product $p) => $this->mapPosProduct($p))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
            'category_counts' => $categoryCounts,
        ]);
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

    /**
     * Browser-facing POS product shape (no cost prices, national ids or other internal columns).
     * Shared by the initial-paint list and every search response so the frontend has one contract.
     */
    private function mapPosProduct(Product $p): array
    {
        return [
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
        ];
    }

    /**
     * A curated "fast movers" list for the instant first paint: the best-selling products (by
     * historical net units sold), topped up with batteries if sales history doesn't cover the
     * limit yet (e.g. right after a catalog import, or before stock counts are entered). Prefers
     * in-stock items via ordering rather than excluding out-of-stock ones outright — the old
     * catalog showed every active product (with a "نفذ من المخزن" badge), and a hard stock>0
     * filter would paint an empty screen whenever current_stock hasn't been reconciled yet.
     * Cached briefly: a convenience shortlist, not a real-time figure — full search hits the DB live.
     */
    private function featuredProducts(int $limit): Collection
    {
        $featuredIds = Cache::remember('pos.featured_product_ids', self::CACHE_TTL_SECONDS, function () {
            return InvoiceItem::query()
                ->selectRaw('product_id, SUM(quantity) as sold_qty')
                ->whereHas('invoice', fn ($q) => $q->countable())
                ->groupBy('product_id')
                ->orderByDesc('sold_qty')
                ->limit(100)
                ->pluck('product_id');
        });

        $byId = Product::whereIn('id', $featuredIds)
            ->where('is_active', true)
            ->with('category:id,slug,name')
            ->get()
            ->keyBy('id');

        // Preserve the popularity order from the query above (keyBy loses it).
        $products = collect($featuredIds)
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->values();

        if ($products->count() < $limit) {
            $fill = Product::where('is_active', true)
                ->whereNotIn('id', $products->pluck('id'))
                ->with('category:id,slug,name')
                ->orderByDesc('is_battery')
                ->orderByDesc('current_stock')
                ->orderBy('name')
                ->limit($limit - $products->count())
                ->get();
            $products = $products->concat($fill);
        }

        return $products->take($limit);
    }

    /**
     * Per-category active-product counts, cached briefly. Feeds both the initial pill render
     * and every search response (so pills stay accurate without re-fetching on each request).
     *
     * @return Collection<int, array{slug: string, name: string, count: int, icon: string}>
     */
    private function categoryStats(): Collection
    {
        return Cache::remember('pos.category_stats', self::CACHE_TTL_SECONDS, function () {
            return Category::withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->get(['id', 'slug', 'name'])
                ->filter(fn (Category $c) => $c->products_count > 0)
                ->sortByDesc('products_count')
                ->map(fn (Category $c) => [
                    'slug'  => $c->slug,
                    'name'  => $c->name,
                    'count' => $c->products_count,
                    'icon'  => self::CATEGORY_ICONS[$c->slug] ?? '📦',
                ])
                ->values();
        });
    }
}
