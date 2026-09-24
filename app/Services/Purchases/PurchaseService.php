<?php

namespace App\Services\Purchases;

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierProduct;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseService implements PurchaseServiceInterface
{
    public function getPaginatedInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseInvoice::query()
            ->with([
                'supplier:id,name,company_name,phone',
                'receivedByUser:id,name',
                'branch:id,name',
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('company_name', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function createDirectPurchase(array $data, int $receivedByUserId): PurchaseInvoice
    {
        return DB::transaction(function () use ($data, $receivedByUserId) {
            $supplier = Supplier::where('id', $data['supplier_id'])->lockForUpdate()->firstOrFail();

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw new \InvalidArgumentException('فاتورة المشتريات يجب أن تحتوي على صنف واحد على الأقل.');
            }

            // 1. Bulk fetch and lock products to eliminate N+1 and race conditions
            $productIds = collect($itemsData)->pluck('product_id')->unique()->all();
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0.0;
            $preparedItems = [];

            foreach ($itemsData as $item) {
                $productId = $item['product_id'];
                $product = $products->get($productId);

                if (!$product) {
                    throw new \InvalidArgumentException("المنتج ذو المعرف ({$productId}) غير موجود.");
                }

                $qty = (int) $item['quantity'];
                $unitCost = (float) $item['unit_cost_price'];
                $totalCost = round($qty * $unitCost, 2);
                $subtotal += $totalCost;

                $preparedItems[] = [
                    'product_id'       => $productId,
                    'quantity'         => $qty,
                    'unit_cost_price'  => $unitCost,
                    'total_cost_price' => $totalCost,
                    'batch_number'     => $item['batch_number'] ?? null,
                    'production_date'  => $item['production_date'] ?? null,
                    'supplier_sku'     => $item['supplier_sku'] ?? null,
                ];
            }

            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $finalAmount = round(max(0, $subtotal + $taxAmount - $discountAmount), 2);
            $paidAmount = round((float) ($data['paid_amount'] ?? 0), 2);
            $remainingAmount = round(max(0, $finalAmount - $paidAmount), 2);

            $paymentStatus = 'unpaid';
            if ($remainingAmount <= 0.001) {
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partially_paid';
            }

            // 2. Create Purchase Invoice without triggering double observer execution
            $invoice = PurchaseInvoice::withoutEvents(function () use ($data, $receivedByUserId, $subtotal, $taxAmount, $discountAmount, $finalAmount, $paidAmount, $remainingAmount, $paymentStatus) {
                return PurchaseInvoice::create([
                    'invoice_number'   => $data['invoice_number'],
                    'supplier_id'      => $data['supplier_id'],
                    'branch_id'        => $data['branch_id'] ?? auth()->user()?->branch_id ?? 1,
                    'received_by'      => $receivedByUserId,
                    'invoice_date'     => $data['invoice_date'] ?? now()->toDateString(),
                    'subtotal'         => $subtotal,
                    'tax_amount'       => $taxAmount,
                    'discount_amount'  => $discountAmount,
                    'final_amount'     => $finalAmount,
                    'paid_amount'      => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'payment_status'   => $paymentStatus,
                    'notes'            => $data['notes'] ?? null,
                ]);
            });

            // 3. Save items and apply Weighted Average Cost (WAC) formula
            foreach ($preparedItems as $pItem) {
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id'          => $pItem['product_id'],
                    'quantity'            => $pItem['quantity'],
                    'unit_cost_price'     => $pItem['unit_cost_price'],
                    'total_cost_price'    => $pItem['total_cost_price'],
                    'batch_number'        => $pItem['batch_number'],
                    'production_date'     => $pItem['production_date'],
                ]);

                $product = $products->get($pItem['product_id']);
                $currentStock = max(0, (int) $product->current_stock);
                $currentCost = (float) $product->cost_price;
                $newQty = $pItem['quantity'];
                $newCost = $pItem['unit_cost_price'];

                $totalQty = $currentStock + $newQty;
                $newWac = $totalQty > 0
                    ? (($currentStock * $currentCost) + ($newQty * $newCost)) / $totalQty
                    : $newCost;

                $product->update([
                    'current_stock' => $currentStock + $newQty,
                    'cost_price'    => round($newWac, 2),
                ]);

                // Sync catalog entry in supplier_products
                SupplierProduct::updateOrCreate(
                    [
                        'supplier_id' => $supplier->id,
                        'product_id'  => $product->id,
                    ],
                    [
                        'supplier_sku'        => $pItem['supplier_sku'] ?? null,
                        'last_purchase_price' => $newCost,
                        'is_primary_supplier' => true,
                    ]
                );
            }

            // 4. Update Supplier ledger and current balance
            $balanceBefore = (float) $supplier->current_balance;
            $balanceAfter = $balanceBefore + $remainingAmount;

            $supplier->update([
                'current_balance' => $balanceAfter,
            ]);

            if ($remainingAmount > 0) {
                SupplierLedgerEntry::create([
                    'supplier_id'         => $supplier->id,
                    'purchase_invoice_id' => $invoice->id,
                    'entry_type'          => 'purchase_invoice',
                    'amount'              => $remainingAmount,
                    'balance_before'      => $balanceBefore,
                    'balance_after'       => $balanceAfter,
                    'payment_method'      => $data['payment_method'] ?? 'cash',
                    'paid_by'             => $receivedByUserId,
                    'notes'               => "استحقاق آجل لفاتورة توريد رقم {$invoice->invoice_number}",
                ]);
            }

            // If there's an immediate payment made on invoice reception
            if ($paidAmount > 0) {
                // balance_before للدفعة الفورية = الرصيد بعد إضافة الآجل ($balanceAfter)
                // لأن الرصيد تحرك من $balanceBefore -> $balanceAfter (بإضافة الآجل فقط)
                // ثم الدفعة الفورية تخفضه: $balanceAfter -> ($balanceAfter - $paidAmount)
                $paymentBalanceBefore = $balanceAfter; // = $balanceBefore + $remainingAmount
                $paymentBalanceAfter  = round($paymentBalanceBefore - $paidAmount, 2);

                SupplierLedgerEntry::create([
                    'supplier_id'         => $supplier->id,
                    'purchase_invoice_id' => $invoice->id,
                    'entry_type'          => 'supplier_payment',
                    'amount'              => $paidAmount,
                    'balance_before'      => $paymentBalanceBefore,
                    'balance_after'       => $paymentBalanceAfter,
                    'payment_method'      => $data['payment_method'] ?? 'cash',
                    'paid_by'             => $receivedByUserId,
                    'notes'               => "دفعة نقدية مسددة فور استلام فاتورة توريد رقم {$invoice->invoice_number}",
                ]);
            }

            return $invoice->fresh(['items.product', 'supplier', 'receivedByUser']);
        });
    }

    public function recordSupplierPayment(int $supplierId, float $amount, string $method, array $extra = []): SupplierLedgerEntry
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ الدفعة المسددة يجب أن يكون أكبر من الصفر.');
        }

        return DB::transaction(function () use ($supplierId, $amount, $method, $extra) {
            $supplier = Supplier::where('id', $supplierId)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) $supplier->current_balance;

            // نمنع السداد الزائد الواضح (تجاوز الرصيد بأكثر من 1000 ج.م) ما لم يكن مقصوداً
            // المورد قد يكون له رصيد صفر ومع ذلك يمكن تسجيل دفعة مقدمة
            if ($balanceBefore <= 0 && $amount > 1000) {
                throw new \DomainException(
                    "رصيد المورد الحالي ({$balanceBefore} ج.م) لا يسمح بسداد مبلغ ({$amount} ج.م). " .
                    "يرجى مراجعة الرصيد قبل تسجيل الدفعة."
                );
            }

            $balanceAfter = round($balanceBefore - $amount, 2);

            $supplier->update(['current_balance' => $balanceAfter]);

            return SupplierLedgerEntry::create([
                'supplier_id'         => $supplier->id,
                'purchase_invoice_id' => $extra['purchase_invoice_id'] ?? null,
                'entry_type'          => 'supplier_payment',
                'amount'              => $amount,
                'balance_before'      => $balanceBefore,
                'balance_after'       => $balanceAfter,
                'payment_method'      => $method,
                'cheque_number'       => $extra['cheque_number'] ?? null,
                'paid_by'             => $extra['paid_by'] ?? auth()->id() ?? 1,
                'receipt_number'      => $extra['receipt_number'] ?? null,
                'notes'               => $extra['notes'] ?? 'سند صرف وسداد دفعة للمورد',
            ]);
        });
    }

    public function processPurchaseReturn(int $purchaseInvoiceId, array $items, string $reason, int $userId): PurchaseInvoice
    {
        return DB::transaction(function () use ($purchaseInvoiceId, $items, $reason, $userId) {
            $invoice = PurchaseInvoice::with(['supplier', 'items.product'])->lockForUpdate()->findOrFail($purchaseInvoiceId);
            $supplier = Supplier::where('id', $invoice->supplier_id)->lockForUpdate()->firstOrFail();

            $returnTotal = 0.0;
            $invoiceItems = $invoice->items->keyBy('product_id');

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $qty = (int) $item['quantity'];

                $invItem = $invoiceItems->get($productId);
                if (!$invItem || $qty > $invItem->quantity) {
                    throw new \DomainException("كمية المرتجع للصنف تتجاوز الكمية المسجلة بالفاتورة الأصلية.");
                }

                $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
                if ($product->current_stock < $qty) {
                    throw new \DomainException("المخزون الحالي للصنف ({$product->name}) أقل من كمية المرتجع المطلوبة.");
                }

                $itemTotal = round($qty * (float) $invItem->unit_cost_price, 2);
                $returnTotal += $itemTotal;

                $product->decrement('current_stock', $qty);
            }

            $balanceBefore = (float) $supplier->current_balance;
            $balanceAfter = round(max(0, $balanceBefore - $returnTotal), 2);
            $supplier->update(['current_balance' => $balanceAfter]);

            SupplierLedgerEntry::create([
                'supplier_id'         => $supplier->id,
                'purchase_invoice_id' => $invoice->id,
                'entry_type'          => 'purchase_return',
                'amount'              => $returnTotal,
                'balance_before'      => $balanceBefore,
                'balance_after'       => $balanceAfter,
                'payment_method'      => 'cash',
                'paid_by'             => $userId,
                'notes'               => "مرتجع بضاعة لفاتورة مشتريات رقم {$invoice->invoice_number}. السبب: {$reason}",
            ]);

            return $invoice->fresh(['items.product', 'supplier']);
        });
    }

    public function getSupplierLedgerStatement(int $supplierId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $supplier = Supplier::findOrFail($supplierId);

        $query = SupplierLedgerEntry::where('supplier_id', $supplierId)
            ->with([
                'supplier',
                'purchaseInvoice.items.product',
                'paidByUser:id,name',
            ]);

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $entries = $query->orderBy('id', 'asc')->get();

        $totalPurchases = $entries->where('entry_type', 'purchase_invoice')->sum('amount');
        $totalPayments = $entries->where('entry_type', 'supplier_payment')->sum('amount');
        $totalReturns = $entries->where('entry_type', 'purchase_return')->sum('amount');

        return [
            'supplier'        => $supplier,
            'entries'         => $entries,
            'total_purchases' => (float) $totalPurchases,
            'total_payments'  => (float) $totalPayments,
            'total_returns'   => (float) $totalReturns,
            'current_balance' => (float) $supplier->current_balance,
        ];
    }
}
