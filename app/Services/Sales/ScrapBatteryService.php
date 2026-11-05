<?php

namespace App\Services\Sales;

use App\Contracts\Sales\ScrapBatteryServiceInterface;
use App\Models\ScrapBatteriesInventory;
use App\Models\ScrapPricingTier;
use App\Models\ScrapSale;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ScrapBatteryService implements ScrapBatteryServiceInterface
{
    public function __construct(
        private DocumentNumberService $documentNumbers,
    ) {}

    public function getPaginatedInventory(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ScrapBatteriesInventory::query()
            ->with([
                'branch:id,name',
                'invoice:id,invoice_number,customer_id',
                'invoice.customer:id,name,phone',
                'receivedByEmployee:id,full_name',
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['capacity_ah'])) {
            $query->where('capacity_ah', $filters['capacity_ah']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['batch_number'])) {
            $query->where('batch_number', $filters['batch_number']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function getInventoryMetrics(?int $branchId): array
    {
        $inStockBatteries = ScrapBatteriesInventory::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'in_stock')
            ->get();

        $totalUnits = $inStockBatteries->count();
        $totalScrapValue = (float) $inStockBatteries->sum('scrap_value');
        $totalLeadWeightKg = (float) $inStockBatteries->sum('lead_weight_kg');
        $totalMetricTons = round($totalLeadWeightKg / 1000, 3);

        $capacityBreakdown = $inStockBatteries
            ->groupBy('capacity_ah')
            ->map(function ($items, $capacity) {
                return [
                    'capacity'    => $capacity,
                    'count'       => $items->count(),
                    'total_value' => (float) $items->sum('scrap_value'),
                    'weight_kg'   => (float) $items->sum('lead_weight_kg'),
                ];
            })
            ->sortBy(fn (array $row) => (int) $row['capacity'])
            ->values()
            ->all();

        return [
            'branch_id'             => $branchId,
            'total_units'           => $totalUnits,
            'total_scrap_value'     => round($totalScrapValue, 2),
            'total_lead_weight_kg'  => round($totalLeadWeightKg, 2),
            'total_metric_tons'     => $totalMetricTons,
            'capacity_breakdown'    => $capacityBreakdown,
        ];
    }

    public function dispatchScrapSaleBatch(array $data, int $authorizedByUserId): array
    {
        return DB::transaction(function () use ($data, $authorizedByUserId) {
            $batteries = $this->lockBatteriesForSale($data);
            $batteryIds = $batteries->pluck('id')->all();

            foreach ($batteries as $bat) {
                if ($bat->status !== 'in_stock') {
                    throw new \DomainException("البطارية رقم ({$bat->id}) تم بيعها أو تخريدها مسبقاً.");
                }
            }

            $batchNumber = $this->documentNumbers->nextFormatted('SCRAP-BATCH-' . now()->format('Ymd'), 4);
            $totalCostValue = round((float) $batteries->sum('scrap_value'), 2);
            $totalSalePrice = round((float) ($data['total_amount'] ?? 0), 2);
            $grossProfit = round($totalSalePrice - $totalCostValue, 2);

            // The batch is recorded as a sale (buyer, amount, method). Linking it to a cash or
            // treasury account is an open owner decision (D6); none exists in the system.
            $branchIds = $batteries->pluck('branch_id')->unique();
            $sale = ScrapSale::create([
                'batch_number'    => $batchNumber,
                'branch_id'       => $branchIds->count() === 1 ? $branchIds->first() : null,
                'buyer_name'      => $data['buyer_name'],
                'buyer_phone'     => $data['buyer_phone'] ?? null,
                'payment_method'  => $data['payment_method'] ?? null,
                'batteries_count' => $batteries->count(),
                'total_amount'    => $totalSalePrice,
                'cost_value'      => $totalCostValue,
                'gross_profit'    => $grossProfit,
                'sold_by'         => $authorizedByUserId,
                'notes'           => $data['notes'] ?? null,
            ]);

            // Update status of all items in batch
            ScrapBatteriesInventory::whereIn('id', $batteryIds)->update([
                'status'        => 'sold_to_factory',
                'batch_number'  => $batchNumber,
                'scrap_sale_id' => $sale->id,
            ]);

            return [
                'scrap_sale_id'    => $sale->id,
                'batch_number'     => $batchNumber,
                'batteries_count'  => $batteries->count(),
                'buyer_name'       => $data['buyer_name'],
                'total_cost_value' => round($totalCostValue, 2),
                'total_sale_price' => round($totalSalePrice, 2),
                'gross_profit'     => $grossProfit,
                'authorized_by'    => $authorizedByUserId,
            ];
        });
    }

    /** The batteries of one sale, locked: the given ids, the whole stock, or the oldest N of it. */
    private function lockBatteriesForSale(array $data): Collection
    {
        $mode = $data['selection_mode'] ?? 'selected';

        if ($mode === 'selected') {
            $ids = array_values(array_unique(array_map('intval', $data['scrap_battery_ids'] ?? [])));
            if (empty($ids)) {
                throw new \InvalidArgumentException('يجب تحديد بطاريات كهنة لبيعها.');
            }

            $batteries = ScrapBatteriesInventory::whereIn('id', $ids)->lockForUpdate()->get();
            if ($batteries->count() !== count($ids)) {
                throw new \DomainException('بعض البطاريات المحددة غير موجودة.');
            }

            return $batteries;
        }

        $stock = ScrapBatteriesInventory::query()
            ->where('status', 'in_stock')
            ->when($data['branch_id'] ?? null, fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($stock->isEmpty()) {
            throw new \DomainException('لا توجد بطاريات كهنة في رصيد المخزن لبيعها.');
        }

        if ($mode === 'all') {
            return $stock;
        }

        $count = (int) ($data['batch_count'] ?? 0);
        if ($count < 1) {
            throw new \InvalidArgumentException('يجب تحديد عدد البطاريات المراد بيعها.');
        }
        if ($count > $stock->count()) {
            throw new \DomainException("العدد المطلوب ({$count}) أكبر من رصيد المخزن الحالي ({$stock->count()}).");
        }

        return $stock->take($count)->values();
    }

    public function updatePricingTiers(array $tiers): void
    {
        DB::transaction(function () use ($tiers) {
            foreach ($tiers as $tierData) {
                if (isset($tierData['id'])) {
                    $tier = ScrapPricingTier::find($tierData['id']);
                    if ($tier) {
                        $tier->update([
                            'default_scrap_price' => $tierData['default_scrap_price'],
                            'is_active'           => $tierData['is_active'] ?? $tier->is_active,
                        ]);
                    }
                }
            }
        });
    }
}
