<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('paid','partially_paid','unpaid','cancelled','refunded','partially_refunded') NOT NULL DEFAULT 'paid'");
            return;
        }

        if ($driver === 'sqlite') {
            $this->rewriteSqliteStatusCheck("'refunded'", "'refunded','partially_refunded'");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (DB::table('invoices')->where('status', 'partially_refunded')->exists()) {
            // Refuse instead of silently skipping: removing the value would orphan existing rows,
            // and a silent no-op would drop the migration record while the schema keeps the value.
            throw new RuntimeException('Cannot roll back: invoices with status partially_refunded exist.');
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('paid','partially_paid','unpaid','cancelled','refunded') NOT NULL DEFAULT 'paid'");
            return;
        }

        if ($driver === 'sqlite') {
            $this->rewriteSqliteStatusCheck("'refunded','partially_refunded'", "'refunded'");
        }
    }

    /**
     * SQLite stores enum columns as varchar + CHECK constraint. Changing only a CHECK constraint
     * does not affect the on-disk format, so the documented SQLite procedure applies:
     * edit sqlite_master under writable_schema, bump schema_version so every connection
     * (including this one) re-parses the schema, then verify integrity.
     * See https://www.sqlite.org/lang_altertable.html#otheralter
     * Constraint enforcement (foreign_keys / CHECK) is never disabled.
     */
    private function rewriteSqliteStatusCheck(string $search, string $replace): void
    {
        $sql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'invoices')->value('sql');

        if (!$sql || !str_contains($sql, $search) || ($search === "'refunded'" && str_contains($sql, "'partially_refunded'"))) {
            return;
        }

        $schemaVersion = (int) DB::selectOne('PRAGMA schema_version')->schema_version;

        DB::statement('PRAGMA writable_schema=ON');
        try {
            DB::update(
                "UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = 'invoices'",
                [str_replace($search, $replace, $sql)]
            );
        } finally {
            DB::statement('PRAGMA writable_schema=OFF');
        }

        DB::statement('PRAGMA schema_version = ' . ($schemaVersion + 1));

        $check = DB::selectOne('PRAGMA integrity_check');
        $result = $check ? (array) $check : [];
        if (reset($result) !== 'ok') {
            throw new RuntimeException('SQLite integrity_check failed after updating invoices.status CHECK constraint.');
        }
    }
};
