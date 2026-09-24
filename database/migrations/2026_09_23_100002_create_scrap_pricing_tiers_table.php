<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scrap_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('capacity_min_ah');
            $table->unsignedInteger('capacity_max_ah');
            $table->string('tier_name', 100);
            $table->decimal('default_scrap_price', 10, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['capacity_min_ah', 'capacity_max_ah', 'is_active'], 'idx_scrap_tier_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_pricing_tiers');
    }
};
