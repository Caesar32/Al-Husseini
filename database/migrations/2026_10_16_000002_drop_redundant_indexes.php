<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * DB-03: drop indexes that duplicate a unique index on the same columns.
 *   payrolls_branch_year_month_unique (2026_09_22_215442) duplicates
 *   payrolls_branch_id_year_month_unique (2026_09_21_160005) on (branch_id, year, month).
 * The six "->unique()->index()" rows in the audit were never created: Laravel builds
 * only the first fluent index of a column, so those tables have the unique index alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('payrolls', 'payrolls_branch_year_month_unique')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->dropUnique('payrolls_branch_year_month_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('payrolls', 'payrolls_branch_year_month_unique')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->unique(['branch_id', 'year', 'month'], 'payrolls_branch_year_month_unique');
            });
        }
    }
};
