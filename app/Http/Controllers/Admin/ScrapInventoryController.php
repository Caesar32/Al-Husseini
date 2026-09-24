<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Sales\ScrapBatteryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scrap\StoreScrapSaleBatchRequest;
use App\Models\Branch;
use App\Models\ScrapPricingTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScrapInventoryController extends Controller
{
    public function __construct(
        protected ScrapBatteryServiceInterface $scrapBatteryService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $branchId = (int) ($request->get('branch_id') ?: (auth()->user()?->branch_id ?: 1));

        $metrics = $this->scrapBatteryService->getInventoryMetrics($branchId);
        $inventory = $this->scrapBatteryService->getPaginatedInventory($request->only(['status', 'capacity_ah', 'batch_number']));

        if ($request->wantsJson()) {
            return response()->json([
                'metrics'   => $metrics,
                'inventory' => $inventory,
            ]);
        }

        $tiers = ScrapPricingTier::orderBy('capacity_min_ah')->get();
        $branches = Branch::where('is_active', true)->get();

        return view('admin.scrap.index', compact('metrics', 'inventory', 'tiers', 'branches', 'branchId'));
    }

    public function sellBatch(StoreScrapSaleBatchRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $result = $this->scrapBatteryService->dispatchScrapSaleBatch($request->validated(), auth()->id() ?? 1);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "تم بيع وتسجيل خروج شحنة الكهنة برقم تشغيلة ({$result['batch_number']}) بنجاح.",
                    'data'    => $result,
                ], 201);
            }

            return redirect()->route('admin.scrap.index')
                ->with('status', "تم بيع شحنة الكهنة بنجاح برقم ({$result['batch_number']}). أرباح الشحنة: " . number_format($result['gross_profit'], 2) . " ج.م.");
        } catch (\DomainException $e) {
            return redirect()->back()->withErrors(['scrap_error' => $e->getMessage()]);
        }
    }

    public function updateTiers(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'tiers'                       => ['required', 'array', 'min:1'],
            'tiers.*.id'                  => ['required', 'exists:scrap_pricing_tiers,id'],
            'tiers.*.default_scrap_price' => ['required', 'numeric', 'min:0'],
            'tiers.*.is_active'           => ['sometimes', 'boolean'],
        ], [
            'tiers.required' => 'بيانات شرائح التسعير مطلوبة.',
        ]);

        $this->scrapBatteryService->updatePricingTiers($validated['tiers']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تحديث جدول تسعير الكهنة المرجعي بنجاح.',
            ]);
        }

        return redirect()->route('admin.scrap.index')
            ->with('status', 'تم حفظ أسعار شرائح بطاريات الكهنة المرجعية بنجاح.');
    }
}
