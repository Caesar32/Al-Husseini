<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy ERP export has no phone numbers for most contacts and no national IDs for
 * employees. Rather than fabricate placeholder values to satisfy NOT NULL, the columns
 * become nullable (MySQL allows multiple NULLs through a UNIQUE index).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->change();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->string('national_id', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable(false)->change();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable(false)->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
            $table->string('national_id', 20)->nullable(false)->change();
        });
    }
};
