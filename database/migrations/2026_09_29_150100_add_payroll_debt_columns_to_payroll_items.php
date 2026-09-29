<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('debt_repayment', 15, 2)->default(0)->after('total_deduction');
            $table->decimal('carried_debt', 15, 2)->default(0)->after('debt_repayment');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['debt_repayment', 'carried_debt']);
        });
    }
};
