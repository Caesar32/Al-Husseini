<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('serial_number', 100)->unique()->index();
            $table->date('start_date');
            $table->date('end_date')->index();
            $table->enum('status', ['active', 'expired', 'claimed', 'voided'])->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->constrained('employees')->restrictOnDelete();
            $table->date('claim_date');
            $table->decimal('battery_voltage_tested', 4, 2);
            $table->decimal('cca_tested', 6, 1)->nullable();
            $table->text('issue_description');
            $table->enum('decision', ['pending', 'recharged', 'repaired', 'replaced', 'rejected'])->default('pending')->index();
            $table->foreignId('replacement_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranties');
    }
};
