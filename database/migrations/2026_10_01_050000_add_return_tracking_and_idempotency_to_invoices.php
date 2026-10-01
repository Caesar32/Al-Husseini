<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Units already returned on this line; returns are validated against quantity - returned_quantity.
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Cumulative value refunded (cash + credit reversal) across all returns of this invoice.
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('remaining_amount');
            // Client-generated key so a retried POS submission cannot create a second invoice.
            $table->string('idempotency_key', 64)->nullable()->unique()->after('notes');
        });

        $this->backfillRefundedAmount();
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'refunded_amount']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });
    }

    /**
     * Rebuild refunded_amount from refunds already recorded by the return flow:
     * negative cash payments referenced "REFUND-{invoice_number}" plus credit-ledger "refund"
     * entries linked to the invoice. Sets (not adds) the value, so rerunning is idempotent.
     * Historical returned quantities per line cannot be reconstructed (the old flow did not
     * record them); returned_quantity stays 0 for pre-existing returns.
     */
    private function backfillRefundedAmount(): void
    {
        $cashRefunds = DB::table('invoice_payments')
            ->where('amount', '<', 0)
            ->where('transaction_reference', 'like', 'REFUND-%')
            ->groupBy('invoice_id')
            ->select('invoice_id', DB::raw('SUM(-amount) as total'))
            ->pluck('total', 'invoice_id');

        $creditRefunds = DB::table('credit_ledger_entries')
            ->where('entry_type', 'refund')
            ->whereNotNull('invoice_id')
            ->groupBy('invoice_id')
            ->select('invoice_id', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'invoice_id');

        $invoiceIds = $cashRefunds->keys()->merge($creditRefunds->keys())->unique();

        foreach ($invoiceIds as $invoiceId) {
            $total = round((float) ($cashRefunds[$invoiceId] ?? 0) + (float) ($creditRefunds[$invoiceId] ?? 0), 2);
            DB::table('invoices')->where('id', $invoiceId)->update(['refunded_amount' => $total]);
        }
    }
};
