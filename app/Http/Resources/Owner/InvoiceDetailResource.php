<?php

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceDetailResource extends JsonResource
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

        return [
            'id'             => $this->id,
            'invoice_number' => $this->invoice_number,
            'status'         => $this->status,
            'payment_method' => $this->payment_method,
            'time'           => $timeString,
            'created_at'     => $createdAt?->toIso8601String(),
            'notes'          => $this->notes,

            'customer' => $this->customer ? [
                'id'                     => $this->customer->id,
                'name'                   => $this->customer->name,
                'phone'                  => $this->customer->phone,
                'current_credit_balance' => (float) $this->customer->current_credit_balance,
            ] : null,

            'cashier' => $this->cashier ? [
                'id'   => $this->cashier->id,
                'name' => $this->cashier->name,
            ] : null,

            'technician' => $this->technician ? [
                'id'   => $this->technician->id,
                'name' => $this->technician->full_name,
            ] : null,

            'vehicle' => $this->customerVehicle ? [
                'id'           => $this->customerVehicle->id,
                'plate_number' => $this->customerVehicle->plate_number,
                'make'         => $this->customerVehicle->make,
                'model'        => $this->customerVehicle->model,
            ] : null,

            'branch' => $this->branch ? [
                'id'   => $this->branch->id,
                'name' => $this->branch->name,
                'code' => $this->branch->code,
            ] : null,

            'financials' => [
                'subtotal' => [
                    'raw'       => (float) $this->subtotal,
                    'formatted' => number_format((float) $this->subtotal, 2) . ' ج.م',
                ],
                'discount_amount' => [
                    'raw'       => (float) $this->discount_amount,
                    'formatted' => number_format((float) $this->discount_amount, 2) . ' ج.م',
                ],
                'scrap_deduction_amount' => [
                    'raw'       => (float) $this->scrap_deduction_amount,
                    'formatted' => number_format((float) $this->scrap_deduction_amount, 2) . ' ج.م',
                ],
                'tax_amount' => [
                    'raw'       => (float) $this->tax_amount,
                    'formatted' => number_format((float) $this->tax_amount, 2) . ' ج.م',
                ],
                'final_amount' => [
                    'raw'       => (float) $this->final_amount,
                    'formatted' => number_format((float) $this->final_amount, 2) . ' ج.م',
                ],
                'paid_amount' => [
                    'raw'       => (float) $this->paid_amount,
                    'formatted' => number_format((float) $this->paid_amount, 2) . ' ج.م',
                ],
                'remaining_amount' => [
                    'raw'       => (float) $this->remaining_amount,
                    'formatted' => number_format((float) $this->remaining_amount, 2) . ' ج.م',
                ],
                'refunded_amount' => [
                    'raw'       => (float) $this->refunded_amount,
                    'formatted' => number_format((float) $this->refunded_amount, 2) . ' ج.م',
                ],
            ],

            'scrap_battery' => $this->scrapBattery ? [
                'capacity_ah'    => $this->scrapBattery->capacity_ah,
                'scrap_value'    => (float) $this->scrapBattery->scrap_value,
                'lead_weight_kg' => (float) $this->scrapBattery->lead_weight_kg,
                'status'         => $this->scrapBattery->status,
            ] : null,

            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),

            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($payment) {
                    return [
                        'id'             => $payment->id,
                        'payment_method' => $payment->payment_method,
                        'amount'         => (float) $payment->amount,
                        'formatted'      => number_format((float) $payment->amount, 2) . ' ج.م',
                        'created_at'     => $payment->created_at?->toIso8601String(),
                    ];
                });
            }),
        ];
    }
}
