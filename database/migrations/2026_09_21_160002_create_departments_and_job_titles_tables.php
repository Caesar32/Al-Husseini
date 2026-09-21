<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->unique();
            $table->timestamps();
        });

        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->decimal('min_salary', 10, 2)->default(0);
            $table->decimal('max_salary', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'title']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_titles');
        Schema::dropIfExists('departments');
    }
};
