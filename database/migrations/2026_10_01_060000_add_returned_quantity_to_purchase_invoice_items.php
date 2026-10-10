<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tracks quantities already returned to the supplier so a purchase line
        // cannot be returned more than once beyond its received quantity.
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });
    }
};
