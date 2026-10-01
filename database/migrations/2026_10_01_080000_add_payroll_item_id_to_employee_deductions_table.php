<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Records which payroll item withheld a deduction, so disbursal can mark exactly those
        // deductions as applied. Nullable and additive; deleting a (draft) payroll item clears the link.
        Schema::table('employee_deductions', function (Blueprint $table) {
            $table->foreignId('payroll_item_id')
                ->nullable()
                ->after('attendance_id')
                ->constrained('payroll_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_deductions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_item_id');
        });
    }
};
