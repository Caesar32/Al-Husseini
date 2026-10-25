<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit', 10)->default('piece')->after('name');
            $table->decimal('current_stock', 12, 3)->default(0)->change();
            $table->decimal('reorder_threshold', 12, 3)->default(5)->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->default(1)->change();
            $table->decimal('returned_quantity', 12, 3)->default(0)->change();
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('returned_quantity', 12, 3)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity_delta', 12, 3)->change();
            $table->decimal('stock_after', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('current_stock')->default(0)->change();
            $table->unsignedInteger('reorder_threshold')->default(5)->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->change();
            $table->unsignedInteger('returned_quantity')->default(0)->change();
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
            $table->unsignedInteger('returned_quantity')->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('quantity_delta')->change();
            $table->integer('stock_after')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
