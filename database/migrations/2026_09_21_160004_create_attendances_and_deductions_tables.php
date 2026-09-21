<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->decimal('overtime_hours', 4, 2)->default(0);
            $table->enum('status', ['present', 'absent', 'late', 'excused', 'holiday'])->default('present');
            $table->string('source', 30)->default('zkteco');
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });

        Schema::create('deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', ['lateness', 'absence', 'disciplinary', 'loan'])->index();
            $table->enum('calculation_method', ['fixed_amount', 'hourly_rate_multiplier', 'day_wage_multiplier']);
            $table->decimal('multiplier_value', 6, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->date('deduction_date');
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'applied', 'cancelled'])->default('pending')->index();
            $table->timestamps();

            $table->index(['employee_id', 'status', 'deduction_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('employee_deductions');
        Schema::dropIfExists('deduction_rules');
        Schema::dropIfExists('attendances');
    }
};
