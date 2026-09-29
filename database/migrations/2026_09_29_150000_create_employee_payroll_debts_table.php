<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payroll_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_payroll_item_id')->nullable()->constrained('payroll_items')->nullOnDelete();
            $table->decimal('original_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'settled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_debts');
    }
};
