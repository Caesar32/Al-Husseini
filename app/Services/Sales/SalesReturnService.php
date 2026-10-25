<?php

namespace App\Services\Sales;

use App\Contracts\Catalog\StockLedgerInterface;
use App\Contracts\Sales\SalesReturnServiceInterface;
use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Jobs\DispatchOwnerPushNotification;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\Warranty;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * Sales returns: per-line return quantities, stock back through the ledger, credit/cash
 * refund split and warranty voiding. Moved verbatim from PosOrderService (Phase 4).
 */
class SalesReturnService implements SalesReturnServiceInterface
{
    private const EPSILON = 0.01;

    public function __construct(
        private StockLedgerInterface $stock,
    ) {}

    private function epsilon(): float
    {
        return (float) config('finance.epsilon', self::EPSILON);
    }

    public function processSalesReturn(int $invoiceId, array $items, string $reason, int $cashierUserId): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $items, $reason, $cashierUserId) {
            // The invoice row lock serializes concurrent returns on the same invoice.
            $invoice = Invoice::with(['items.product', 'customer'])->lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->status === InvoiceStatus::Refunded->value) {
                throw new \DomainException('لا يمكن إجراء مرتجع على فاتورة مُرتجعة مسبقاً.');
            }
            if ($invoice->status === InvoiceStatus::Cancelled->value) {
                throw new \DomainException('لا يمكن إجراء مرتجع على فاتورة ملغاة.');
            }

            $eps = $this->epsilon();
            $requestedByLine = $this->resolveReturnLines($invoice, $items);

            $refundTotal = 0.0;
            foreach ($requestedByLine as $lineId => $qtyMilli) {
                /** @var InvoiceItem $invItem */
                $invItem = $invoice->items->firstWhere('id', $lineId);
                $returnableMilli = Quantity::toMilli($invItem->returnableQuantity());

                // Cumulative guard: earlier returns on this line are subtracted (BIZ-03).
                if ($qtyMilli > $returnableMilli) {
                    throw new \DomainException(sprintf(
                        'كمية المرتجع للصنف (%s) تتجاوز الكمية المتاحة للإرجاع: %s من أصل %s (سبق إرجاع %s).',
                        $invItem->product?->name ?? $invItem->product_id,
                        Quantity::format(Quantity::fromMilli($returnableMilli)),
                        Quantity::format($invItem->quantity),
                        Quantity::format($invItem->returned_quantity)
                    ));
                }

                $invItem->product?->quantityMilliOrFail(Quantity::fromMilli($qtyMilli));

                $product = Product::where('id', $invItem->product_id)->lockForUpdate()->firstOrFail();
                $qty = Quantity::fromMilli($qtyMilli);
                $this->stock->move($product->id, $qty, StockMovementType::SaleReturn, $invoice, $cashierUserId);

                $invItem->update([
                    'returned_quantity' => Quantity::fromMilli(
                        Quantity::toMilli($invItem->returned_quantity) + $qtyMilli
                    ),
                ]);

                // Valued at the line unit price. Proration of invoice discount/scrap/tax is an
                // owner decision (D3) and intentionally not applied here.
                $refundTotal += Quantity::lineTotal($qty, $invItem->unit_price);

                // Void warranty if battery
                if (! empty($invItem->battery_serial_number)) {
                    Warranty::where('serial_number', $invItem->battery_serial_number)
                        ->update(['status' => 'voided', 'notes' => "تم إلغاء الضمان بسبب مرتجع الفاتورة. السبب: {$reason}"]);
                }
            }

            $refundTotal = round($refundTotal, 2);

            // Invariant (BIZ-04 partial): cumulative refunds can never exceed what the invoice is worth
            // to the customer right now — money received (paid_amount) plus debt still owed (remaining_amount).
            // Both already reflect earlier returns, so this caps the total across all returns at final_amount.
            $refundable = round(max(0, (float) $invoice->paid_amount + (float) $invoice->remaining_amount), 2);
            $capNote = '';
            if ($refundTotal > $refundable + $eps) {
                $capNote = sprintf(' (قيمة الأصناف %s ج.م تم تحديدها بالمتاح للاسترداد %s ج.م)', number_format($refundTotal, 2), number_format($refundable, 2));
                $refundTotal = $refundable;
            }

            $isFullReturn = $invoice->items->every(
                fn (InvoiceItem $line) => Quantity::toMilli($line->returnableQuantity()) === 0
            );

            // ─── إصلاح محاسبة الآجل والنقدي عند المرتجع ───────────────────────────────
            $creditRefund = 0.0;
            $cashRefund = 0.0;

            if ($invoice->customer_id) {
                $creditRefund = min($refundTotal, max(0, (float) $invoice->remaining_amount));
                $cashRefund = round(max(0, $refundTotal - $creditRefund), 2);
            } else {
                // Walk-in customer: all refund is cash
                $cashRefund = $refundTotal;
            }

            // Credit portion: reduce customer balance and ledger
            if ($creditRefund > 0.01 && $invoice->customer_id) {
                $customer = Customer::where('id', $invoice->customer_id)->lockForUpdate()->firstOrFail();
                $balanceBefore = (float) $customer->current_credit_balance;
                $balanceAfter = max(0, round($balanceBefore - $creditRefund, 2));

                $customer->update(['current_credit_balance' => $balanceAfter]);

                CreditLedgerEntry::create([
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'entry_type' => 'refund',
                    'amount' => $creditRefund,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'collected_by' => $cashierUserId,
                    'notes' => "تسوية آجل ناتجة عن مرتجع فاتورة رقم {$invoice->invoice_number}. السبب: {$reason}",
                ]);

                $invoice->remaining_amount = max(0, round((float) $invoice->remaining_amount - $creditRefund, 2));
            }

            // Cash portion: create negative payment and reduce paid_amount
            if ($cashRefund > 0.01) {
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'payment_method' => 'cash',
                    'amount' => -$cashRefund,
                    'transaction_reference' => 'REFUND-'.$invoice->invoice_number,
                    'notes' => "مرتجع نقدي لفاتورة {$invoice->invoice_number}. السبب: {$reason}",
                ]);

                $invoice->paid_amount = max(0, round((float) $invoice->paid_amount - $cashRefund, 2));
            }

            $invoice->refunded_amount = round((float) $invoice->refunded_amount + $refundTotal, 2);

            // Persist remaining/paid adjustments if needed (already set on model)
            // Ensure we save the model fields before status update
            $invoice->save();

            // ─────────────────────────────────────────────────────────────────────────────

            // Fully refunded only when every unit of every line has been returned.
            $newStatus = $isFullReturn ? InvoiceStatus::Refunded->value : InvoiceStatus::PartiallyRefunded->value;

            $invoice->update([
                'status' => $newStatus,
                'notes' => trim(($invoice->notes ?? '')."\nمرتجع بقيمة {$refundTotal} ج.م{$capNote}. السبب: {$reason}"),
            ]);

            // Owner alert: every sales return/refund is reported, regardless of size.
            DispatchOwnerPushNotification::dispatch(
                'تسجيل مرتجع مبيعات 🔄',
                "تم تسجيل مرتجع على الفاتورة رقم {$invoice->invoice_number} بقيمة ".number_format($refundTotal, 2).' ج.م',
                ['type' => 'sales_return', 'invoice_id' => $invoice->id, 'amount' => $refundTotal, 'status' => $newStatus]
            );

            return $invoice->fresh(['items.product', 'customer', 'payments']);
        });
    }

    /**
     * Map requested return rows to invoice lines and sum quantities per line.
     * Each row identifies its line by invoice_item_id (preferred) or, for older callers,
     * by product_id when that product appears on exactly one line of the invoice.
     *
     * @return array<int, int> invoice_item_id => quantity in milli-units
     */
    private function resolveReturnLines(Invoice $invoice, array $items): array
    {
        if (empty($items)) {
            throw new \DomainException('يرجى تحديد الأصناف المراد إرجاعها.');
        }

        $perLine = [];
        foreach ($items as $row) {
            $qtyMilli = Quantity::milliOrFail($row['quantity'] ?? null);

            if (! empty($row['invoice_item_id'])) {
                $line = $invoice->items->firstWhere('id', (int) $row['invoice_item_id']);
                if (! $line) {
                    throw new \DomainException('البند المحدد للمرتجع لا ينتمي إلى هذه الفاتورة.');
                }
            } elseif (! empty($row['product_id'])) {
                $lines = $invoice->items->where('product_id', (int) $row['product_id']);
                if ($lines->isEmpty()) {
                    throw new \DomainException('الصنف المحدد للمرتجع غير موجود في هذه الفاتورة.');
                }
                if ($lines->count() > 1) {
                    throw new \DomainException('الصنف مكرر في أكثر من بند بالفاتورة؛ يرجى تحديد البند (invoice_item_id).');
                }
                $line = $lines->first();
            } else {
                throw new \DomainException('يجب تحديد البند أو الصنف المراد إرجاعه.');
            }

            $perLine[$line->id] = ($perLine[$line->id] ?? 0) + $qtyMilli;
        }

        return $perLine;
    }
}
