<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة فهرس مركب على جدول payrolls لتسريع استعلام:
 * WHERE branch_id = ? AND year = ? AND month = ?
 * المستخدم بشكل متكرر في PayrollService::generateMonthlyPayroll()
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasIndex('payrolls', 'payrolls_branch_year_month_unique')) {
            Schema::table('payrolls', function (Blueprint $table) {
                $table->unique(['branch_id', 'year', 'month'], 'payrolls_branch_year_month_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropUnique('payrolls_branch_year_month_unique');
        });
    }
};
