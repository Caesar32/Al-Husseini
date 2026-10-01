<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Persist fields that the claim / settlement forms already validate but were discarded.
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('decision');
            $table->text('settlement_notes')->nullable()->after('supplier_resolution');
        });
    }

    public function down(): void
    {
        Schema::table('warranty_claims', function (Blueprint $table) {
            $table->dropColumn(['rejection_reason', 'settlement_notes']);
        });
    }
};
