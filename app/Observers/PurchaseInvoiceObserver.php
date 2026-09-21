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
            // 1. زيادة رصيد المخزون وتحديث سعر التكلفة للأصناف الموردة
            foreach ($invoice->items as $item) {
                $product = $item->product;
                if ($product) {
                    $product->increment('current_stock', $item->quantity);
                    $product->update(['cost_price' => $item->unit_cost_price]);
                }
            }

            // 2. تحديث حساب المورد ودفتر الأستاذ في حالة الآجل أو السداد الجزئي
            $supplier = $invoice->supplier;
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
