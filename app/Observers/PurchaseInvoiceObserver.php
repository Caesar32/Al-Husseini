<?php

namespace App\Observers;

use App\Models\PurchaseInvoice;
use App\Models\SupplierLedgerEntry;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceObserver
{
    public function created(PurchaseInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // ─── Eager Load لمنع N+1 داخل الـ Observer ───────────────────────────
            $invoice->load(['items.product', 'supplier']);
            // ──────────────────────────────────────────────────────────────────────

            // 1. زيادة رصيد المخزون وحساب المتوسط المرجح (WAC) وتحديث كتالوج المورد
            foreach ($invoice->items as $item) {
                $product = $item->product; // محمّل مسبقاً
                if ($product) {
                    $oldStock = max(0, (int) $product->current_stock);
                    $oldCost = (float) $product->cost_price;
                    $receivedQty = (int) $item->quantity;
                    $receivedCost = (float) $item->unit_cost_price;

                    $newStock = $oldStock + $receivedQty;
                    $newCostPrice = $newStock > 0
                        ? (($oldStock * $oldCost) + ($receivedQty * $receivedCost)) / $newStock
                        : $receivedCost;

                    $product->update([
                        'current_stock' => $newStock,
                        'cost_price' => round($newCostPrice, 2),
                    ]);

                    // تحديث أو إنشاء سجل كتالوج المورد
                    \App\Models\SupplierProduct::updateOrCreate(
                        [
                            'supplier_id' => $invoice->supplier_id,
                            'product_id'  => $product->id,
                        ],
                        [
                            'last_purchase_price' => $receivedCost,
                            'is_primary_supplier' => true,
                        ]
                    );
                }
            }

            // 2. تحديث حساب المورد ودفتر الأستاذ في حالة الآجل أو السداد الجزئي
            $supplier = $invoice->supplier; // محمّل مسبقاً
            if ($supplier) {
                $balanceBefore = $supplier->current_balance;
                $balanceAfter = $balanceBefore + $invoice->remaining_amount;

                // تحديث رصيد المورد الدائن
                $supplier->update(['current_balance' => $balanceAfter]);

                // تسجيل حركة الفاتورة في دفتر أستاذ المورد
                if ($invoice->remaining_amount > 0) {
                    SupplierLedgerEntry::create([
                        'supplier_id' => $supplier->id,
                        'purchase_invoice_id' => $invoice->id,
                        'entry_type' => 'purchase_invoice',
                        'amount' => $invoice->remaining_amount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'payment_method' => 'cash',
                        'paid_by' => $invoice->received_by,
                        'notes' => "استحقاق آجل لفاتورة توريد رقم {$invoice->invoice_number}",
                    ]);
                }
            }
        });
    }
}
