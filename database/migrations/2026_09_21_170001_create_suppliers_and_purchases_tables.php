<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // 1. جدول الموردين وشركات تصنيع وتوزيع البطاريات
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150); // اسم المسؤول أو جهة الاتصال
            $table->string('company_name', 150)->index(); // شركة كلورايد، شركة فارتا، إيه سي ديلكو
            $table->string('phone', 30)->unique();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('tax_number', 50)->nullable(); // الرقم الضريبي
            $table->string('commercial_register', 50)->nullable(); // السجل التجاري
            $table->string('address', 255)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0); // سقف المديونية المتاح للمركز
            $table->decimal('current_balance', 12, 2)->default(0); // رصيد المورد الدائن (المستحق له)
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. جدول فواتير المشتريات وشحنات البطاريات الواردة
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique()->index(); // رقم فاتورة المشتريات
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete(); // أمين المخزن أو المستخدم المستلم
            $table->date('invoice_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0); // المتبقي للمورد ديناً
            $table->enum('payment_status', ['paid', 'partially_paid', 'unpaid'])->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'invoice_date']);
        });

        // 3. جدول بنود فاتورة المشتريات
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost_price', 10, 2); // سعر الشراء للوحدة
            $table->decimal('total_cost_price', 12, 2);
            $table->string('batch_number', 50)->nullable(); // رقم الشحنة/التشغيلة
            $table->date('production_date')->nullable(); // تاريخ إنتاج البطاريات
            $table->timestamps();
        });

        // 4. دفتر أستاذ حسابات الموردين (Supplier Ledger Double-Entry)
        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('entry_type', ['purchase_invoice', 'supplier_payment', 'purchase_return', 'adjustment'])->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque'])->default('cash');
            $table->string('cheque_number', 50)->nullable();
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_ledger_entries');
        Schema::dropIfExists('purchase_invoice_items');
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('suppliers');
    }
};
