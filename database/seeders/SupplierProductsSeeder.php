<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;

class SupplierProductsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. الفئات الرئيسية
        $batteryCategory = Category::firstOrCreate(['slug' => 'batteries'], [
            'name' => 'بطاريات السيارات',
        ]);

        $oilCategory = Category::firstOrCreate(['slug' => 'oils'], [
            'name' => 'زيوت المحركات والفلاتر',
        ]);

        // 2. الموردين المعتمدين
        $chlorideSupplier = Supplier::firstOrCreate(['company_name' => 'شركة كلورايد إيجيبت لتصنيع البطاريات'], [
            'name' => 'م. طارق العوضي - مسؤول التوزيع',
            'phone' => '01001234567',
            'alt_phone' => '01201234567',
            'email' => 'sales@chloride-egypt.com',
            'tax_number' => '100-234-567',
            'commercial_register' => '88912',
            'address' => 'المنطقة الصناعية، العاشر من رمضان',
            'credit_limit' => 200000.00,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        $ahramSupplier = Supplier::firstOrCreate(['company_name' => 'شركة الأهرام للتجارة والتوزيع (وكيل فارتا)'], [
            'name' => 'أ. حسام عبد الرحيم',
            'phone' => '01109876543',
            'email' => 'contact@ahram-dist.com',
            'tax_number' => '300-888-999',
            'commercial_register' => '44512',
            'address' => 'ش الجمهورية، القاهرة',
            'credit_limit' => 150000.00,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        $mansourSupplier = Supplier::firstOrCreate(['company_name' => 'مؤسسة المنصور لتوريد قطع الغيار والبطاريات'], [
            'name' => 'أ. هيثم المنصور',
            'phone' => '01223344556',
            'email' => 'orders@mansour-auto.com',
            'tax_number' => '400-555-666',
            'commercial_register' => '77819',
            'address' => 'شارع رمسيس، القاهرة',
            'credit_limit' => 100000.00,
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        // 3. المنتجات
        $p1 = Product::firstOrCreate(['sku' => 'BAT-CHL-70G'], [
            'category_id' => $batteryCategory->id,
            'barcode' => '6221001000701',
            'name' => 'بطارية كلورايد 70 أمبير جولد (Chloride Gold 70Ah)',
            'brand' => 'Chloride',
            'capacity_ah' => '70',
            'voltage' => '12V',
            'terminal_type' => 'regular',
            'warranty_months' => 12,
            'cost_price' => 2000.00,
            'retail_price' => 3200.00,
            'wholesale_price' => 2900.00,
            'current_stock' => 15,
            'reorder_threshold' => 5,
            'is_battery' => true,
            'is_active' => true,
        ]);

        $p2 = Product::firstOrCreate(['sku' => 'BAT-VAR-70B'], [
            'category_id' => $batteryCategory->id,
            'barcode' => '4016987119532',
            'name' => 'بطارية فارتا 70 أمبير بلو ديناميك (Varta Blue 70Ah)',
            'brand' => 'Varta',
            'capacity_ah' => '70',
            'voltage' => '12V',
            'terminal_type' => 'regular',
            'warranty_months' => 12,
            'cost_price' => 2300.00,
            'retail_price' => 3600.00,
            'wholesale_price' => 3300.00,
            'current_stock' => 10,
            'reorder_threshold' => 3,
            'is_battery' => true,
            'is_active' => true,
        ]);

        $p3 = Product::firstOrCreate(['sku' => 'BAT-ACD-55R'], [
            'category_id' => $batteryCategory->id,
            'barcode' => '808709000554',
            'name' => 'بطارية إيه سي ديلكو 55 أمبير (ACDelco 55Ah)',
            'brand' => 'ACDelco',
            'capacity_ah' => '55',
            'voltage' => '12V',
            'terminal_type' => 'regular',
            'warranty_months' => 12,
            'cost_price' => 1600.00,
            'retail_price' => 2500.00,
            'wholesale_price' => 2300.00,
            'current_stock' => 12,
            'reorder_threshold' => 4,
            'is_battery' => true,
            'is_active' => true,
        ]);

        $p4 = Product::firstOrCreate(['sku' => 'OIL-SHL-5W40-4L'], [
            'category_id' => $oilCategory->id,
            'barcode' => '5011987004455',
            'name' => 'زيت شل هيلكس الترا 5W-40 تخليقي بالكامل 4 لتر',
            'brand' => 'Shell',
            'capacity_ah' => null,
            'voltage' => '12V',
            'terminal_type' => 'regular',
            'warranty_months' => 0,
            'cost_price' => 1100.00,
            'retail_price' => 1650.00,
            'wholesale_price' => 1450.00,
            'current_stock' => 25,
            'reorder_threshold' => 8,
            'is_battery' => false,
            'is_active' => true,
        ]);

        // 4. الكتالوج متعدد الموردين (Multi-Supplier Links):
        // المنتج 1: بطارية كلورايد 70 أمبير يوردها موردان:
        // أ) شركة كلورايد إيجيبت (المورد الأساسي) بسعر 2000 ج.م
        SupplierProduct::updateOrCreate(
            ['supplier_id' => $chlorideSupplier->id, 'product_id' => $p1->id],
            [
                'supplier_sku' => 'CHL-EGY-70G',
                'last_purchase_price' => 2000.00,
                'min_order_qty' => 5,
                'lead_time_days' => 1,
                'is_primary_supplier' => true,
                'notes' => 'المورد المصنّع الأساسي المفضل لبطاريات كلورايد',
            ]
        );

        // ب) شركة الأهرام (مورد ثانوي/بديل) بسعر 2150 ج.م
        SupplierProduct::updateOrCreate(
            ['supplier_id' => $ahramSupplier->id, 'product_id' => $p1->id],
            [
                'supplier_sku' => 'AHR-CHL-70',
                'last_purchase_price' => 2150.00,
                'min_order_qty' => 2,
                'lead_time_days' => 2,
                'is_primary_supplier' => false,
                'notes' => 'مورد بديل في حال نقص المخزون لدى الشركة الأم',
            ]
        );

        // المنتج 2: بطارية فارتا 70 يوردها الأهرام (أساسي) والمنصور (بديل)
        SupplierProduct::updateOrCreate(
            ['supplier_id' => $ahramSupplier->id, 'product_id' => $p2->id],
            [
                'supplier_sku' => 'VRT-BLUE-70',
                'last_purchase_price' => 2300.00,
                'min_order_qty' => 3,
                'lead_time_days' => 2,
                'is_primary_supplier' => true,
            ]
        );

        SupplierProduct::updateOrCreate(
            ['supplier_id' => $mansourSupplier->id, 'product_id' => $p2->id],
            [
                'supplier_sku' => 'MNS-V70-B',
                'last_purchase_price' => 2400.00,
                'min_order_qty' => 1,
                'lead_time_days' => 1,
                'is_primary_supplier' => false,
            ]
        );

        // المنتج 3: بطارية إيه سي ديلكو يوردها المنصور
        SupplierProduct::updateOrCreate(
            ['supplier_id' => $mansourSupplier->id, 'product_id' => $p3->id],
            [
                'supplier_sku' => 'ACD-55-MF',
                'last_purchase_price' => 1600.00,
                'min_order_qty' => 4,
                'lead_time_days' => 1,
                'is_primary_supplier' => true,
            ]
        );
    }
}
