<?php

namespace App\Http\Resources\Owner;

use App\Support\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'product_id'               => $this->product_id,
            'product_name'             => $this->product?->name ?? 'صنف غير محدد',
            'sku'                      => $this->product?->sku,
            'barcode'                  => $this->product?->barcode,
            'is_battery'               => (bool) ($this->product?->is_battery ?? false),
            'quantity'                 => Quantity::normalize($this->quantity),
            'returned_quantity'        => Quantity::normalize($this->returned_quantity),
            'battery_serial_number'    => $this->battery_serial_number,
            'warranty_duration_months' => $this->warranty_duration_months,
            'unit_price'               => [
                'raw'       => (float) $this->unit_price,
                'formatted' => number_format((float) $this->unit_price, 2) . ' ج.م',
            ],
            'total_price'              => [
                'raw'       => (float) $this->total_price,
                'formatted' => number_format((float) $this->total_price, 2) . ' ج.م',
            ],
        ];
    }
}
