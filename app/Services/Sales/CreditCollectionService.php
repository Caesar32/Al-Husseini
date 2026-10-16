<?php

namespace App\Services\Sales;

use App\Contracts\Sales\CustomerDebtServiceInterface;
use App\Enums\InvoiceStatus;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Credit (آجل) debt collections: settleCustomerDebt() writes them, and the read side
 * lists them from the credit ledger (entry_type = payment_collection) written by
 * settleCustomerDebt() in this class.
 */
class CreditCollectionService implements CustomerDebtServiceInterface
{
    private const EPSILON = 0.01;

    private function epsilon(): float
    {
        return (float) config('finance.epsilon', self::EPSILON);
    }

    /**
     * تحصيل دفعة من عميل آجل وتحديث رصيده ودفتر الأستاذ.
     * يوزع الدفعة على الفواتير المفتوحة بنظام FIFO (الأقدم أولاً) لضمان تطابق
     * customer.current_credit_balance مع مجموع invoice.remaining_amount.
     *
     * @throws \DomainException إذا كان المبلغ يتجاوز الرصيد المدين أو العميل ليس مديناً
     */
    public function settleCustomerDebt(
        int $customerId,
        float $amount,
        string $paymentMethod,
        int $collectedBy,
        ?string $receiptNumber = null,
        ?string $notes = null
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
            if (! empty($receiptNumber) && CreditLedgerEntry::where('receipt_number', $receiptNumber)->exists()) {
                throw new \DomainException('رقم الإيصال ('.$receiptNumber.') مستخدم مسبقاً.');
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
            $balanceAfter = max(0, round($currentBalance - $amount, 2));

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
                $applied = min($remainingToApply, $invRemaining);
                $newRemaining = max(0, round($invRemaining - $applied, 2));
                if ($newRemaining < $eps) {
                    $newRemaining = 0;
                }
                $newPaid = round((float) $inv->paid_amount + $applied, 2);
                // A partially refunded invoice keeps that status (the return history must stay visible);
                // its payment state is carried by remaining_amount.
                $newStatus = $inv->status === InvoiceStatus::PartiallyRefunded->value
                    ? InvoiceStatus::PartiallyRefunded->value
                    : ($newRemaining <= $eps ? InvoiceStatus::Paid->value : InvoiceStatus::PartiallyPaid->value);

                $inv->update([
                    'paid_amount' => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'status' => $newStatus,
                ]);

                // تسجيل الدفعة على مستوى الفاتورة لتظهر في تاريخ الفواتير وكشف الحساب
                InvoicePayment::create([
                    'invoice_id' => $inv->id,
                    'payment_method' => $paymentMethod,
                    'amount' => $applied,
                    'transaction_reference' => $receiptNumber,
                    'notes' => $notes ?? "تحصيل دفعة آجل من العميل {$customer->name}",
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
                'customer_id' => $customer->id,
                'invoice_id' => null,
                'entry_type' => 'payment_collection',
                'amount' => round($amount, 2),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'collected_by' => $collectedBy,
                'receipt_number' => $receiptNumber,
                'notes' => $ledgerNotes,
            ]);
        });
    }

    public function paginateCollections(int $perPage = 20): LengthAwarePaginator
    {
        return CreditLedgerEntry::query()
            ->where('entry_type', 'payment_collection')
            ->with(['customer:id,name,phone', 'collectedByUser:id,name'])
            ->latest('id')
            ->paginate($perPage, ['id', 'customer_id', 'amount', 'receipt_number', 'collected_by', 'notes', 'created_at'], 'payments_page');
    }

    public function collectedBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        return round((float) CreditLedgerEntry::query()
            ->where('entry_type', 'payment_collection')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount'), 2);
    }

    public function countCollections(): int
    {
        return CreditLedgerEntry::where('entry_type', 'payment_collection')->count();
    }
}
