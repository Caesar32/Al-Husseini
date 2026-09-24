<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'credit'])->index();
            $table->decimal('amount', 10, 2);
            $table->string('transaction_reference', 100)->nullable()->index();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
