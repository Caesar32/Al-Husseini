<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. جدول الموظفين (Employees)
        Schema::table('employees', function (Blueprint $table) {
            $table->index('full_name', 'employees_full_name_index');
            $table->index(['branch_id', 'status'], 'employees_branch_status_index');
            $table->index(['branch_id', 'full_name'], 'employees_branch_full_name_index');
        });

        // 2. جدول العملاء (Customers)
        Schema::table('customers', function (Blueprint $table) {
            $table->index('name', 'customers_name_index');
            $table->index('national_id', 'customers_national_id_index');
            $table->index(['is_active', 'name'], 'customers_is_active_name_index');
        });

        // 3. جدول مركبات العملاء (Customer Vehicles)
        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->index('chassis_number', 'customer_vehicles_chassis_number_index');
            $table->index(['car_brand', 'car_model'], 'customer_vehicles_brand_model_index');
        });

        // 4. جدول المنتجات والبطاريات (Products)
        Schema::table('products', function (Blueprint $table) {
            $table->index('name', 'products_name_index');
            $table->index('brand', 'products_brand_index');
            $table->index(['category_id', 'is_active'], 'products_category_is_active_index');
            $table->index(['is_battery', 'is_active', 'brand'], 'products_battery_active_brand_index');
        });

        // 5. جدول الفواتير (Invoices)
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['customer_id', 'created_at'], 'invoices_customer_created_index');
            $table->index(['technician_id', 'created_at'], 'invoices_technician_created_index');
        });

        // 6. جدول بنود الفواتير (Invoice Items)
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->index(['product_id', 'created_at'], 'invoice_items_product_created_index');
        });

        // 7. جدول الضمانات (Warranties)
        Schema::table('warranties', function (Blueprint $table) {
            $table->index(['customer_id', 'status'], 'warranties_customer_status_index');
            $table->index(['status', 'end_date'], 'warranties_status_end_date_index');
        });

        // 8. جدول الموردين (Suppliers)
        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('name', 'suppliers_name_index');
            $table->index('tax_number', 'suppliers_tax_number_index');
            $table->index('commercial_register', 'suppliers_commercial_register_index');
        });

        // 9. جدول إجازات الموظفين (Employee Leaves)
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->index(['start_date', 'end_date', 'status'], 'employee_leaves_dates_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropIndex('employee_leaves_dates_status_index');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_name_index');
            $table->dropIndex('suppliers_tax_number_index');
            $table->dropIndex('suppliers_commercial_register_index');
        });

        Schema::table('warranties', function (Blueprint $table) {
            $table->dropIndex('warranties_customer_status_index');
            $table->dropIndex('warranties_status_end_date_index');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex('invoice_items_product_created_index');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_customer_created_index');
            $table->dropIndex('invoices_technician_created_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_name_index');
            $table->dropIndex('products_brand_index');
            $table->dropIndex('products_category_is_active_index');
            $table->dropIndex('products_battery_active_brand_index');
        });

        Schema::table('customer_vehicles', function (Blueprint $table) {
            $table->dropIndex('customer_vehicles_chassis_number_index');
            $table->dropIndex('customer_vehicles_brand_model_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_name_index');
            $table->dropIndex('customers_national_id_index');
            $table->dropIndex('customers_is_active_name_index');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('employees_full_name_index');
            $table->dropIndex('employees_branch_status_index');
            $table->dropIndex('employees_branch_full_name_index');
        });
    }
};
