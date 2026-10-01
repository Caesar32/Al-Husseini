<?php

namespace App\Services\Sales;

use App\Contracts\Sales\PosOrderServiceInterface;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\ScrapBatteriesInventory;
use App\Models\ScrapPricingTier;
use App\Models\TechnicianCommission;
use App\Models\Warranty;
use App\Enums\InvoiceStatus;
use App\Services\Finance\ManagerOverrideService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderService implements PosOrderServiceInterface
{
    private const EPSILON = 0.01;

    private function epsilon(): float
    {
        return (float) config('finance.epsilon', self::EPSILON);
    }
    public function getPaginatedInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filter = InvoiceFilter::fromArray($filters);

        $query = Invoice::query()
            ->with([
                'customer:id,name,phone',
                'customerVehicle:id,car_brand,car_model,plate_number',
                'cashier:id,name',
                'technician:id,full_name',
                'payments',
                'items.product',
                'branch:id,name',
            ]);

        $filter->apply($query);

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function getInvoiceStats(array $filters = []): array
    {
        $filter = InvoiceFilter::fromArray($filters);
        $base = Invoice::query();
        $filter->applyForStats($base);

        return [
            // Net of returns: partially refunded invoices count for what the customer kept.
            'total_sales'            => round(Invoice::sumNetAmount($base), 2),
            'invoices_count'         => (int) (clone $base)->count(),
            'credit_invoices_count'  => (int) (clone $base)->where('remaining_amount', '>', 0)->count(),
            'total_remaining_credit' => (float) (clone $base)->sum('remaining_amount'),
            'scrap_count'            => (int) (clone $base)->where('scrap_deduction_amount', '>', 0)->count(),
        ];
    }

    public function processPosSale(array $data, int $cashierUserId): Invoice
    {
        $idempotencyKey = isset($data['idempotency_key']) && trim((string) $data['idempotency_key']) !== ''
            ? trim((string) $data['idempotency_key'])
            : null;

        try {
            return $this->createPosSale($data, $cashierUserId, $idempotencyKey);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent submission with the same key committed first: return that invoice.
            if ($idempotencyKey !== null && ($existing = $this->findByIdempotencyKey($idempotencyKey))) {
                return $existing;
            }
            throw $e;
        }
    }

    private function findByIdempotencyKey(string $key): ?Invoice
    {
        return Invoice::where('idempotency_key', $key)->first()?->load([
            'items.product',
            'customer',
            'customerVehicle',
            'technician',
            'payments',
            'cashier',
        ]);
    }

    private function createPosSale(array $data, int $cashierUserId, ?string $idempotencyKey): Invoice
    {
        return DB::transaction(function () use ($data, $cashierUserId, $idempotencyKey) {
            // Retried submission (same client key): return the already-created invoice, no side effects.
            if ($idempotencyKey !== null && ($existing = $this->findByIdempotencyKey($idempotencyKey))) {
                return $existing;
            }

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw new \InvalidArgumentException('يجب إضافة منتج واحد على الأقل في الفاتورة.');
            }

            // 1. Bulk query and lock products with pessimistic lock (lockForUpdate)
            $productIds = collect($itemsData)->pluck('product_id')->unique()->all();
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $subtotal = 0.0;
            $preparedItems = [];

            foreach ($itemsData as $item) {
                $productId = $item['product_id'];
                $product = $products->get($productId);

                if (!$product) {
                    throw new \InvalidArgumentException("المنتج ذو المعرف ({$productId}) غير متاح.");
                }

                $qty = (int) ($item['quantity'] ?? 1);
                if ($product->current_stock < $qty) {
                    throw new \DomainException(
                        "الرصيد المتاح من الصنف ({$product->name}) هو {$product->current_stock} فقط، ولا يكفي لصرف {$qty}."
                    );
                }

                $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price'])
                    ? (float) $item['unit_price']
                    : (float) $product->retail_price;

                $totalPrice = round($qty * $unitPrice, 2);
                $subtotal += $totalPrice;

                $preparedItems[] = [
                    'product_id'            => $productId,
                    'quantity'              => $qty,
                    'unit_price'            => $unitPrice,
                    'total_price'           => $totalPrice,
                    'battery_serial'        => !empty($item['battery_serial']) ? trim($item['battery_serial']) : null,
                    'warranty_duration'     => (int) ($product->warranty_months ?? 12),
                    'is_battery'            => (bool) $product->is_battery,
                ];
            }

            // 2. Flexible Scrap Calculation (Supports manual custom price per battery, total scrap deduction, or tier fallback)
            $scrapDeduction = 0.0;
            $hasScrap = !empty($data['has_scrap']);
            if ($hasScrap) {
                $scrapAh = (int) ($data['scrap_capacity_ah'] ?? 70);
                $scrapCount = max(1, (int) ($data['scrap_count'] ?? 1));

                if (isset($data['scrap_deduction_amount']) && is_numeric($data['scrap_deduction_amount'])) {
                    $scrapDeduction = round((float) $data['scrap_deduction_amount'], 2);
                } elseif (isset($data['scrap_price_override']) && is_numeric($data['scrap_price_override'])) {
                    $scrapDeduction = round((float) $data['scrap_price_override'] * $scrapCount, 2);
                } else {
                    $scrapDeduction = $this->calculateScrapDeduction($scrapAh, $scrapCount);
                }
            }

            $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);
            $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
            $scrapDeduction = round($scrapDeduction, 2);
            $gross = round($subtotal + $taxAmount, 2);
            if ($discountAmount + $scrapDeduction > $gross + 0.01) {
                throw new \DomainException('مجموع الخصم وخصم الكهنة (' . number_format($discountAmount + $scrapDeduction, 2) . ' ج.م) يتجاوز إجمالي الفاتورة قبل الخصم (' . number_format($gross, 2) . ' ج.م).');
            }
            $finalAmount = round($gross - $discountAmount - $scrapDeduction, 2);
            if ($finalAmount < -0.01) {
                throw new \LogicException('صافي الفاتورة سالب بعد الخصم.');
            }

            // 3. Process Payments & Customer Credit validation with pessimistic lock
            $paymentsData = $data['payments'] ?? [];
            if (empty($paymentsData)) {
                throw new \InvalidArgumentException('بيانات الدفع مطلوبة.');
            }

            $totalPaid = 0.0;
            $creditAmount = 0.0;
            $paymentMethodsUsed = [];

            foreach ($paymentsData as $pay) {
                $amt = (float) ($pay['amount'] ?? 0);
                $m = $pay['method'] ?? 'cash';
                $totalPaid += $amt;
                $paymentMethodsUsed[] = $m;
                if ($m === 'credit') {
                    $creditAmount += $amt;
                }
            }

            $totalPaid = round($totalPaid, 2);
            if (abs($totalPaid - $finalAmount) > $this->epsilon()) {
                throw new \DomainException(
                    sprintf(
                        'إجمالي مبالغ الدفع (%s ج.م) لا يتطابق مع صافي الفاتورة (%s ج.م).',
                        number_format($totalPaid, 2),
                        number_format($finalAmount, 2)
                    )
                );
            }

            if (empty($data['technician_id'])) {
                throw new \DomainException('يجب اختيار الفني / العامل المسؤول عن التركيب.');
            }

            $customerId = $data['customer_id'] ?? null;
            $customer = null;
            if ($creditAmount > 0) {
                if (!$customerId) {
                    throw new \DomainException('لا يمكن الدفع بالآجل بدون تحديد حساب العميل.');
                }

                $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();
                $newCreditBalance = (float) $customer->current_credit_balance + $creditAmount;
                $creditLimit = (float) $customer->credit_limit;

                if ($newCreditBalance > $creditLimit) {
                    $overrideCode = $data['manager_override_code'] ?? null;
                    if (empty($overrideCode) || !$this->isManagerOverrideValid($overrideCode)) {
                        throw new \DomainException('تم تجاوز سقف الائتمان للعميل، ويلزم إدخال كود موافقة المدير الصحيح للاعتماد.');
                    }
                }
            } elseif ($customerId) {
                $customer = Customer::find($customerId);
            }

            // Determine primary payment method name
            $paymentMethodsUsed = array_unique($paymentMethodsUsed);
            $invoicePaymentMethod = count($paymentMethodsUsed) > 1 ? 'split' : ($paymentMethodsUsed[0] ?? 'cash');

            // 4. Generate unique atomic invoice number
            $branchId = $data['branch_id'] ?? \App\Models\User::find($cashierUserId)?->branch_id ?? auth()->user()?->branch_id ?? null;
            if (empty($branchId)) {
                throw new \InvalidArgumentException('الفرع مطلوب لإنشاء الفاتورة.');
            }
            // Sequential per-day number from a locked counter (released on commit; rolled-back sales free their number).
            $invoiceNumber = app(DocumentNumberService::class)->nextFormatted('INV-' . now()->format('Ymd'));

            $invoice = Invoice::withoutEvents(function () use ($invoiceNumber, $branchId, $customerId, $data, $cashierUserId, $subtotal, $discountAmount, $scrapDeduction, $taxAmount, $finalAmount, $totalPaid, $creditAmount, $invoicePaymentMethod, $idempotencyKey) {
                return Invoice::create([
                    'invoice_number'         => $invoiceNumber,
                    'branch_id'              => $branchId,
                    'customer_id'            => $customerId,
                    'customer_vehicle_id'    => $data['customer_vehicle_id'] ?? null,
                    'technician_id'          => $data['technician_id'] ?? null,
                    'cashier_id'             => $cashierUserId,
                    'subtotal'               => $subtotal,
                    'discount_amount'        => $discountAmount,
                    'scrap_deduction_amount' => $scrapDeduction,
                    'tax_amount'             => $taxAmount,
                    'final_amount'           => $finalAmount,
                    'paid_amount'            => $totalPaid - $creditAmount,
                    'remaining_amount'       => $creditAmount,
                    'payment_method'         => $invoicePaymentMethod,
                    'status'                 => $creditAmount > 0 ? ($creditAmount < $finalAmount ? 'partially_paid' : 'unpaid') : 'paid',
                    'notes'                  => $data['notes'] ?? null,
                    'idempotency_key'        => $idempotencyKey,
                ]);
            });

            // 5. Insert Payments
            foreach ($paymentsData as $pay) {
                InvoicePayment::create([
                    'invoice_id'            => $invoice->id,
                    'payment_method'        => $pay['method'],
                    'amount'                => $pay['amount'],
                    'transaction_reference' => $pay['reference'] ?? null,
                    'notes'                 => $pay['notes'] ?? null,
                ]);
            }

            // 6. Update Customer Credit Ledger if credit was used
            if ($creditAmount > 0 && $customer) {
                $before = (float) $customer->current_credit_balance;
                $after = $before + $creditAmount;

                $customer->update(['current_credit_balance' => $after]);

                CreditLedgerEntry::create([
                    'customer_id'    => $customer->id,
                    'invoice_id'     => $invoice->id,
                    'entry_type'     => 'invoice_debt',
                    'amount'         => $creditAmount,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                    'collected_by'   => $cashierUserId,
                    'notes'          => "مديونية آجلة ناتجة عن فاتورة مبيعات رقم {$invoice->invoice_number}",
                ]);
            }

            // 7. Save Items, Decrement Stock, and Issue Warranties
            $totalBatteriesSold = 0;
            foreach ($preparedItems as $pItem) {
                $invoiceItem = InvoiceItem::create([
                    'invoice_id'               => $invoice->id,
                    'product_id'               => $pItem['product_id'],
                    'quantity'                 => $pItem['quantity'],
                    'unit_price'               => $pItem['unit_price'],
                    'total_price'              => $pItem['total_price'],
                    'battery_serial_number'    => $pItem['battery_serial'],
                    'warranty_duration_months' => $pItem['warranty_duration'],
                ]);

                // Decrement inventory safely
                $product = $products->get($pItem['product_id']);
                $product->decrement('current_stock', $pItem['quantity']);

                // Issue Warranty certificate if product is battery
                if ($pItem['is_battery'] && !empty($pItem['battery_serial'])) {
                    $totalBatteriesSold += $pItem['quantity'];

                    // Warranty requires customer_id, ensure valid customer ID
                    if (!$customerId) {
                        // firstOrCreate is race-safe here (savepoint + unique phone); withTrashed so a
                        // soft-deleted guest record is reused instead of colliding with its unique phone.
                        $guestCustomer = Customer::withTrashed()->firstOrCreate(
                            ['phone' => '00000000000'],
                            [
                                'name'                   => 'عميل نقدي عابر',
                                'tier'                   => 'standard',
                                'credit_limit'           => 0,
                                'current_credit_balance' => 0,
                                'is_active'              => true,
                            ]
                        );
                        $warrantyCustomerId = $guestCustomer->id;
                    } else {
                        $warrantyCustomerId = $customerId;
                    }

                    Warranty::create([
                        'invoice_item_id'     => $invoiceItem->id,
                        'customer_id'         => $warrantyCustomerId,
                        'customer_vehicle_id' => $data['customer_vehicle_id'] ?? null,
                        'serial_number'       => $pItem['battery_serial'],
                        'start_date'          => now()->toDateString(),
                        'end_date'            => now()->addMonths($pItem['warranty_duration'])->toDateString(),
                        'status'              => 'active',
                        'notes'               => "ضمان إلكتروني معتمد صادر من فرع {$branchId}",
                    ]);
                }
            }

            // 8. Deposit Scrap Battery into Inventory if trade-in occurred
            if ($hasScrap && $scrapDeduction > 0) {
                $scrapAh = (int) ($data['scrap_capacity_ah'] ?? 70);
                $scrapCount = (int) ($data['scrap_count'] ?? 1);
                $unitScrapValue = round($scrapDeduction / max(1, $scrapCount), 2);

                for ($i = 0; $i < $scrapCount; $i++) {
                    ScrapBatteriesInventory::create([
                        'branch_id'      => $branchId,
                        'invoice_id'     => $invoice->id,
                        'capacity_ah'    => "{$scrapAh}Ah",
                        'scrap_value'    => $unitScrapValue,
                        'lead_weight_kg' => round($scrapAh * 0.17, 2), // Standard average lead yield per Ah
                        'status'         => 'in_stock',
                        'received_by'    => $data['technician_id'] ?? 1,
                    ]);
                }
            }


            return $invoice->fresh([
                'items.product',
                'customer',
                'customerVehicle',
                'technician',
                'payments',
                'cashier',
            ]);
        });
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
            foreach ($requestedByLine as $lineId => $qty) {
                /** @var InvoiceItem $invItem */
                $invItem = $invoice->items->firstWhere('id', $lineId);

                // Cumulative guard: earlier returns on this line are subtracted (BIZ-03).
                if ($qty > $invItem->returnableQuantity()) {
                    throw new \DomainException(sprintf(
                        'كمية المرتجع للصنف (%s) تتجاوز الكمية المتاحة للإرجاع: %d من أصل %d (سبق إرجاع %d).',
                        $invItem->product?->name ?? $invItem->product_id,
                        $invItem->returnableQuantity(),
                        $invItem->quantity,
                        $invItem->returned_quantity
                    ));
                }

                $product = Product::where('id', $invItem->product_id)->lockForUpdate()->firstOrFail();
                $product->increment('current_stock', $qty);

                $invItem->increment('returned_quantity', $qty);

                // Valued at the line unit price. Proration of invoice discount/scrap/tax is an
                // owner decision (D3) and intentionally not applied here.
                $refundTotal += round($qty * (float) $invItem->unit_price, 2);

                // Void warranty if battery
                if (!empty($invItem->battery_serial_number)) {
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

            $isFullReturn = $invoice->items->every(fn (InvoiceItem $line) => $line->returnableQuantity() === 0);

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
                $balanceAfter  = max(0, round($balanceBefore - $creditRefund, 2));

                $customer->update(['current_credit_balance' => $balanceAfter]);

                CreditLedgerEntry::create([
                    'customer_id'   => $customer->id,
                    'invoice_id'    => $invoice->id,
                    'entry_type'    => 'refund',
                    'amount'        => $creditRefund,
                    'balance_before'=> $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'collected_by'  => $cashierUserId,
                    'notes'         => "تسوية آجل ناتجة عن مرتجع فاتورة رقم {$invoice->invoice_number}. السبب: {$reason}",
                ]);

                $invoice->remaining_amount = max(0, round((float) $invoice->remaining_amount - $creditRefund, 2));
            }

            // Cash portion: create negative payment and reduce paid_amount
            if ($cashRefund > 0.01) {
                InvoicePayment::create([
                    'invoice_id'            => $invoice->id,
                    'payment_method'        => 'cash',
                    'amount'                => -$cashRefund,
                    'transaction_reference' => 'REFUND-' . $invoice->invoice_number,
                    'notes'                 => "مرتجع نقدي لفاتورة {$invoice->invoice_number}. السبب: {$reason}",
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
                'notes'  => trim(($invoice->notes ?? '') . "\nمرتجع بقيمة {$refundTotal} ج.م{$capNote}. السبب: {$reason}"),
            ]);

            return $invoice->fresh(['items.product', 'customer', 'payments']);
        });
    }

    /**
     * Map requested return rows to invoice lines and sum quantities per line.
     * Each row identifies its line by invoice_item_id (preferred) or, for older callers,
     * by product_id when that product appears on exactly one line of the invoice.
     *
     * @return array<int, int> invoice_item_id => quantity
     */
    private function resolveReturnLines(Invoice $invoice, array $items): array
    {
        if (empty($items)) {
            throw new \DomainException('يرجى تحديد الأصناف المراد إرجاعها.');
        }

        $perLine = [];
        foreach ($items as $row) {
            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty < 1) {
                throw new \DomainException('كمية المرتجع يجب أن تكون 1 على الأقل.');
            }

            if (!empty($row['invoice_item_id'])) {
                $line = $invoice->items->firstWhere('id', (int) $row['invoice_item_id']);
                if (!$line) {
                    throw new \DomainException('البند المحدد للمرتجع لا ينتمي إلى هذه الفاتورة.');
                }
            } elseif (!empty($row['product_id'])) {
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

            $perLine[$line->id] = ($perLine[$line->id] ?? 0) + $qty;
        }

        return $perLine;
    }

    public function calculateScrapDeduction(int $capacityAh, int $quantity = 1): float
    {
        $tier = ScrapPricingTier::findPriceForCapacity($capacityAh);
        if (!$tier) {
            return 0.0;
        }

        return round((float) $tier->default_scrap_price * max(1, $quantity), 2);
    }

    public function getDailyCashierSummary(int $branchId, string $date): array
    {
        // DB aggregation for financial totals (FIN-H08) — avoids loading all rows into PHP
        $baseInvoiceQuery = Invoice::where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->countable();

        $totalSales = Invoice::sumNetAmount($baseInvoiceQuery);
        $totalScrapDeductions = (float) $baseInvoiceQuery->clone()->sum('scrap_deduction_amount');
        $invoicesCount = (int) $baseInvoiceQuery->clone()->count();

        // Payments aggregated in DB (refunds are negative payments, so these are net)
        $paymentBase = InvoicePayment::whereHas('invoice', fn($q) => $q->where('branch_id', $branchId)->whereDate('created_at', $date)->countable());
        $totalCash = (float) $paymentBase->clone()->where('payment_method', 'cash')->sum('amount');
        $totalCard = (float) $paymentBase->clone()->where('payment_method', 'card')->sum('amount');
        $totalCredit = (float) $paymentBase->clone()->where('payment_method', 'credit')->sum('amount');

        // Batteries count via DB, net of returned units
        $batteriesCount = (int) \App\Models\InvoiceItem::whereHas('invoice', fn($q) => $q->where('branch_id', $branchId)->whereDate('created_at', $date)->countable())
            ->whereHas('product', fn($q) => $q->where('is_battery', true))
            ->sum(DB::raw('quantity - returned_quantity'));

        // Still load invoices for detailed return (with relations) — but limited to needed data
        $invoices = Invoice::where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->countable()
            ->with(['payments', 'items.product', 'customer:id,name', 'technician:id,full_name'])
            ->get();

        return [
            'date'                  => $date,
            'branch_id'             => $branchId,
            'invoices_count'        => $invoicesCount,
            'total_sales'           => round($totalSales, 2),
            'total_cash'            => round($totalCash, 2),
            'total_card'            => round($totalCard, 2),
            'total_credit'          => round($totalCredit, 2),
            'total_scrap_deductions'=> round($totalScrapDeductions, 2),
            'batteries_sold_count'  => $batteriesCount,
            'invoices'              => $invoices,
        ];
    }

    /**
     * تحصيل دفعة من عميل آجل وتحديث رصيده ودفتر الأستاذ.
     * يوزع الدفعة على الفواتير المفتوحة بنظام FIFO (الأقدم أولاً) لضمان تطابق
     * customer.current_credit_balance مع مجموع invoice.remaining_amount.
     *
     * @throws \DomainException إذا كان المبلغ يتجاوز الرصيد المدين أو العميل ليس مديناً
     */
    public function settleCustomerDebt(
        int    $customerId,
        float  $amount,
        string $paymentMethod,
        int    $collectedBy,
        ?string $receiptNumber = null,
        ?string $notes         = null
    ): CreditLedgerEntry {
        return DB::transaction(function () use ($customerId, $amount, $paymentMethod, $collectedBy, $receiptNumber, $notes) {
            if ($amount <= 0) {
                throw new \InvalidArgumentException('مبلغ التحصيل يجب أن يكون أكبر من الصفر.');
            }

            // قفل تشاؤمي لمنع Race Condition في حالة تحصيل متزامن
            $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

            $currentBalance = (float) $customer->current_credit_balance;
            $eps = $this->epsilon();

            // Idempotency: prevent duplicate receipt_number
            if (!empty($receiptNumber) && CreditLedgerEntry::where('receipt_number', $receiptNumber)->exists()) {
                throw new \DomainException('رقم الإيصال (' . $receiptNumber . ') مستخدم مسبقاً.');
            }

            if ($currentBalance <= $eps) {
                throw new \DomainException("العميل ({$customer->name}) ليس عليه أي مديونية آجل مستحقة.");
            }

            if ($amount > $currentBalance + $eps) {
                throw new \DomainException(
                    sprintf(
                        'مبلغ التحصيل (%s ج.م) يتجاوز رصيد العميل المدين الفعلي (%s ج.م).',
                        number_format($amount, 2),
                        number_format($currentBalance, 2)
                    )
                );
            }

            $balanceBefore = $currentBalance;
            $balanceAfter  = max(0, round($currentBalance - $amount, 2));

            $customer->update(['current_credit_balance' => $balanceAfter]);

            // ─── توزيع الدفعة على الفواتير المفتوحة بنظام FIFO (الأقدم أولاً) ───────────
            // يضمن ذلك تطابق رصيد العميل مع مجموع الفواتير المتبقية في كشف الحساب
            $remainingToApply = round($amount, 2);
            // Partially refunded invoices are included: debt left on them is still owed (BIZ-05).
            $openInvoices = Invoice::where('customer_id', $customerId)
                ->where('remaining_amount', '>', $eps)
                ->countable()
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($openInvoices as $inv) {
                if ($remainingToApply <= $eps) {
                    break;
                }

                $invRemaining = (float) $inv->remaining_amount;
                $applied      = min($remainingToApply, $invRemaining);
                $newRemaining = max(0, round($invRemaining - $applied, 2));
                if ($newRemaining < $eps) $newRemaining = 0;
                $newPaid      = round((float) $inv->paid_amount + $applied, 2);
                // A partially refunded invoice keeps that status (the return history must stay visible);
                // its payment state is carried by remaining_amount.
                $newStatus    = $inv->status === InvoiceStatus::PartiallyRefunded->value
                    ? InvoiceStatus::PartiallyRefunded->value
                    : ($newRemaining <= $eps ? InvoiceStatus::Paid->value : InvoiceStatus::PartiallyPaid->value);

                $inv->update([
                    'paid_amount'      => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status'           => $newStatus,
                ]);

                // تسجيل الدفعة على مستوى الفاتورة لتظهر في تاريخ الفواتير وكشف الحساب
                InvoicePayment::create([
                    'invoice_id'            => $inv->id,
                    'payment_method'        => $paymentMethod,
                    'amount'                => $applied,
                    'transaction_reference' => $receiptNumber,
                    'notes'                 => $notes ?? "تحصيل دفعة آجل من العميل {$customer->name}",
                ]);

                $remainingToApply = round($remainingToApply - $applied, 2);
            }
            // ─────────────────────────────────────────────────────────────────────────────

            // Any amount not matched to an open invoice settles debt that has no invoice in the
            // system (opening balances brought in as ledger entries, e.g. SalesAndPosDataSeeder).
            // It is recorded explicitly instead of being silently absorbed.
            $ledgerNotes = $notes ?? "تحصيل دفعة آجل من العميل {$customer->name} بطريقة {$paymentMethod}";
            if ($remainingToApply > $eps) {
                $ledgerNotes .= sprintf(' | منها %s ج.م على رصيد مديونية غير مرتبط بفواتير (رصيد افتتاحي).', number_format($remainingToApply, 2));
            }

            return CreditLedgerEntry::create([
                'customer_id'    => $customer->id,
                'invoice_id'     => null,
                'entry_type'     => 'payment_collection',
                'amount'         => round($amount, 2),
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'collected_by'   => $collectedBy,
                'receipt_number' => $receiptNumber,
                'notes'          => $ledgerNotes,
            ]);
        });
    }

    protected function isManagerOverrideValid(?string $code): bool
    {
        return app(ManagerOverrideService::class)->isValid($code);
    }
}

