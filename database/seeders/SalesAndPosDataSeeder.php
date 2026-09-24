<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Employee;
use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Warranty;
use App\Models\CreditLedgerEntry;
use App\Models\ScrapBatteriesInventory;
use App\Models\TechnicianCommission;
use App\Models\Attendance;
use Carbon\Carbon;

class SalesAndPosDataSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::first() ?? Branch::create([
            'code' => 'MAIN',
            'name' => 'فرع دمياط الجديدة',
            'phone' => '0572400000',
            'address' => 'شارع المحجوب، دمياط الجديدة',
            'is_active' => true,
        ]);

        $cashierUser = User::where('email', 'admin@alhusseini.com')->first() ?? User::first();
        $technician = Employee::where('status', 'active')->first();

        // 1. الفئات الرئيسية
        $batteryCat = Category::firstOrCreate(['slug' => 'batteries'], ['name' => 'بطاريات السيارات']);
        $oilCat = Category::firstOrCreate(['slug' => 'oils'], ['name' => 'زيوت المحركات والفلاتر']);
        $greaseCat = Category::firstOrCreate(['slug' => 'greases'], ['name' => 'شحوم وسوائل تبريد']);
        $serviceCat = Category::firstOrCreate(['slug' => 'services'], ['name' => 'صيانة وخدمات الورشة']);

        // 2. دليل المنتجات المتكامل
        $productsData = [
            // بطاريات
            [
                'category_id' => $batteryCat->id,
                'sku' => 'BAT-CHL-70G',
                'barcode' => '6221001000701',
                'name' => 'بطارية كلورايد 70 أمبير جولد (Chloride Gold 70Ah)',
                'brand' => 'Chloride',
                'capacity_ah' => '70',
                'warranty_months' => 12,
                'cost_price' => 2000.00,
                'retail_price' => 3200.00,
                'wholesale_price' => 2900.00,
                'current_stock' => 14,
                'reorder_threshold' => 5,
                'is_battery' => true,
            ],
            [
                'category_id' => $batteryCat->id,
                'sku' => 'BAT-VAR-70B',
                'barcode' => '4016987119532',
                'name' => 'بطارية فارتا 70 أمبير بلو ديناميك (Varta Blue 70Ah)',
                'brand' => 'Varta',
                'capacity_ah' => '70',
                'warranty_months' => 12,
                'cost_price' => 2300.00,
                'retail_price' => 3600.00,
                'wholesale_price' => 3300.00,
                'current_stock' => 9,
                'reorder_threshold' => 4,
                'is_battery' => true,
            ],
            [
                'category_id' => $batteryCat->id,
                'sku' => 'BAT-ACD-55R',
                'barcode' => '808709000554',
                'name' => 'بطارية إيه سي ديلكو 55 أمبير (ACDelco 55Ah)',
                'brand' => 'ACDelco',
                'capacity_ah' => '55',
                'warranty_months' => 12,
                'cost_price' => 1600.00,
                'retail_price' => 2500.00,
                'wholesale_price' => 2300.00,
                'current_stock' => 3, // Low stock alert!
                'reorder_threshold' => 5,
                'is_battery' => true,
            ],
            [
                'category_id' => $batteryCat->id,
                'sku' => 'BAT-HAN-80K',
                'barcode' => '8801234567890',
                'name' => 'بطارية هانكوك كوري 80 أمبير جافة (Hankook MF 80Ah)',
                'brand' => 'Hankook',
                'capacity_ah' => '80',
                'warranty_months' => 18,
                'cost_price' => 2600.00,
                'retail_price' => 3950.00,
                'wholesale_price' => 3650.00,
                'current_stock' => 2, // Low stock alert!
                'reorder_threshold' => 4,
                'is_battery' => true,
            ],
            [
                'category_id' => $batteryCat->id,
                'sku' => 'BAT-MUT-100T',
                'barcode' => '8690123456789',
                'name' => 'بطارية موتلو تركي 100 أمبير نقل (Mutlu 100Ah)',
                'brand' => 'Mutlu',
                'capacity_ah' => '100',
                'warranty_months' => 12,
                'cost_price' => 3100.00,
                'retail_price' => 4800.00,
                'wholesale_price' => 4400.00,
                'current_stock' => 7,
                'reorder_threshold' => 3,
                'is_battery' => true,
            ],
            // زيوت وفلاتر
            [
                'category_id' => $oilCat->id,
                'sku' => 'OIL-SHL-5W40-4L',
                'barcode' => '5011987004455',
                'name' => 'زيت شل هيلكس الترا 5W-40 تخليقي بالكامل 4 لتر',
                'brand' => 'Shell',
                'capacity_ah' => null,
                'warranty_months' => 0,
                'cost_price' => 1100.00,
                'retail_price' => 1650.00,
                'wholesale_price' => 1450.00,
                'current_stock' => 22,
                'reorder_threshold' => 8,
                'is_battery' => false,
            ],
            [
                'category_id' => $oilCat->id,
                'sku' => 'OIL-MOB-10W40-4L',
                'barcode' => '5011987009988',
                'name' => 'زيت موبيل سوبر 2000 10W-40 نصف تخليقي 4 لتر',
                'brand' => 'Mobil',
                'capacity_ah' => null,
                'warranty_months' => 0,
                'cost_price' => 750.00,
                'retail_price' => 1100.00,
                'wholesale_price' => 950.00,
                'current_stock' => 4, // Low stock alert!
                'reorder_threshold' => 6,
                'is_battery' => false,
            ],
            [
                'category_id' => $oilCat->id,
                'sku' => 'OIL-CAS-5W30-4L',
                'barcode' => '5011987007711',
                'name' => 'زيت كاسترول إيدج 5W-30 تيتانيوم FST 4 لتر',
                'brand' => 'Castrol',
                'capacity_ah' => null,
                'warranty_months' => 0,
                'cost_price' => 1250.00,
                'retail_price' => 1850.00,
                'wholesale_price' => 1650.00,
                'current_stock' => 11,
                'reorder_threshold' => 4,
                'is_battery' => false,
            ],
            // شحوم وسوائل تبريد
            [
                'category_id' => $greaseCat->id,
                'sku' => 'COOL-MOB-RD-4L',
                'barcode' => '6223004001122',
                'name' => 'مياه رادياتير موبيل حمراء 33% عضوية 4 لتر',
                'brand' => 'Mobil Coolant',
                'capacity_ah' => null,
                'warranty_months' => 0,
                'cost_price' => 220.00,
                'retail_price' => 380.00,
                'wholesale_price' => 320.00,
                'current_stock' => 18,
                'reorder_threshold' => 5,
                'is_battery' => false,
            ],
            // صيانة وخدمات
            [
                'category_id' => $serviceCat->id,
                'sku' => 'SRV-BAT-SCAN',
                'barcode' => '9990001',
                'name' => 'فحص كفاءة الدينامو والمارش وكمبيوتر السيارة',
                'brand' => 'خدمة ورشة',
                'capacity_ah' => null,
                'warranty_months' => 0,
                'cost_price' => 0.00,
                'retail_price' => 150.00,
                'wholesale_price' => 100.00,
                'current_stock' => 999,
                'reorder_threshold' => 0,
                'is_battery' => false,
            ],
            [
                'category_id' => $serviceCat->id,
                'sku' => 'SRV-BAT-CHARGE',
                'barcode' => '9990002',
                'name' => 'شحن وتنشيط بطارية خارجي ومتابعة الحامض',
                'brand' => 'خدمة ورشة',
                'capacity_ah' => null,
                'warranty_months' => 0,
                'cost_price' => 20.00,
                'retail_price' => 100.00,
                'wholesale_price' => 80.00,
                'current_stock' => 999,
                'reorder_threshold' => 0,
                'is_battery' => false,
            ],
        ];

        $products = [];
        foreach ($productsData as $p) {
            $prod = Product::updateOrCreate(
                ['sku' => $p['sku']],
                array_merge($p, ['voltage' => '12V', 'terminal_type' => 'regular', 'is_active' => true])
            );
            $products[$p['sku']] = $prod;
        }

        // 3. العملاء والمركبات (نقدي وآجل)
        $customersData = [
            [
                'name' => 'أحمد فتحي الشناوي',
                'phone' => '01091234567',
                'credit_limit' => 0.00,
                'current_credit_balance' => 0.00,
                'tier' => 'standard',
                'vehicles' => [
                    ['car_brand' => 'تويوتا', 'car_model' => 'كورولا 2021', 'plate_number' => 'ط أ ج 1845']
                ]
            ],
            [
                'name' => 'م. حسن العيسوي (مقاولات)',
                'phone' => '01221234568',
                'credit_limit' => 25000.00,
                'current_credit_balance' => 8400.00,
                'tier' => 'vip',
                'vehicles' => [
                    ['car_brand' => 'شيفروليه', 'car_model' => 'جامبو نقل', 'plate_number' => 'د ف ر 9134'],
                    ['car_brand' => 'ميتسوبيشي', 'car_model' => 'باجيرو 2020', 'plate_number' => 'ق س و 4412']
                ]
            ],
            [
                'name' => 'ورشة الأمل لصيانة وتعديل السيارات',
                'phone' => '01115556677',
                'credit_limit' => 40000.00,
                'current_credit_balance' => 16500.00,
                'tier' => 'fleet',
                'vehicles' => [
                    ['car_brand' => 'هيونداي', 'car_model' => 'إلنترا CN7', 'plate_number' => 'ر ب ع 5219'],
                    ['car_brand' => 'كيا', 'car_model' => 'سيراتو 2019', 'plate_number' => 'س ن ص 7381']
                ]
            ],
            [
                'name' => 'إبراهيم مسعد القطان',
                'phone' => '01007891234',
                'credit_limit' => 10000.00,
                'current_credit_balance' => 3200.00,
                'tier' => 'standard',
                'vehicles' => [
                    ['car_brand' => 'نيسان', 'car_model' => 'صني N17', 'plate_number' => 'ط هـ ر 8821']
                ]
            ],
            [
                'name' => 'د. محمود عبد العزيز البنا',
                'phone' => '01289998877',
                'credit_limit' => 0.00,
                'current_credit_balance' => 0.00,
                'tier' => 'standard',
                'vehicles' => [
                    ['car_brand' => 'مرسيدس', 'car_model' => 'C180 2022', 'plate_number' => 'أ ل م 3122']
                ]
            ],
            [
                'name' => 'شركة الدلتا للنقل الجماعي',
                'phone' => '01063334411',
                'credit_limit' => 60000.00,
                'current_credit_balance' => 24800.00,
                'tier' => 'fleet',
                'vehicles' => [
                    ['car_brand' => 'تويوتا', 'car_model' => 'ميكروباص هاي إيس', 'plate_number' => 'د ط ق 6620']
                ]
            ],
        ];

        $seededCustomers = [];
        $seededVehicles = [];
        foreach ($customersData as $cd) {
            $vehicles = $cd['vehicles'];
            unset($cd['vehicles']);
            $cust = Customer::updateOrCreate(
                ['phone' => $cd['phone']],
                array_merge($cd, ['is_active' => true])
            );
            $seededCustomers[] = $cust;

            foreach ($vehicles as $vd) {
                $veh = CustomerVehicle::firstOrCreate(
                    ['customer_id' => $cust->id, 'plate_number' => $vd['plate_number']],
                    $vd
                );
                $seededVehicles[$cust->id][] = $veh;
            }

            // إذا كان للعميل رصيد مديونية، سجل حركة رصيد افتتاحي في دفتر الأستاذ
            if ($cust->current_credit_balance > 0) {
                CreditLedgerEntry::firstOrCreate(
                    ['customer_id' => $cust->id, 'entry_type' => 'invoice_debt'],
                    [
                        'amount' => $cust->current_credit_balance,
                        'balance_before' => 0.00,
                        'balance_after' => $cust->current_credit_balance,
                        'collected_by' => $cashierUser?->id ?? 1,
                        'receipt_number' => 'REC-INIT-' . $cust->id,
                        'notes' => 'رصيد مديونية آجل مستحق عن فواتير سابقة',
                        'created_at' => Carbon::now()->subDays(rand(2, 6)),
                    ]
                );
            }
        }

        // 4. فواتير مبيعات واقعية للأيام الماضية (لإنعاش الرسومات البيانية وجداول المعاينة)
        $invoicesSeeds = [
            [
                'days_ago' => 0,
                'cust_idx' => 0,
                'product_sku' => 'BAT-CHL-70G',
                'qty' => 1,
                'unit_price' => 3200.00,
                'scrap_deduction' => 500.00,
                'payment_method' => 'cash',
                'status' => 'paid',
                'is_battery' => true,
                'serial' => 'SN-CHL-'.rand(100000, 999999),
            ],
            [
                'days_ago' => 0,
                'cust_idx' => 4,
                'product_sku' => 'OIL-SHL-5W40-4L',
                'qty' => 1,
                'unit_price' => 1650.00,
                'scrap_deduction' => 0.00,
                'payment_method' => 'bank_transfer',
                'status' => 'paid',
                'is_battery' => false,
                'serial' => null,
            ],
            [
                'days_ago' => 1,
                'cust_idx' => 1,
                'product_sku' => 'BAT-VAR-70B',
                'qty' => 2,
                'unit_price' => 3600.00,
                'scrap_deduction' => 1000.00,
                'payment_method' => 'credit',
                'status' => 'unpaid',
                'is_battery' => true,
                'serial' => 'SN-VRT-'.rand(100000, 999999),
            ],
            [
                'days_ago' => 2,
                'cust_idx' => 2,
                'product_sku' => 'BAT-MUT-100T',
                'qty' => 3,
                'unit_price' => 4800.00,
                'scrap_deduction' => 1800.00,
                'payment_method' => 'credit',
                'status' => 'partially_paid',
                'is_battery' => true,
                'serial' => 'SN-MUT-'.rand(100000, 999999),
            ],
            [
                'days_ago' => 3,
                'cust_idx' => 3,
                'product_sku' => 'BAT-ACD-55R',
                'qty' => 1,
                'unit_price' => 2500.00,
                'scrap_deduction' => 400.00,
                'payment_method' => 'card',
                'status' => 'paid',
                'is_battery' => true,
                'serial' => 'SN-ACD-'.rand(100000, 999999),
            ],
            [
                'days_ago' => 4,
                'cust_idx' => 0,
                'product_sku' => 'OIL-MOB-10W40-4L',
                'qty' => 2,
                'unit_price' => 1100.00,
                'scrap_deduction' => 0.00,
                'payment_method' => 'cash',
                'status' => 'paid',
                'is_battery' => false,
                'serial' => null,
            ],
            [
                'days_ago' => 5,
                'cust_idx' => 5,
                'product_sku' => 'BAT-HAN-80K',
                'qty' => 2,
                'unit_price' => 3950.00,
                'scrap_deduction' => 1200.00,
                'payment_method' => 'credit',
                'status' => 'partially_paid',
                'is_battery' => true,
                'serial' => 'SN-HAN-'.rand(100000, 999999),
            ],
            [
                'days_ago' => 6,
                'cust_idx' => 4,
                'product_sku' => 'SRV-BAT-SCAN',
                'qty' => 1,
                'unit_price' => 150.00,
                'scrap_deduction' => 0.00,
                'payment_method' => 'cash',
                'status' => 'paid',
                'is_battery' => false,
                'serial' => null,
            ],
        ];

        foreach ($invoicesSeeds as $i => $seed) {
            $cust = $seededCustomers[$seed['cust_idx']];
            $veh = $seededVehicles[$cust->id][0] ?? null;
            $prod = $products[$seed['product_sku']];
            $date = Carbon::now()->subDays($seed['days_ago'])->setTime(rand(10, 19), rand(10, 50));

            $subtotal = $seed['unit_price'] * $seed['qty'];
            $finalAmount = max(0, $subtotal - $seed['scrap_deduction']);
            $paidAmount = match($seed['status']) {
                'paid' => $finalAmount,
                'unpaid' => 0.00,
                'partially_paid' => round($finalAmount * 0.4, 2),
                default => $finalAmount,
            };
            $remaining = $finalAmount - $paidAmount;

            $invNum = 'INV-' . $date->format('ymd') . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $invoice = Invoice::updateOrCreate(
                ['invoice_number' => $invNum],
                [
                    'branch_id' => $branch->id,
                    'customer_id' => $cust->id,
                    'customer_vehicle_id' => $veh?->id,
                    'technician_id' => $technician?->id,
                    'cashier_id' => $cashierUser?->id ?? 1,
                    'subtotal' => $subtotal,
                    'discount_amount' => 0.00,
                    'scrap_deduction_amount' => $seed['scrap_deduction'],
                    'tax_amount' => 0.00,
                    'final_amount' => $finalAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remaining,
                    'payment_method' => $seed['payment_method'],
                    'status' => $seed['status'],
                    'notes' => 'فاتورة معتمدة صادرة من نقطة البيع',
                    'created_at' => $date,
                    'updated_at' => $date,
                ]
            );

            // صنف الفاتورة
            $item = InvoiceItem::firstOrCreate(
                ['invoice_id' => $invoice->id, 'product_id' => $prod->id],
                [
                    'quantity' => $seed['qty'],
                    'unit_price' => $seed['unit_price'],
                    'total_price' => $subtotal,
                    'battery_serial_number' => $seed['serial'],
                    'warranty_duration_months' => $prod->warranty_months ?: 12,
                ]
            );

            // سجل الدفع
            if ($paidAmount > 0) {
                $payMethod = match($seed['payment_method']) {
                    'instapay' => 'bank_transfer',
                    'credit' => 'credit',
                    'card' => 'card',
                    default => 'cash',
                };
                InvoicePayment::firstOrCreate(
                    ['invoice_id' => $invoice->id, 'payment_method' => $payMethod],
                    [
                        'amount' => $paidAmount,
                        'transaction_reference' => 'PAY-' . rand(100000, 999999),
                        'notes' => 'تحصيل مباشر بالخزينة',
                    ]
                );
            }

            // شهادة ضمان إذا كان بطارية
            if ($seed['is_battery'] && $seed['serial']) {
                Warranty::firstOrCreate(
                    ['serial_number' => $seed['serial']],
                    [
                        'invoice_item_id' => $item->id,
                        'customer_id' => $cust->id,
                        'customer_vehicle_id' => $veh?->id,
                        'start_date' => $date->toDateString(),
                        'end_date' => $date->copy()->addMonths($prod->warranty_months ?: 12)->toDateString(),
                        'status' => 'active',
                    ]
                );
            }

            // بطارية كهنة مسترجعة إذا وجد خصم كهنة
            if ($seed['scrap_deduction'] > 0) {
                ScrapBatteriesInventory::firstOrCreate(
                    ['invoice_id' => $invoice->id],
                    [
                        'branch_id' => $branch->id,
                        'capacity_ah' => ($prod->capacity_ah ?? '70') . 'Ah',
                        'scrap_value' => $seed['scrap_deduction'],
                        'lead_weight_kg' => round(($prod->capacity_ah ?: 70) * 0.28, 2),
                        'status' => 'in_stock',
                        'received_by' => $technician?->id ?? 1,
                        'created_at' => $date,
                    ]
                );
            }

            // عمولة فني
            if ($technician) {
                TechnicianCommission::firstOrCreate(
                    ['invoice_id' => $invoice->id, 'employee_id' => $technician->id],
                    [
                        'commission_amount' => 50.00,
                        'status' => 'approved',
                    ]
                );
            }
        }

        // 5. حضور اليوم لفنيي الورشة (لإظهار بطاقات الحضور الحية على الداشبورد)
        $today = Carbon::today();
        $workshopTechs = Employee::where('status', 'active')->take(6)->get();

        foreach ($workshopTechs as $idx => $tech) {
            $status = match($idx % 3) {
                0 => 'present',
                1 => 'late',
                2 => 'present',
            };
            $lateMin = $status === 'late' ? 25 : 0;
            $checkIn = $status === 'late' ? $today->copy()->setTime(9, 25) : $today->copy()->setTime(8, 55);

            Attendance::updateOrCreate(
                ['employee_id' => $tech->id, 'work_date' => $today->toDateString()],
                [
                    'check_in' => $checkIn,
                    'late_minutes' => $lateMin,
                    'status' => $status,
                    'source' => 'manual',
                ]
            );
        }
    }
}
