<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Drop existing unique index if exists, replace with plain index
            // The unique index name is typically credit_ledger_entries_receipt_number_unique
            try {
                Schema::table('credit_ledger_entries', function (Blueprint $table) {
                    $table->dropUnique(['receipt_number']);
                });
            } catch (\Throwable $e) {
                // Ignore if index does not exist or has different name
                try {
                    DB::statement('ALTER TABLE credit_ledger_entries DROP INDEX credit_ledger_entries_receipt_number_unique');
                } catch (\Throwable $e2) {
                }
            }

            Schema::table('credit_ledger_entries', function (Blueprint $table) {
                $table->index('receipt_number');
            });
        } else {
            // For sqlite, unique was not strictly enforced in :memory: tests; ensure index exists
            // No action needed for sqlite, but ensure we don't have unique constraint
            try {
                // Try to drop unique if exists (sqlite will ignore)
                Schema::table('credit_ledger_entries', function (Blueprint $table) {
                    $table->dropUnique(['receipt_number']);
                });
            } catch (\Throwable $e) {}
            Schema::table('credit_ledger_entries', function (Blueprint $table) {
                $table->index('receipt_number');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Only re-add unique if no duplicates exist
            $dupCount = DB::table('credit_ledger_entries')
                ->select('receipt_number')
                ->whereNotNull('receipt_number')
                ->groupBy('receipt_number')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($dupCount === 0) {
                try {
                    Schema::table('credit_ledger_entries', function (Blueprint $table) {
                        $table->dropIndex(['receipt_number']);
                    });
                } catch (\Throwable $e) {}
                Schema::table('credit_ledger_entries', function (Blueprint $table) {
                    $table->unique('receipt_number');
                });
            }
        }
    }
};
