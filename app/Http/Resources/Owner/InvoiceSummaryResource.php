<?php

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at;
        $timeString = $createdAt ? $createdAt->format('h:i') . ' ' . ($createdAt->format('A') === 'AM' ? 'ص' : 'م') : null;

        // Check if invoice has any warranties
        $hasWarranty = false;
        if ($this->relationLoaded('items')) {
            $hasWarranty = $this->items->contains(function ($item) {
                return !empty($item->warranty_duration_months) || !empty($item->battery_serial_number);
            });
        }

        return [
            'id'             => $this->id,
            'invoice_number' => $this->invoice_number,
            'time'           => $timeString,
            'created_at'     => $createdAt?->toIso8601String(),
            'customer_name'  => $this->customer?->name ?? 'عميل نقدي',
            'customer_phone' => $this->customer?->phone,
            'cashier_name'   => $this->cashier?->name ?? 'كاشير',
            'final_amount'   => [
                'raw'       => (float) $this->final_amount,
                'formatted' => number_format((float) $this->final_amount, 2) . ' ج.م',
            ],
            'items_count'    => $this->relationLoaded('items') ? $this->items->count() : (int) ($this->items_count ?? 1),
            'payment_method' => $this->payment_method ?? 'cash',
            'status'         => $this->status,
            'has_warranty'   => $hasWarranty,
        ];
    }
}
