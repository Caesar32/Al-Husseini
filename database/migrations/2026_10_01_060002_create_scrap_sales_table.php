<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Record of each scrap batch sold to a recycling factory. Previously only the
        // batteries' status changed and the sale amount/buyer were discarded.
        Schema::create('scrap_sales', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('buyer_name', 150);
            $table->string('buyer_phone', 30)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->unsignedInteger('batteries_count');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('cost_value', 12, 2);
            $table->decimal('gross_profit', 12, 2);
            $table->foreignId('sold_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });

        Schema::table('scrap_batteries_inventory', function (Blueprint $table) {
            $table->foreignId('scrap_sale_id')->nullable()->after('batch_number')
                ->constrained('scrap_sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scrap_batteries_inventory', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scrap_sale_id');
        });

        Schema::dropIfExists('scrap_sales');
    }
};
