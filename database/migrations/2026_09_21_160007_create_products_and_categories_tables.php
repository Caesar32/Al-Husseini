<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('sku', 50)->unique()->index();
            $table->string('barcode', 100)->nullable()->unique()->index();
            $table->string('name', 200);
            $table->string('brand', 100);
            $table->string('capacity_ah', 20)->nullable();
            $table->string('voltage', 20)->default('12V');
            $table->enum('terminal_type', ['regular', 'reverse', 'side'])->default('regular');
            $table->unsignedSmallInteger('warranty_months')->default(12);
            $table->decimal('cost_price', 10, 2);
            $table->decimal('retail_price', 10, 2);
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('reorder_threshold')->default(5);
            $table->boolean('is_battery')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
