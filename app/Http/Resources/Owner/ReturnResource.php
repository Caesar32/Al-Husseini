<?php

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $updatedAt = $this->updated_at ?? $this->created_at;
        $timeString = $updatedAt ? $updatedAt->format('h:i') . ' ' . ($updatedAt->format('A') === 'AM' ? 'ص' : 'م') : null;

        return [
            'id'              => $this->id,
            'invoice_number'  => $this->invoice_number,
            'customer_name'   => $this->customer?->name ?? 'عميل نقدي',
            'customer_phone'  => $this->customer?->phone,
            'cashier_name'    => $this->cashier?->name ?? 'كاشير',
            'status'          => $this->status,
            'time'            => $timeString,
            'updated_at'      => $updatedAt?->toIso8601String(),
            'refunded_amount' => [
                'raw'       => (float) $this->refunded_amount,
                'formatted' => number_format((float) $this->refunded_amount, 2) . ' ج.م',
            ],
            'original_amount' => [
                'raw'       => (float) $this->final_amount,
                'formatted' => number_format((float) $this->final_amount, 2) . ' ج.م',
            ],
            'returned_items'  => $this->whenLoaded('items', function () {
                return $this->items->filter(fn($item) => $item->returned_quantity > 0)->map(function ($item) {
                    return [
                        'product_name'      => $item->product?->name ?? 'صنف غير محدد',
                        'returned_quantity' => (int) $item->returned_quantity,
                        'unit_price'        => (float) $item->unit_price,
                        'total_refunded'    => (float) ($item->returned_quantity * $item->unit_price),
                        'formatted_total'   => number_format((float) ($item->returned_quantity * $item->unit_price), 2) . ' ج.م',
                    ];
                })->values();
            }),
        ];
    }
}
