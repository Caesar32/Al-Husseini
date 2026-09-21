<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('scrap_batteries_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('capacity_ah', 20)->default('70Ah');
            $table->decimal('scrap_value', 10, 2);
            $table->decimal('lead_weight_kg', 6, 2)->nullable();
            $table->enum('status', ['in_stock', 'sold_to_factory', 'recycled'])->default('in_stock')->index();
            $table->string('batch_number', 50)->nullable()->index();
            $table->foreignId('received_by')->constrained('employees')->restrictOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('scrap_batteries_inventory');
    }
};
