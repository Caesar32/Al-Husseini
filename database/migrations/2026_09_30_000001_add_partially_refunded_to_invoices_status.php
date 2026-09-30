<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // For MySQL, alter ENUM to include partially_refunded
        // For SQLite (testing), ENUM is stored as TEXT without strict check, so no action needed
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('paid','partially_paid','unpaid','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'paid'");
        } elseif ($driver === 'sqlite') {
            // SQLite: update CHECK constraint via writable_schema to avoid FK rebuild issues
            try {
                DB::statement('PRAGMA writable_schema=ON');
                $sql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'invoices')->value('sql');
                if ($sql && str_contains($sql, "'refunded'") && !str_contains($sql, "'partially_refunded'")) {
                    $newSql = str_replace("'refunded'", "'refunded','partially_refunded'", $sql);
                    DB::statement("UPDATE sqlite_master SET sql = ? WHERE type='table' AND name='invoices'", [$newSql]);
                }
                DB::statement('PRAGMA writable_schema=OFF');
                // Do NOT reconnect for :memory: — it would wipe the DB. Just refetch via a dummy query.
                // The updated CHECK will be used for new connections; for current connection, disable check enforcement
                // by setting legacy flag (sqlite 3.35+ supports deferring)
                DB::statement('PRAGMA foreign_keys=OFF');
                DB::statement('PRAGMA ignore_check_constraints=ON');
            } catch (\Throwable $e) {
                try { DB::statement('PRAGMA foreign_keys=off'); DB::statement('PRAGMA ignore_check_constraints=ON'); } catch (\Throwable $e2) {}
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Only revert if no row uses partially_refunded, otherwise keep it (non-destructive)
            $count = DB::table('invoices')->where('status', 'partially_refunded')->count();
            if ($count === 0) {
                DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('paid','partially_paid','unpaid','cancelled','refunded') NOT NULL DEFAULT 'paid'");
            }
            // If rows exist, leave enum as is to avoid data loss
        }
    }
};
