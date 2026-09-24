<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->string('claim_number', 50)->nullable()->unique()->after('id');
            $table->foreignId('customer_id')->nullable()->after('warranty_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->string('defective_battery_serial', 100)->nullable()->index()->after('branch_id');
            $table->foreignId('replacement_product_id')->nullable()->after('replacement_invoice_id')->constrained('products')->restrictOnDelete();
            $table->string('replacement_battery_serial', 100)->nullable()->index()->after('replacement_product_id');
            $table->foreignId('supplier_id')->nullable()->after('replacement_battery_serial')->constrained()->nullOnDelete();
            $table->enum('supplier_resolution', [
                'pending',
                'sent_to_supplier',
                'settled_replacement',
                'settled_credit_note',
                'rejected',
            ])->default('pending')->index()->after('supplier_id');
            $table->foreignId('received_by_user_id')->nullable()->after('supplier_resolution')->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by_user_id')->nullable()->after('received_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('settled_by_user_id');
            $table->timestamp('resolved_at')->nullable()->after('received_at');

            $table->index(['customer_id', 'supplier_resolution'], 'idx_warranty_claim_cust_res');
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->dropIndex('idx_warranty_claim_cust_res');
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['replacement_product_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['received_by_user_id']);
            $table->dropForeign(['settled_by_user_id']);
            $table->dropColumn([
                'claim_number',
                'customer_id',
                'branch_id',
                'defective_battery_serial',
                'replacement_product_id',
                'replacement_battery_serial',
                'supplier_id',
                'supplier_resolution',
                'received_by_user_id',
                'settled_by_user_id',
                'received_at',
                'resolved_at',
            ]);
        });
    }
};
