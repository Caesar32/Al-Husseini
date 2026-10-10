<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Gap-free, lock-protected counters for human-facing document numbers
        // (sales invoices, warranty claims, ...). One row per sequence key, e.g. "INV-20261001".
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
