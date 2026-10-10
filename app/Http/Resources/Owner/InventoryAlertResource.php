<?php

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stock = (int) $this->current_stock;
        $threshold = (int) $this->reorder_threshold;

        if ($stock <= 0) {
            $status = 'out_of_stock';
            $statusLabel = 'نفد المخزون';
        } elseif ($threshold > 0 && $stock <= ceil($threshold / 2)) {
            $status = 'critical_low';
            $statusLabel = 'نقص حرج جداً';
        } else {
            $status = 'warning_low';
            $statusLabel = 'وصل لحد الطلب';
        }

        $depletionRatio = $threshold > 0 ? round($stock / $threshold, 2) : 0.0;

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'sku'               => $this->sku,
            'barcode'           => $this->barcode,
            'category_name'     => $this->category?->name ?? 'عام',
            'is_battery'        => (bool) $this->is_battery,
            'current_stock'     => $stock,
            'reorder_threshold' => $threshold,
            'depletion_ratio'   => $depletionRatio,
            'status'            => $status,
            'status_label'      => $statusLabel,
            'cost_price'        => [
                'raw'       => (float) $this->cost_price,
                'formatted' => number_format((float) $this->cost_price, 2) . ' ج.م',
            ],
            'retail_price'      => [
                'raw'       => (float) $this->retail_price,
                'formatted' => number_format((float) $this->retail_price, 2) . ' ج.م',
            ],
        ];
    }
}
