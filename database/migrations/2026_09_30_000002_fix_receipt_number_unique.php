<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const TABLE = 'credit_ledger_entries';
    private const UNIQUE = 'credit_ledger_entries_receipt_number_unique';
    private const INDEX = 'credit_ledger_entries_receipt_number_index';

    /**
     * receipt_number is no longer unique: one collection receipt may be referenced by several
     * entries. Duplicate-receipt protection is enforced in PosOrderService::settleCustomerDebt.
     * Idempotent on every driver: acts only on the indexes that actually exist.
     */
    public function up(): void
    {
        if (Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropUnique(self::UNIQUE));
        }

        if (!Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->index('receipt_number', self::INDEX));
        }
    }

    public function down(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->select('receipt_number')
            ->whereNotNull('receipt_number')
            ->groupBy('receipt_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            // Refuse instead of silently skipping: the unique index cannot be restored over duplicates.
            throw new RuntimeException('Cannot roll back: duplicate credit_ledger_entries.receipt_number values exist.');
        }

        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex(self::INDEX));
        }

        if (!Schema::hasIndex(self::TABLE, self::UNIQUE)) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->unique('receipt_number', self::UNIQUE));
        }
    }
};
