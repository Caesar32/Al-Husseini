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
    private const EPSILON = 0.01;

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

            $paymentStatus = $this->paymentStatusFor($finalAmount, $paidAmount, $remainingAmount);

            $branchId = $data['branch_id'] ?? auth()->user()?->branch_id;
            if (empty($branchId)) {
                throw new \InvalidArgumentException('الفرع مطلوب لتسجيل فاتورة المشتريات.');
            }

            // 2. Create Purchase Invoice without triggering double observer execution
            $invoice = PurchaseInvoice::withoutEvents(function () use ($data, $branchId, $receivedByUserId, $subtotal, $taxAmount, $discountAmount, $finalAmount, $paidAmount, $remainingAmount, $paymentStatus) {
                return PurchaseInvoice::create([
                    'invoice_number'   => $data['invoice_number'],
                    'supplier_id'      => $data['supplier_id'],
                    'branch_id'        => $branchId,
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

                // Sync catalog entry in supplier_products. The supplier of the latest purchase
                // becomes the (single) primary supplier; an empty line SKU keeps the known SKU.
                $catalogValues = [
                    'last_purchase_price' => $newCost,
                    'is_primary_supplier' => true,
                ];
                if (!empty($pItem['supplier_sku'])) {
                    $catalogValues['supplier_sku'] = $pItem['supplier_sku'];
                }

                SupplierProduct::updateOrCreate(
                    [
                        'supplier_id' => $supplier->id,
                        'product_id'  => $product->id,
                    ],
                    $catalogValues
                );

                SupplierProduct::where('product_id', $product->id)
                    ->where('supplier_id', '!=', $supplier->id)
                    ->where('is_primary_supplier', true)
                    ->update(['is_primary_supplier' => false]);
            }

            // 4. Supplier ledger: the invoice is owed in full, then any amount paid on
            // reception is a payment. The running balance of the ledger therefore always
            // equals Supplier.current_balance (before + final - paid = before + remaining).
            $balance = (float) $supplier->current_balance;

            if ($finalAmount > 0) {
                $balance = (float) $this->postLedgerEntry($supplier, [
                    'purchase_invoice_id' => $invoice->id,
                    'entry_type'          => 'purchase_invoice',
                    'amount'              => $finalAmount,
                    'payment_method'      => $data['payment_method'] ?? 'cash',
                    'paid_by'             => $receivedByUserId,
                    'notes'               => "استحقاق فاتورة توريد رقم {$invoice->invoice_number}",
                ], $balance, +1)->balance_after;
            }

            if ($paidAmount > 0) {
                $balance = (float) $this->postLedgerEntry($supplier, [
                    'purchase_invoice_id' => $invoice->id,
                    'entry_type'          => 'supplier_payment',
                    'amount'              => $paidAmount,
                    'payment_method'      => $data['payment_method'] ?? 'cash',
                    'paid_by'             => $receivedByUserId,
                    'notes'               => "دفعة مسددة فور استلام فاتورة توريد رقم {$invoice->invoice_number}",
                ], $balance, -1)->balance_after;
            }

            $supplier->update(['current_balance' => $balance]);

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

            $paidBy = $extra['paid_by'] ?? auth()->id();
            if (empty($paidBy)) {
                throw new \InvalidArgumentException('يجب تحديد المستخدم المسؤول عن صرف الدفعة.');
            }

            $targetInvoiceId = $extra['purchase_invoice_id'] ?? null;

            // Allocate the payment to open purchase invoices so their paid/remaining/status stay
            // truthful: to the given invoice only, otherwise FIFO (oldest invoice first).
            // Any amount beyond the open invoices stays unallocated (advance), as before.
            $openInvoices = PurchaseInvoice::where('supplier_id', $supplier->id)
                ->where('remaining_amount', '>', self::EPSILON)
                ->when($targetInvoiceId, fn ($q) => $q->where('id', $targetInvoiceId))
                ->orderBy('invoice_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($targetInvoiceId && $openInvoices->isEmpty()
                && !PurchaseInvoice::where('supplier_id', $supplier->id)->where('id', $targetInvoiceId)->exists()) {
                throw new \DomainException('فاتورة المشتريات المحددة لا تخص هذا المورد.');
            }

            $toAllocate = round($amount, 2);
            foreach ($openInvoices as $invoice) {
                if ($toAllocate <= self::EPSILON) {
                    break;
                }

                $applied = round(min($toAllocate, (float) $invoice->remaining_amount), 2);
                $newPaid = round((float) $invoice->paid_amount + $applied, 2);
                $newRemaining = round(max(0, (float) $invoice->remaining_amount - $applied), 2);

                $invoice->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'payment_status'   => $this->paymentStatusFor((float) $invoice->final_amount, $newPaid, $newRemaining),
                ]);

                $toAllocate = round($toAllocate - $applied, 2);
            }

            $entry = $this->postLedgerEntry($supplier, [
                'purchase_invoice_id' => $targetInvoiceId,
                'entry_type'          => 'supplier_payment',
                'amount'              => $amount,
                'payment_method'      => $method,
                'cheque_number'       => $extra['cheque_number'] ?? null,
                'paid_by'             => $paidBy,
                'receipt_number'      => $extra['receipt_number'] ?? null,
                'notes'               => $extra['notes'] ?? 'سند صرف وسداد دفعة للمورد',
            ], $balanceBefore, -1);

            $supplier->update(['current_balance' => (float) $entry->balance_after]);

            return $entry;
        });
    }

    public function processPurchaseReturn(int $purchaseInvoiceId, array $items, string $reason, int $userId): PurchaseInvoice
    {
        return DB::transaction(function () use ($purchaseInvoiceId, $items, $reason, $userId) {
            $invoice = PurchaseInvoice::with(['supplier', 'items.product'])->lockForUpdate()->findOrFail($purchaseInvoiceId);
            $supplier = Supplier::where('id', $invoice->supplier_id)->lockForUpdate()->firstOrFail();

            $returnTotal = 0.0;
            $invoiceItems = PurchaseInvoiceItem::where('purchase_invoice_id', $invoice->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['quantity'];

                if ($qty < 1) {
                    throw new \InvalidArgumentException('كمية المرتجع يجب أن تكون 1 على الأقل.');
                }

                // Quantities already returned are excluded, so the same goods cannot be returned twice.
                $lines = $invoiceItems->where('product_id', $productId);
                $available = $lines->sum(fn ($line) => (int) $line->quantity - (int) $line->returned_quantity);
                if ($lines->isEmpty() || $qty > $available) {
                    throw new \DomainException("كمية المرتجع للصنف تتجاوز الكمية المتبقية القابلة للإرجاع بالفاتورة الأصلية.");
                }

                $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
                if ($product->current_stock < $qty) {
                    throw new \DomainException("المخزون الحالي للصنف ({$product->name}) أقل من كمية المرتجع المطلوبة.");
                }

                $remainingQty = $qty;
                foreach ($lines as $line) {
                    $lineAvailable = (int) $line->quantity - (int) $line->returned_quantity;
                    if ($remainingQty <= 0 || $lineAvailable <= 0) {
                        continue;
                    }
                    $take = min($remainingQty, $lineAvailable);
                    $returnTotal += round($take * (float) $line->unit_cost_price, 2);
                    $line->update(['returned_quantity' => (int) $line->returned_quantity + $take]);

                    // Reverse the weighted average cost for the goods leaving stock.
                    $stock = (int) $product->current_stock;
                    $newStock = $stock - $take;
                    $cost = (float) $product->cost_price;
                    if ($newStock > 0) {
                        $reversed = (($stock * $cost) - ($take * (float) $line->unit_cost_price)) / $newStock;
                        if ($reversed >= 0) {
                            $cost = round($reversed, 2);
                        }
                    }
                    $product->update(['current_stock' => $newStock, 'cost_price' => $cost]);

                    $remainingQty -= $take;
                }
            }

            $returnTotal = round($returnTotal, 2);

            // The return first reduces what is still owed on this invoice. Anything beyond
            // that (invoice already paid) becomes a credit with the supplier: the balance may
            // go below zero instead of being clamped, so the ledger keeps matching the balance.
            $fromRemaining = round(min($returnTotal, (float) $invoice->remaining_amount), 2);
            if ($fromRemaining > 0) {
                $newRemaining = round((float) $invoice->remaining_amount - $fromRemaining, 2);
                $invoice->update([
                    'remaining_amount' => $newRemaining,
                    'payment_status'   => $this->paymentStatusFor((float) $invoice->final_amount, (float) $invoice->paid_amount, $newRemaining),
                ]);
            }

            $entry = $this->postLedgerEntry($supplier, [
                'purchase_invoice_id' => $invoice->id,
                'entry_type'          => 'purchase_return',
                'amount'              => $returnTotal,
                'payment_method'      => 'cash',
                'paid_by'             => $userId,
                'notes'               => "مرتجع بضاعة لفاتورة مشتريات رقم {$invoice->invoice_number}. السبب: {$reason}",
            ], (float) $supplier->current_balance, -1);

            $supplier->update(['current_balance' => (float) $entry->balance_after]);

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
        // Adjustments (e.g. warranty credit notes) reduce the balance like payments and returns.
        $totalAdjustments = $entries->where('entry_type', 'adjustment')->sum('amount');

        return [
            'supplier'          => $supplier,
            'entries'           => $entries,
            'total_purchases'   => (float) $totalPurchases,
            'total_payments'    => (float) $totalPayments,
            'total_returns'     => (float) $totalReturns,
            'total_adjustments' => (float) $totalAdjustments,
            'current_balance'   => (float) $supplier->current_balance,
        ];
    }

    private function paymentStatusFor(float $finalAmount, float $paidAmount, float $remainingAmount): string
    {
        if ($remainingAmount <= self::EPSILON) {
            return 'paid';
        }

        return $paidAmount > 0 ? 'partially_paid' : 'unpaid';
    }

    /**
     * Writes one supplier ledger entry whose balance_before/after continue the running balance.
     * $sign is +1 for amounts owed to the supplier and -1 for payments, returns and credits.
     */
    private function postLedgerEntry(Supplier $supplier, array $attributes, float $balanceBefore, int $sign): SupplierLedgerEntry
    {
        $amount = round((float) $attributes['amount'], 2);

        return SupplierLedgerEntry::create(array_merge($attributes, [
            'supplier_id'    => $supplier->id,
            'amount'         => $amount,
            'balance_before' => round($balanceBefore, 2),
            'balance_after'  => round($balanceBefore + ($sign * $amount), 2),
        ]));
    }
}
