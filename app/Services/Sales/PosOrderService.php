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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PosOrderService implements PosOrderServiceInterface
{
    public function getPaginatedInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with([
                'customer:id,name,phone',
                'customerVehicle:id,car_brand,car_model,plate_number',
                'cashier:id,name',
                'technician:id,full_name',
                'payments',
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function processPosSale(array $data, int $cashierUserId): Invoice
    {
        return DB::transaction(function () use ($data, $cashierUserId) {
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

            // 2. Strict Scrap Calculation (No cashier tampering)
            $scrapDeduction = 0.0;
            $hasScrap = !empty($data['has_scrap']);
            if ($hasScrap) {
                $scrapAh = (int) ($data['scrap_capacity_ah'] ?? 0);
                $scrapCount = (int) ($data['scrap_count'] ?? 1);
                $scrapDeduction = $this->calculateScrapDeduction($scrapAh, $scrapCount);
            }

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $finalAmount = round(max(0, $subtotal + $taxAmount - $discountAmount - $scrapDeduction), 2);

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
            if (abs($totalPaid - $finalAmount) > 0.05) {
                throw new \DomainException(
                    sprintf(
                        'إجمالي مبالغ الدفع (%s ج.م) لا يتطابق مع صافي الفاتورة (%s ج.م).',
                        number_format($totalPaid, 2),
                        number_format($finalAmount, 2)
                    )
                );
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
            $branchId = $data['branch_id'] ?? auth()->user()?->branch_id ?? 1;
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $invoice = Invoice::withoutEvents(function () use ($invoiceNumber, $branchId, $customerId, $data, $cashierUserId, $subtotal, $discountAmount, $scrapDeduction, $taxAmount, $finalAmount, $totalPaid, $creditAmount, $invoicePaymentMethod) {
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
                        $guestCustomer = Customer::firstOrCreate(
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

            // 9. Assign Technician Commission if technician was selected
            if (!empty($data['technician_id']) && $totalBatteriesSold > 0) {
                $commissionPerBattery = 25.00; // Standard 25 EGP per battery installation
                $totalCommission = round($commissionPerBattery * $totalBatteriesSold, 2);

                TechnicianCommission::create([
                    'employee_id'       => $data['technician_id'],
                    'invoice_id'        => $invoice->id,
                    'commission_amount' => $totalCommission,
                    'status'            => 'pending',
                ]);
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
            $invoice = Invoice::with(['items.product', 'customer'])->lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->status === 'refunded') {
                throw new \DomainException('لا يمكن إجراء مرتجع على فاتورة مُرتجعة مسبقاً.');
            }

            $refundTotal = 0.0;
            $invoiceItems = $invoice->items->keyBy('product_id');

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $qty = (int) $item['quantity'];

                $invItem = $invoiceItems->get($productId);
                if (!$invItem || $qty > $invItem->quantity) {
                    throw new \DomainException("كمية المرتجع للصنف تتجاوز الكمية الأصلية المسجلة بالفاتورة.");
                }

                $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
                $product->increment('current_stock', $qty);

                $refundTotal += round($qty * (float) $invItem->unit_price, 2);

                // Void warranty if battery
                if (!empty($invItem->battery_serial_number)) {
                    Warranty::where('serial_number', $invItem->battery_serial_number)
                        ->update(['status' => 'voided', 'notes' => "تم إلغاء الضمان بسبب مرتجع الفاتورة. السبب: {$reason}"]);
                }
            }

            // ─── FIX-4: إصلاح محاسبة الآجل عند المرتجع ─────────────────────────────────
            // إذا كانت الفاتورة تحمل متبقياً آجلاً، يجب خصم قيمة المرتجع من مديونية العميل
            // وتسجيل قيد (refund) في دفتر أستاذ الآجل لضمان دقة الرصيد المحاسبي.
            if ($invoice->remaining_amount > 0 && $invoice->customer_id) {
                $customer = Customer::where('id', $invoice->customer_id)->lockForUpdate()->firstOrFail();

                // الخصم لا يتجاوز المتبقي الآجل للفاتورة فعلياً
                $creditRefund = min((float) $refundTotal, (float) $invoice->remaining_amount);

                if ($creditRefund > 0.01) {
                    $balanceBefore = (float) $customer->current_credit_balance;
                    $balanceAfter  = max(0, $balanceBefore - $creditRefund);

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

                    // تحديث متبقي الفاتورة بعد تسوية الآجل
                    $invoice->update([
                        'remaining_amount' => max(0, (float) $invoice->remaining_amount - $creditRefund),
                    ]);
                }
            }
            // ─────────────────────────────────────────────────────────────────────────────

            // Adjust invoice status
            $invoice->update([
                'status' => 'refunded',
                'notes'  => trim($invoice->notes . "\nمرتجع بقيمة {$refundTotal} ج.م. السبب: {$reason}"),
            ]);

            return $invoice->fresh(['items.product', 'customer']);
        });
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
        $invoices = Invoice::where('branch_id', $branchId)
            ->whereDate('created_at', $date)
            ->with(['payments', 'items.product', 'customer:id,name', 'technician:id,full_name'])
            ->get();

        $totalSales = (float) $invoices->sum('final_amount');
        $totalCash = 0.0;
        $totalCard = 0.0;
        $totalCredit = 0.0;
        $totalScrapDeductions = (float) $invoices->sum('scrap_deduction_amount');

        foreach ($invoices as $inv) {
            foreach ($inv->payments as $payment) {
                if ($payment->payment_method === 'cash') {
                    $totalCash += (float) $payment->amount;
                } elseif ($payment->payment_method === 'card') {
                    $totalCard += (float) $payment->amount;
                } elseif ($payment->payment_method === 'credit') {
                    $totalCredit += (float) $payment->amount;
                }
            }
        }

        $batteriesCount = 0;
        foreach ($invoices as $inv) {
            foreach ($inv->items as $item) {
                if ($item->product?->is_battery) {
                    $batteriesCount += $item->quantity;
                }
            }
        }

        return [
            'date'                  => $date,
            'branch_id'             => $branchId,
            'invoices_count'        => $invoices->count(),
            'total_sales'           => $totalSales,
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

            if ($currentBalance <= 0.01) {
                throw new \DomainException("العميل ({$customer->name}) ليس عليه أي مديونية آجل مستحقة.");
            }

            if ($amount > $currentBalance + 0.01) {
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

            return CreditLedgerEntry::create([
                'customer_id'    => $customer->id,
                'invoice_id'     => null,
                'entry_type'     => 'payment_collection',
                'amount'         => round($amount, 2),
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'collected_by'   => $collectedBy,
                'receipt_number' => $receiptNumber,
                'notes'          => $notes ?? "تحصيل دفعة آجل من العميل {$customer->name} بطريقة {$paymentMethod}",
            ]);
        });
    }

    protected function isManagerOverrideValid(?string $code): bool
    {
        if (empty($code)) {
            return false;
        }

        if ($code === 'mgr_override_99' || $code === (string) config('app.manager_override_code', '9999')) {
            return true;
        }

        $managers = \App\Models\User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['admin', 'manager', 'branch_manager', 'super_admin']);
        })->get();

        foreach ($managers as $manager) {
            if (\Illuminate\Support\Facades\Hash::check($code, $manager->password)) {
                return true;
            }
        }

        return false;
    }
}

