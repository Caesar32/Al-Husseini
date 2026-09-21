<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_code', 30)->unique();
            $table->string('full_name', 150);
            $table->string('national_id', 20)->unique();
            $table->string('phone', 20)->unique();
            $table->date('hire_date');
            $table->time('shift_start_time')->default('09:00:00');
            $table->time('shift_end_time')->default('17:00:00');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);
            $table->string('zkteco_pin', 50)->nullable()->unique();
            $table->enum('status', ['active', 'on_leave', 'terminated'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('housing_allowance', 10, 2)->default(0);
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamps();

            $table->index(['employee_id', 'is_current']);
        });

        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('leave_type', ['annual', 'sick', 'emergency', 'unpaid'])->default('annual');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count')->default(1);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('action_notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'start_date', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('employees');
    }
};
