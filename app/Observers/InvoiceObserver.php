<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\CreditLedgerEntry;
use App\Models\ScrapBatteriesInventory;
use App\Models\Warranty;
use App\Models\TechnicianCommission;
use Illuminate\Support\Facades\DB;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // ─── Eager Load لمنع N+1 داخل الـ Observer ───────────────────────────
            $invoice->load(['items.product', 'customer']);
            // ──────────────────────────────────────────────────────────────────────

            // 1. معالجة بنود الفاتورة: خصم المخزون وإنشاء الضمان
            foreach ($invoice->items as $item) {
                // خصم المخزون
                $item->product->decrement('current_stock', $item->quantity);

                // إنشاء كارت الضمان إذا توفر رقم تسلسلي للمنتج وكان بطارية
                if ($item->battery_serial_number && $invoice->customer_id) {
                    Warranty::create([
                        'invoice_item_id'       => $item->id,
                        'customer_id'           => $invoice->customer_id,
                        'customer_vehicle_id'   => $invoice->customer_vehicle_id,
                        'serial_number'         => $item->battery_serial_number,
                        'start_date'            => now()->toDateString(),
                        'end_date'              => now()->addMonths($item->warranty_duration_months)->toDateString(),
                        'status'                => 'active',
                    ]);
                }
            }

            // 2. معالجة الآجل وإضافة قيد في دفتر الأستاذ
            if ($invoice->remaining_amount > 0 && $invoice->customer_id) {
                $customer = $invoice->customer; // محمّل مسبقاً
                $balanceBefore = $customer->current_credit_balance;
                $balanceAfter  = $balanceBefore + $invoice->remaining_amount;

                // تحديث رصيد العميل
                $customer->update(['current_credit_balance' => $balanceAfter]);

                // تسجيل القيد المزدوج
                CreditLedgerEntry::create([
                    'customer_id'   => $customer->id,
                    'invoice_id'    => $invoice->id,
                    'entry_type'    => 'invoice_debt',
                    'amount'        => $invoice->remaining_amount,
                    'balance_before'=> $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'collected_by'  => $invoice->cashier_id,
                    'notes'         => "تسجيل متبقي آجل من الفاتورة رقم {$invoice->invoice_number}",
                ]);
            }

            // 3. معالجة بطارية الكهنة (Scrap Replacement)
            if ($invoice->scrap_deduction_amount > 0) {
                ScrapBatteriesInventory::create([
                    'branch_id'   => $invoice->branch_id,
                    'invoice_id'  => $invoice->id,
                    'capacity_ah' => 'Old/Replaced',
                    'scrap_value' => $invoice->scrap_deduction_amount,
                    'status'      => 'in_stock',
                    'received_by' => $invoice->technician_id ?? 1,
                ]);
            }
        });
    }
}
