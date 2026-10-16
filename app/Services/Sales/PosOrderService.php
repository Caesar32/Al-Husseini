<?php

namespace App\Services\Sales;

use App\Contracts\Catalog\StockLedgerInterface;
use App\Contracts\Sales\CustomerDebtServiceInterface;
use App\Contracts\Sales\PosOrderServiceInterface;
use App\Contracts\Sales\SalesReturnServiceInterface;
use App\Jobs\DispatchOwnerPushNotification;
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
use App\Enums\StockMovementType;
use App\Services\Finance\ManagerOverrideService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderService implements PosOrderServiceInterface
{
    private const EPSILON = 0.01;

    public function __construct(
        private StockLedgerInterface $stock,
        private SalesReturnServiceInterface $salesReturns,
        private CustomerDebtServiceInterface $customerDebts,
        private ManagerOverrideService $override,
        private DocumentNumberService $documentNumbers,
    ) {}

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
            if ($idempotencyKey !== null && ($existing = $this->findByIdempotencyKey($idempotencyKey, $cashierUserId))) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * A retry only resolves to an invoice created by the same cashier; a key that belongs to
     * another cashier's sale is refused rather than disclosing or reusing that invoice.
     */
    private function findByIdempotencyKey(string $key, int $cashierUserId): ?Invoice
    {
        $invoice = Invoice::where('idempotency_key', $key)->first();

        if ($invoice && (int) $invoice->cashier_id !== $cashierUserId) {
            throw new \DomainException('مفتاح منع التكرار مستخدم لعملية بيع أخرى. أعد تحميل شاشة البيع وحاول مرة أخرى.');
        }

        return $invoice?->load([
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
            if ($idempotencyKey !== null && ($existing = $this->findByIdempotencyKey($idempotencyKey, $cashierUserId))) {
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

            // Stock is checked against the total requested per product: the same product may
            // appear on several lines (each battery unit is its own line with its own serial).
            $requestedPerProduct = [];
            foreach ($itemsData as $item) {
                $requestedPerProduct[$item['product_id']] = ($requestedPerProduct[$item['product_id']] ?? 0) + (int) ($item['quantity'] ?? 1);
            }

            foreach ($itemsData as $item) {
                $productId = $item['product_id'];
                $product = $products->get($productId);

                if (!$product) {
                    throw new \InvalidArgumentException("المنتج ذو المعرف ({$productId}) غير متاح.");
                }

                $qty = (int) ($item['quantity'] ?? 1);
                $requestedTotal = $requestedPerProduct[$productId];
                if ($product->current_stock < $requestedTotal) {
                    throw new \DomainException(
                        "الرصيد المتاح من الصنف ({$product->name}) هو {$product->current_stock} فقط، ولا يكفي لصرف {$requestedTotal}."
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
                    'warranty_duration'     => (int) ($product->warranty_months ?? \App\Models\Setting::number('warranty_months_default', 12, 0, 120)),
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
                // Defence in depth (the request validates too): never a negative payment, and no
                // zero payment when something is due. A zero-due sale (full discount) may carry 0.
                if ($amt < 0 || ($amt <= 0 && $finalAmount > $this->epsilon())) {
                    throw new \DomainException('مبلغ الدفعة يجب أن يكون أكبر من الصفر.');
                }
                $totalPaid += $amt;
                $paymentMethodsUsed[] = $m;
                if ($m === 'credit') {
                    $creditAmount += $amt;
                }
            }

            $totalPaid = round($totalPaid, 2);
            // Exact comparison in piasters (the amounts are 2-decimal currency values): a tolerance
            // would let a one-piaster overpayment through.
            $paidCents = (int) round($totalPaid * 100);
            $dueCents = (int) round($finalAmount * 100);

            if ($paidCents > $dueCents) {
                throw new \DomainException(
                    sprintf(
                        'مبلغ الدفع (%s ج.م) يتجاوز المبلغ المستحق (%s ج.م). لا يمكن دفع أكثر من المستحق.',
                        number_format($totalPaid, 2),
                        number_format($finalAmount, 2)
                    )
                );
            }
            if ($paidCents < $dueCents) {
                throw new \DomainException(
                    sprintf(
                        'إجمالي الدفعات (%s ج.م) أقل من المبلغ المستحق (%s ج.م). أكمل الدفع أو سجّل المتبقي على الآجل.',
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
                    if (empty($overrideCode) || !$this->override->isValid($overrideCode)) {
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
            $invoicePrefix = (string) \App\Models\Setting::get('invoice_prefix', 'INV-');
            if (!preg_match('/^[A-Za-z0-9-]{1,10}$/', $invoicePrefix)) {
                $invoicePrefix = 'INV-';
            }
            $invoiceNumber = $this->documentNumbers->nextFormatted($invoicePrefix . now()->format('Ymd'));

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
                $stockAfter = $this->stock->move($product->id, -$pItem['quantity'], StockMovementType::Sale, $invoice, $cashierUserId);
                $stockBeforeDecrement = $stockAfter + $pItem['quantity'];

                // Owner alert: fires only on the 1 -> 0 edge, not on every sale of an
                // already-depleted item.
                if ($stockBeforeDecrement > 0 && $stockAfter <= 0) {
                    DispatchOwnerPushNotification::dispatch(
                        'نفاد مخزون صنف حيوي ⚠️',
                        "نفد مخزون الصنف ({$product->name}) بالكامل بعد عملية بيع.",
                        ['type' => 'critical_stock_zero', 'product_id' => $product->id, 'sku' => $product->sku]
                    );
                }

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
                        'received_by'    => (int) $data['technician_id'], // validated non-empty at the start of the sale
                    ]);
                }
            }

            // Owner alert: exceptionally large sale (threshold fixed per the approved spec).
            if ($finalAmount > 15000.0) {
                DispatchOwnerPushNotification::dispatch(
                    'فاتورة مبيعات استثنائية 💰',
                    "تم إصدار فاتورة رقم {$invoiceNumber} بقيمة " . number_format($finalAmount, 2) . ' ج.م',
                    ['type' => 'high_value_sale', 'invoice_id' => $invoice->id, 'amount' => $finalAmount]
                );
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
        return $this->salesReturns->processSalesReturn($invoiceId, $items, $reason, $cashierUserId);
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

    public function settleCustomerDebt(
        int $customerId,
        float $amount,
        string $paymentMethod,
        int $collectedBy,
        ?string $receiptNumber = null,
        ?string $notes = null
    ): CreditLedgerEntry {
        return $this->customerDebts->settleCustomerDebt($customerId, $amount, $paymentMethod, $collectedBy, $receiptNumber, $notes);
    }
}

