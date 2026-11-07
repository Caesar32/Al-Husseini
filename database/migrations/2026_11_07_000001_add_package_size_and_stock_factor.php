<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * package_size: liters in one sealed package of a liter product; NULL means no package.
     * stock_factor: stock units per quantity on a document line. It stays 1 unless the product was
     * later converted to liters: a line sold as "1" jerrycan of 5 L then keeps quantity 1 and factor
     * 5, so a return of it restocks 5 liters. Existing rows and amounts do not change.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('package_size', 8, 3)->nullable()->after('unit');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('stock_factor', 8, 3)->default(1)->after('returned_quantity');
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('stock_factor', 8, 3)->default(1)->after('returned_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn('stock_factor');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('stock_factor');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('package_size');
        });
    }
};
