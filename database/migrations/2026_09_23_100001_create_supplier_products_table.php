<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku', 100)->nullable()->index();
            $table->decimal('last_purchase_price', 10, 2);
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->unsignedSmallInteger('lead_time_days')->default(1);
            $table->boolean('is_primary_supplier')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id'], 'uk_supplier_product');
            $table->index(['product_id', 'is_primary_supplier']);
            $table->index(['supplier_id', 'supplier_sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
