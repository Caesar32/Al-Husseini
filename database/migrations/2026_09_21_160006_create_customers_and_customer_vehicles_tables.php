<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->unique()->index();
            $table->string('national_id', 30)->nullable();
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->decimal('current_credit_balance', 10, 2)->default(0);
            $table->enum('tier', ['standard', 'vip', 'fleet'])->default('standard');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('plate_number', 50)->index();
            $table->string('car_brand', 50);
            $table->string('car_model', 50);
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('chassis_number', 100)->nullable();
            $table->unsignedInteger('last_odometer')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'plate_number']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_vehicles');
        Schema::dropIfExists('customers');
    }
};
