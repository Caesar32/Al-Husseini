<?php

namespace Tests\Feature\Sales;

use App\Contracts\Purchases\PurchaseServiceInterface;
use App\Contracts\Purchases\SupplierServiceInterface;
use App\Contracts\Sales\PosOrderServiceInterface;
use App\Contracts\Sales\ScrapBatteryServiceInterface;
use App\Contracts\Sales\WarrantyServiceInterface;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ScrapBatteriesInventory;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\TechnicianCommission;
use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndToEndSalesAndPurchasesScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $manager;
    protected Branch $branch;
    protected Employee $technician;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->branch = Branch::first();
        $this->cashier = User::first();
        $this->manager = User::first();
        $this->technician = Employee::first();
    }

    /**
     * القطاع 1: الكتالوج متعدد الموردين وتوريد شحنات متتالية مع دقة المتوسط المرجح WAC
     */
    public function test_sector_1_multi_supplier_catalog_and_wac_valuation_across_shipments(): void
    {
        $supplierService = app(SupplierServiceInterface::class);
        $purchaseService = app(PurchaseServiceInterface::class);

        // إنشاء المورد (أ) والمورد (ب)
        $supplierA = $supplierService->createSupplier([
            'name'         => 'محي الدين البشبيشي',
            'company_name' => 'شركة كلورايد مصر للتوزيع',
            'phone'        => '01011112222',
            'credit_limit' => 300000,
        ]);

        $supplierB = $supplierService->createSupplier([
            'name'         => 'سامح المنزلاوي',
            'company_name' => 'شركة الدلتا للتجارة والتوريدات',
            'phone'        => '01033334444',
            'credit_limit' => 200000,
        ]);

        // اختيار منتج البطارية كلورايد 70 أمبير وتصفير رصيدها
        $battery = Product::where('is_battery', true)->first();
        $battery->update([
            'current_stock' => 0,
            'cost_price'    => 0.00,
            'retail_price'  => 3200.00,
        ]);

        // الشحنة الأولى: توريد 10 بطاريات من المورد (أ) بسعر 2,000 ج.م للوحدة (إجمالي 20,000 ج.م)
        // سداد 5,000 ج.م كاش، والمتبقي 15,000 ج.م آجل
        $invoice1 = $purchaseService->createDirectPurchase([
            'supplier_id'     => $supplierA->id,
            'branch_id'       => $this->branch->id,
            'invoice_number'  => 'PUR-SEC1-01',
            'invoice_date'    => '2026-09-23',
            'items'           => [
                [
                    'product_id'      => $battery->id,
                    'quantity'        => 10,
                    'unit_cost_price' => 2000.00,
                    'supplier_sku'    => 'CHL-70AH-SUP-A',
                ],
            ],
            'paid_amount'     => 5000.00,
            'payment_method'  => 'cash',
        ], $this->cashier->id);

        $battery->refresh();
        $supplierA->refresh();

        $this->assertEquals(10, $battery->current_stock);
        $this->assertEquals(2000.00, (float) $battery->cost_price);
        $this->assertEquals(15000.00, (float) $supplierA->current_balance);

        // الشحنة الثانية: توريد 10 بطاريات إضافية من المورد (ب) بسعر 2,200 ج.م للوحدة (إجمالي 22,000 ج.م)
        // سداد 2,000 ج.م كاش، والمتبقي 20,000 ج.م آجل
        // معادلة المتوسط المرجح WAC المتوقعة:
        // ( (10 * 2000) + (10 * 2200) ) / (10 + 10) = (20,000 + 22,000) / 20 = 42,000 / 20 = 2,100.00 ج.م
        $invoice2 = $purchaseService->createDirectPurchase([
            'supplier_id'     => $supplierB->id,
            'branch_id'       => $this->branch->id,
            'invoice_number'  => 'PUR-SEC1-02',
            'invoice_date'    => '2026-09-23',
            'items'           => [
                [
                    'product_id'      => $battery->id,
                    'quantity'        => 10,
                    'unit_cost_price' => 2200.00,
                    'supplier_sku'    => 'CHL-70AH-SUP-B',
                ],
            ],
            'paid_amount'     => 2000.00,
            'payment_method'  => 'cash',
        ], $this->cashier->id);

        $battery->refresh();
        $supplierB->refresh();

        $this->assertEquals(20, $battery->current_stock);
        $this->assertEquals(2100.00, (float) $battery->cost_price);
        $this->assertEquals(20000.00, (float) $supplierB->current_balance);

        // فحص كتالوج الموردين المتعدد للبطارية: التحقق من تسجيل سعر كل مورد والـ SKU الخاص به
        $supplierAProducts = DB::table('supplier_products')->where('supplier_id', $supplierA->id)->where('product_id', $battery->id)->first();
        $supplierBProducts = DB::table('supplier_products')->where('supplier_id', $supplierB->id)->where('product_id', $battery->id)->first();

        $this->assertNotNull($supplierAProducts);
        $this->assertEquals('CHL-70AH-SUP-A', $supplierAProducts->supplier_sku);
        $this->assertEquals(2000.00, (float) $supplierAProducts->last_purchase_price);

        $this->assertNotNull($supplierBProducts);
        $this->assertEquals('CHL-70AH-SUP-B', $supplierBProducts->supplier_sku);
        $this->assertEquals(2200.00, (float) $supplierBProducts->last_purchase_price);

        // اختبار سداد دفعة للمورد (أ) بمبلغ 5,000 ج.م وتحديث دفتر الأستاذ
        $paymentEntry = $purchaseService->recordSupplierPayment($supplierA->id, 5000.00, 'cash', [
            'notes'   => 'سداد جزء من فاتورة PUR-SEC1-01',
            'paid_by' => $this->cashier->id,
        ]);

        $supplierA->refresh();
        $this->assertEquals(10000.00, (float) $supplierA->current_balance);
        $this->assertEquals(10000.00, (float) $paymentEntry->balance_after);
    }

    /**
     * القطاع 2: بيع نقطة البيع POS مع خصم الكهنة الصارم، الدفع المجزأ، إصدار الضمان وعمولة الفني
     */
    public function test_sector_2_pos_checkout_with_scrap_deduction_split_payment_warranty_and_commission(): void
    {
        $posService = app(PosOrderServiceInterface::class);

        $customer = Customer::create([
            'name'                   => 'محمود فاروق النجار',
            'phone'                  => '01099887766',
            'credit_limit'           => 15000,
            'current_credit_balance' => 0,
            'tier'                   => 'standard',
            'is_active'              => true,
        ]);

        $battery = Product::where('is_battery', true)->first();
        $battery->update(['current_stock' => 20, 'retail_price' => 3200.00]);

        // العميل يشتري بطارية بسعر 3200 ج.م + يسلم بطارية قديمة 70 أمبير (خصم 800 ج.م)
        // الصافي المطلوب سداده = 2400 ج.م
        // الدفع مجزأ: 1400 ج.م كاش + 1000 ج.م فيزا
        $payload = [
            'branch_id'             => $this->branch->id,
            'customer_id'           => $customer->id,
            'technician_id'         => $this->technician->id,
            'items'                 => [
                [
                    'product_id'     => $battery->id,
                    'quantity'       => 1,
                    'unit_price'     => 3200.00,
                    'battery_serial' => 'SN-BAT-70-2026-001',
                ],
            ],
            'has_scrap'             => true,
            'scrap_capacity_ah'     => 70,
            'scrap_count'           => 1,
            'payments'              => [
                ['method' => 'cash', 'amount' => 1400.00],
                ['method' => 'card', 'amount' => 1000.00, 'reference' => 'POS-CARD-9911'],
            ],
        ];

        $invoice = $posService->processPosSale($payload, $this->cashier->id);

        // 1. التحقق من حسابات الفاتورة والدفع المجزأ
        $this->assertEquals(3200.00, (float) $invoice->subtotal);
        $this->assertEquals(800.00, (float) $invoice->scrap_deduction_amount);
        $this->assertEquals(2400.00, (float) $invoice->final_amount);
        $this->assertEquals(2400.00, (float) $invoice->paid_amount);
        $this->assertEquals('split', $invoice->payment_method);
        $this->assertEquals('paid', $invoice->status);

        // 2. التحقق من خصم رصيد المخزن للبطارية المبيعة
        $battery->refresh();
        $this->assertEquals(19, $battery->current_stock);

        // 3. التحقق من إصدار شهادة الضمان الإلكتروني وسريانها
        $warranty = Warranty::where('serial_number', 'SN-BAT-70-2026-001')->first();
        $this->assertNotNull($warranty);
        $this->assertEquals('active', $warranty->status);
        $this->assertEquals($customer->id, $warranty->customer_id);
        $this->assertEquals(now()->toDateString(), $warranty->start_date->toDateString());

        // 4. التحقق من إيداع البطارية الكهنة في مخزن الكهنة
        $scrap = ScrapBatteriesInventory::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($scrap);
        $this->assertEquals('70Ah', $scrap->capacity_ah);
        $this->assertEquals(800.00, (float) $scrap->scrap_value);
        $this->assertEquals('in_stock', $scrap->status);

        // 5. التحقق من إسناد الفني كإجباري وعدم إنشاء عمولة 25 ج.م بعد إلغائها
        $this->assertEquals($this->technician->id, $invoice->technician_id);
        $commission = TechnicianCommission::where('invoice_id', $invoice->id)->first();
        $this->assertNull($commission);
    }

    /**
     * القطاع 3: الرقابة على سقف الائتمان وموافقة المدير Manager Override
     */
    public function test_sector_3_credit_limit_and_manager_override_enforcement(): void
    {
        $posService = app(PosOrderServiceInterface::class);

        // عميل بسقف ائتمان 1,000 ج.م فقط
        $customer = Customer::create([
            'name'                   => 'طارق العبد',
            'phone'                  => '01012345678',
            'credit_limit'           => 1000.00,
            'current_credit_balance' => 0.00,
            'tier'                   => 'standard',
            'is_active'              => true,
        ]);

        $product = Product::first();
        $product->update(['current_stock' => 10, 'retail_price' => 2500.00]);

        // محاولة الشراء بالآجل بمبلغ 2,500 ج.م (تجاوز الحد بمقدار 1,500 ج.م) بدون موافقة المدير
        $invalidPayload = [
            'branch_id'             => $this->branch->id,
            'customer_id'           => $customer->id,
            'technician_id'         => $this->technician->id,
            'items'                 => [
                [
                    'product_id'     => $product->id,
                    'quantity'       => 1,
                    'unit_price'     => 2500.00,
                    'battery_serial' => 'SN-LIMIT-FAIL-01',
                ],
            ],
            'has_scrap'             => false,
            'payments'              => [
                ['method' => 'credit', 'amount' => 2500.00],
            ],
            'manager_override_code' => 'wrong_code',
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('تم تجاوز سقف الائتمان للعميل');
        $posService->processPosSale($invalidPayload, $this->cashier->id);
    }

    /**
     * القطاع 3 (تابع): نجاح البيع بالآجل عند إدخال كود موافقة المدير الصحيح
     */
    public function test_sector_3_credit_limit_success_with_valid_manager_override(): void
    {
        $posService = app(PosOrderServiceInterface::class);

        $customer = Customer::create([
            'name'                   => 'طارق العبد المعتمد',
            'phone'                  => '01087654321',
            'credit_limit'           => 1000.00,
            'current_credit_balance' => 0.00,
            'tier'                   => 'standard',
            'is_active'              => true,
        ]);

        $product = Product::first();
        $product->update(['current_stock' => 10, 'retail_price' => 2500.00]);

        // البيع مع كود موافقة المدير الصحيح mgr_override_99
        $validPayload = [
            'branch_id'             => $this->branch->id,
            'customer_id'           => $customer->id,
            'technician_id'         => $this->technician->id,
            'items'                 => [
                [
                    'product_id'     => $product->id,
                    'quantity'       => 1,
                    'unit_price'     => 2500.00,
                    'battery_serial' => 'SN-LIMIT-OK-01',
                ],
            ],
            'has_scrap'             => false,
            'payments'              => [
                ['method' => 'credit', 'amount' => 2500.00],
            ],
            'manager_override_code' => 'mgr_override_99',
        ];

        $invoice = $posService->processPosSale($validPayload, $this->cashier->id);

        $this->assertEquals('credit', $invoice->payment_method);
        $this->assertEquals('unpaid', $invoice->status);

        $customer->refresh();
        $this->assertEquals(2500.00, (float) $customer->current_credit_balance);
    }

    /**
     * القطاع 4: فحص الضمان والاستبدال الفوري لبطارية معيبة ووضعها في الحجر
     */
    public function test_sector_4_warranty_verification_and_instant_stock_replacement(): void
    {
        $warrantyService = app(WarrantyServiceInterface::class);

        $customer = Customer::create([
            'name'                   => 'ياسر الشناوي',
            'phone'                  => '01077665544',
            'credit_limit'           => 5000,
            'current_credit_balance' => 0,
            'tier'                   => 'standard',
            'is_active'              => true,
        ]);

        $battery = Product::where('is_battery', true)->first();
        $battery->update(['current_stock' => 10]);

        // إنشاء فاتورة وضمان نشط للبطارية المعيبة
        $invoice = Invoice::withoutEvents(function () use ($customer) {
            return Invoice::create([
                'invoice_number' => 'INV-DEFECT-TEST',
                'branch_id'      => $this->branch->id,
                'customer_id'    => $customer->id,
                'cashier_id'     => $this->cashier->id,
                'subtotal'       => 3000,
                'final_amount'   => 3000,
                'paid_amount'    => 3000,
                'payment_method' => 'cash',
                'status' => 'paid',
            ]);
        });

        $item = $invoice->items()->create([
            'product_id'              => $battery->id,
            'quantity'                => 1,
            'unit_price'              => 3000,
            'total_price'             => 3000,
            'battery_serial_number'   => 'DEFECT-BAT-SN-12345',
            'warranty_duration_months'=> 12,
        ]);

        $originalWarranty = Warranty::create([
            'invoice_item_id' => $item->id,
            'customer_id'     => $customer->id,
            'serial_number'   => 'DEFECT-BAT-SN-12345',
            'start_date'      => now()->subMonths(4)->toDateString(),
            'end_date'        => now()->addMonths(8)->toDateString(),
            'status'          => 'active',
        ]);

        // 1. التحقق من سريان الضمان بالسيريال
        $check = $warrantyService->verifyBatterySerial('DEFECT-BAT-SN-12345');
        $this->assertTrue($check['is_valid']);
        $this->assertGreaterThan(0, $check['days_remaining']);

        // 2. تنفيذ قرار الاستبدال الفوري لبطارية جديدة
        $claim = $warrantyService->processInstantClaim([
            'defective_serial'           => 'DEFECT-BAT-SN-12345',
            'technician_id'              => $this->technician->id,
            'battery_voltage_tested'     => 10.1,
            'cca_tested'                 => 130,
            'issue_description'          => 'عطل داخلي بالخلايا - هبوط حاد في الجهد تحت الحمل',
            'decision'                   => 'replaced',
            'replacement_product_id'     => $battery->id,
            'replacement_battery_serial' => 'NEW-REPLACED-SN-9988',
        ], $this->cashier->id);

        $this->assertNotNull($claim);
        $this->assertEquals('replaced', $claim->decision);
        $this->assertStringStartsWith('CLM-', $claim->claim_number);

        // خصم بطارية بديلة من المخزن: من 10 إلى 9
        $battery->refresh();
        $this->assertEquals(9, $battery->current_stock);

        // إنشاء شهادة ضمان جديدة للبديل
        $newWarranty = Warranty::where('serial_number', 'NEW-REPLACED-SN-9988')->first();
        $this->assertNotNull($newWarranty);
        $this->assertEquals('active', $newWarranty->status);

        // إغلاق الضمان الأصلي
        $originalWarranty->refresh();
        $this->assertEquals('claimed', $originalWarranty->status);
    }

    /**
     * القطاع 5: تسوية مطالبة الضمان مع الشركة الموردة بإشعار دائن Credit Note
     */
    public function test_sector_5_supplier_warranty_claim_settlement(): void
    {
        $warrantyService = app(WarrantyServiceInterface::class);

        $supplier = Supplier::create([
            'name'            => 'شركة النيل للبطاريات',
            'company_name'    => 'توكيل النيل',
            'phone'           => '01005544332',
            'credit_limit'    => 100000,
            'current_balance' => 8000.00, // مديونية المركز للمورد 8,000 ج.م
        ]);

        $product = Product::first();

        // إنشاء عميل وفاتورة وضمان صالح للمطالبة
        $customer = Customer::create([
            'name'                   => 'عميل مطالبة المورد',
            'phone'                  => '01000112233',
            'credit_limit'           => 5000,
            'current_credit_balance' => 0,
            'tier'                   => 'standard',
            'is_active'              => true,
        ]);

        $invoice = Invoice::withoutEvents(function () use ($customer) {
            return Invoice::create([
                'invoice_number' => 'INV-SUP-CLAIM-01',
                'branch_id'      => $this->branch->id,
                'customer_id'    => $customer->id,
                'cashier_id'     => $this->cashier->id,
                'subtotal'       => 3000,
                'final_amount'   => 3000,
                'paid_amount'    => 3000,
            ]);
        });

        $item = $invoice->items()->create([
            'product_id'               => $product->id,
            'quantity'                 => 1,
            'unit_price'               => 3000,
            'total_price'              => 3000,
            'battery_serial_number'    => 'DEFECT-FOR-SUPPLIER-01',
            'warranty_duration_months' => 12,
        ]);

        $warranty = Warranty::create([
            'invoice_item_id' => $item->id,
            'customer_id'     => $customer->id,
            'serial_number'   => 'DEFECT-FOR-SUPPLIER-01',
            'start_date'      => now()->subMonths(2)->toDateString(),
            'end_date'        => now()->addMonths(10)->toDateString(),
            'status'          => 'claimed',
        ]);

        // مطالبة مستبدلة في انتظار التسوية مع المورد
        $claim = WarrantyClaim::create([
            'claim_number'           => 'CLM-SETTLE-TEST-01',
            'claim_date'             => now()->toDateString(),
            'warranty_id'            => $warranty->id,
            'product_id'             => $product->id,
            'branch_id'              => $this->branch->id,
            'technician_id'          => $this->technician->id,
            'customer_id'            => $customer->id,
            'supplier_id'            => $supplier->id,
            'defective_serial'       => 'DEFECT-FOR-SUPPLIER-01',
            'issue_description'      => 'عطل خلايا داخلي وضعف شحن',
            'decision'               => 'replaced',
            'status'                 => 'approved',
            'supplier_resolution'    => 'pending',
            'battery_voltage_tested' => 10.2,
            'cca_tested'             => 140,
        ]);

        // تسوية المطالبة عبر إشعار دائن (Credit Note) بقيمة 1,800 ج.م
        $settledClaim = $warrantyService->settleClaimWithSupplier(
            $claim->id,
            'settled_credit_note',
            ['credit_amount' => 1800.00],
            $this->cashier->id
        );

        $this->assertEquals('settled_credit_note', $settledClaim->supplier_resolution);
        $this->assertNotNull($settledClaim->resolved_at);

        // التحقق من خصم 1,800 ج.م من مديونية المورد (8,000 - 1,800 = 6,200 ج.م)
        $supplier->refresh();
        $this->assertEquals(6200.00, (float) $supplier->current_balance);

        // التحقق من قيد دفتر الأستاذ للمورد
        $ledgerEntry = SupplierLedgerEntry::where('supplier_id', $supplier->id)
            ->where('entry_type', 'adjustment')
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertEquals(1800.00, (float) $ledgerEntry->amount);
        $this->assertEquals(6200.00, (float) $ledgerEntry->balance_after);
    }

    /**
     * القطاع 6: إحصائيات مخزن الكهنة وتجارة الرصاص وبيع شحنة مجمعة لمصنع التدوير
     */
    public function test_sector_6_scrap_yard_accumulation_and_factory_sale_batch(): void
    {
        $scrapService = app(ScrapBatteryServiceInterface::class);

        // إيداع 3 بطاريات كهنة بسعات مختلفة في مخزن الكهنة
        $scrap1 = ScrapBatteriesInventory::create([
            'branch_id'      => $this->branch->id,
            'capacity_ah'    => '70Ah',
            'scrap_value'    => 800.00,
            'lead_weight_kg' => 11.9,
            'status'         => 'in_stock',
            'received_by'    => $this->technician->id,
        ]);

        $scrap2 = ScrapBatteriesInventory::create([
            'branch_id'      => $this->branch->id,
            'capacity_ah'    => '70Ah',
            'scrap_value'    => 800.00,
            'lead_weight_kg' => 11.9,
            'status'         => 'in_stock',
            'received_by'    => $this->technician->id,
        ]);

        $scrap3 = ScrapBatteriesInventory::create([
            'branch_id'      => $this->branch->id,
            'capacity_ah'    => '100Ah',
            'scrap_value'    => 1200.00,
            'lead_weight_kg' => 17.0,
            'status'         => 'in_stock',
            'received_by'    => $this->technician->id,
        ]);

        // 1. فحص إحصائيات المخزن
        $metrics = $scrapService->getInventoryMetrics($this->branch->id);
        $this->assertEquals(3, $metrics['total_units']);
        $this->assertEquals(2800.00, (float) $metrics['total_scrap_value']);
        $this->assertEquals(40.8, (float) $metrics['total_lead_weight_kg']);
        $this->assertGreaterThan(0, (float) $metrics['total_metric_tons']);

        // 2. بيع بطاريتين 70Ah لمصنع التدوير (التكلفة = 1600، البيع = 2000 -> ربح 400 ج.م)
        $batch = $scrapService->dispatchScrapSaleBatch([
            'scrap_battery_ids' => [$scrap1->id, $scrap2->id],
            'buyer_name'        => 'مصنع الشرق لتدوير الرصاص',
            'total_amount'      => 2000.00,
        ], $this->cashier->id);

        $this->assertStringStartsWith('SCRAP-BATCH-', $batch['batch_number']);
        $this->assertEquals(2, $batch['batteries_count']);
        $this->assertEquals(400.00, (float) $batch['gross_profit']);

        $scrap1->refresh();
        $scrap2->refresh();
        $scrap3->refresh();

        $this->assertEquals('sold_to_factory', $scrap1->status);
        $this->assertEquals('sold_to_factory', $scrap2->status);
        $this->assertEquals('in_stock', $scrap3->status);
    }

    /**
     * القطاع 7: قفل الصفوف المتزامن ومنع بيع كمية غير متوفرة في المخزن
     */
    public function test_sector_7_pessimistic_locking_prevents_overselling_below_zero(): void
    {
        $posService = app(PosOrderServiceInterface::class);

        $product = Product::first();
        $product->update(['current_stock' => 1, 'retail_price' => 2000.00]);

        $customer = Customer::create([
            'name'                   => 'عميل فحص التزامن',
            'phone'                  => '01066554433',
            'credit_limit'           => 10000,
            'current_credit_balance' => 0,
            'tier'                   => 'standard',
            'is_active'              => true,
        ]);

        // محاولة بيع 2 وحدات بينما المتوفر 1 فقط
        $payload = [
            'branch_id'             => $this->branch->id,
            'customer_id'           => $customer->id,
            'items'                 => [
                [
                    'product_id'     => $product->id,
                    'quantity'       => 2,
                    'unit_price'     => 2000.00,
                    'battery_serial' => 'SN-RACE-01',
                ],
            ],
            'has_scrap'             => false,
            'payments'              => [
                ['method' => 'cash', 'amount' => 4000.00],
            ],
        ];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ولا يكفي لصرف');

        $posService->processPosSale($payload, $this->cashier->id);
    }
}
