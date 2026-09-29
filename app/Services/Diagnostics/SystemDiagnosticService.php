<?php

namespace App\Services\Diagnostics;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CreditLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\JobTitle;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalaryStructure;
use App\Models\ScrapBatteriesInventory;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\TechnicianCommission;
use App\Models\User;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\PayrollService;
use App\Services\Purchases\PurchaseService;
use App\Services\Sales\PosOrderService;
use App\Services\Sales\ScrapBatteryService;
use App\Services\Sales\WarrantyService;
use App\Services\SearchService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemDiagnosticService
{
    /**
     * تنفيذ فحص تدقيق سلامة قاعدة البيانات والاتساق المحاسبي والمخزني
     */
    public function runFullAudit(): array
    {
        $startTime = microtime(true);
        $issues = [];
        $checks = [];

        // 1. فحص المخزون السالب
        $negativeStockCount = Product::where('current_stock', '<', 0)->count();
        $checks[] = [
            'key'         => 'negative_stock',
            'name'        => 'فحص منع المخزون السالب (Negative Stock)',
            'status'      => $negativeStockCount === 0 ? 'passed' : 'failed',
            'severity'    => 'critical',
            'details'     => $negativeStockCount === 0 
                ? 'جميع الأصناف رصيدها سليم (صفر أو موجب).' 
                : "تم العثور على ({$negativeStockCount}) منتج برصيد سالب تحت الصفر!",
            'count'       => $negativeStockCount,
        ];
        if ($negativeStockCount > 0) {
            $issues[] = "المخزون: يوجد {$negativeStockCount} منتج برصيد سالب.";
        }

        // 2. فحص اتساق كشوف حساب العملاء (Customer Ledger vs Current Balance)
        $customerMismatchCount = 0;
        $customers = Customer::where('current_credit_balance', '>', 0)->get();
        foreach ($customers as $customer) {
            $ledgerDebt = CreditLedgerEntry::where('customer_id', $customer->id)
                ->where('entry_type', 'invoice_debt')
                ->sum('amount');
            $ledgerPaid = CreditLedgerEntry::where('customer_id', $customer->id)
                ->where('entry_type', 'payment_settlement')
                ->sum('amount');
            $calculatedBalance = round($ledgerDebt - $ledgerPaid, 2);
            $actualBalance = round((float) $customer->current_credit_balance, 2);

            // السماح بفرق طفيف إذا وُجد رصيد افتتاحي بدون قيد
            if (abs($calculatedBalance - $actualBalance) > 1.0) {
                $customerMismatchCount++;
            }
        }
        $checks[] = [
            'key'         => 'customer_credit_ledger',
            'name'        => 'اتساق دفتر أستاذ مديونيات العملاء (Customer Ledger)',
            'status'      => $customerMismatchCount === 0 ? 'passed' : 'warning',
            'severity'    => 'medium',
            'details'     => $customerMismatchCount === 0
                ? 'أرصدة مديونيات العملاء مطابقة لقيود دفتر الأستاذ.'
                : "يوجد ({$customerMismatchCount}) عميل تختلف مديونيتهم الفعلية عن قيود السداد والديون.",
            'count'       => $customerMismatchCount,
        ];

        // 3. فحص اتساق كشوف حساب الموردين (Supplier Ledger vs Current Balance)
        $supplierMismatchCount = 0;
        $suppliers = Supplier::where('current_balance', '>', 0)->get();
        foreach ($suppliers as $supplier) {
            $ledgerDebt = SupplierLedgerEntry::where('supplier_id', $supplier->id)
                ->where('entry_type', 'purchase_invoice')
                ->sum('amount');
            $ledgerPaid = SupplierLedgerEntry::where('supplier_id', $supplier->id)
                ->where('entry_type', 'payment')
                ->sum('amount');
            $calculatedBalance = round($ledgerDebt - $ledgerPaid, 2);
            $actualBalance = round((float) $supplier->current_balance, 2);

            if (abs($calculatedBalance - $actualBalance) > 1.0) {
                $supplierMismatchCount++;
            }
        }
        $checks[] = [
            'key'         => 'supplier_ledger',
            'name'        => 'اتساق دفتر أستاذ الموردين (Supplier Ledger)',
            'status'      => $supplierMismatchCount === 0 ? 'passed' : 'warning',
            'severity'    => 'medium',
            'details'     => $supplierMismatchCount === 0
                ? 'أرصدة التزامات الموردين مطابقة لقيود التوريد وسندات الصرف.'
                : "يوجد ({$supplierMismatchCount}) مورد يختلف رصيده الدائن عن القيود.",
            'count'       => $supplierMismatchCount,
        ];

        // 4. فحص تكرار سيريالات البطاريات النشطة (Duplicate Active Warranties)
        $duplicateSerials = Warranty::where('status', 'active')
            ->select('serial_number', DB::raw('count(*) as count'))
            ->groupBy('serial_number')
            ->having('count', '>', 1)
            ->count();
        $checks[] = [
            'key'         => 'duplicate_warranty_serials',
            'name'        => 'منع تكرار سيريالات الضمان النشطة (Active Serials)',
            'status'      => $duplicateSerials === 0 ? 'passed' : 'failed',
            'severity'    => 'critical',
            'details'     => $duplicateSerials === 0
                ? 'لا توجد سيريالات بطاريات نشطة مكررة في منظومة الضمان.'
                : "يوجد ({$duplicateSerials}) سيريال مكرر ومسجل كضمان ساري لأكثر من بطارية!",
            'count'       => $duplicateSerials,
        ];
        if ($duplicateSerials > 0) {
            $issues[] = "الضمانات: يوجد {$duplicateSerials} سيريال نشط مكرر.";
        }

        // 5. فحص سلامة مسيرات الرواتب (Payroll Net Sum Verification)
        $payrollMismatch = 0;
        $payrolls = Payroll::with('items')->get();
        foreach ($payrolls as $payroll) {
            $itemsNetSum = round((float) $payroll->items->sum('net_salary'), 2);
            $payrollTotalNet = round((float) $payroll->total_net, 2);
            if (abs($itemsNetSum - $payrollTotalNet) > 0.05) {
                $payrollMismatch++;
            }
        }
        $checks[] = [
            'key'         => 'payroll_math_integrity',
            'name'        => 'توازن مسيرات الرواتب ومطابقة الصافي (Payroll Net Sum)',
            'status'      => $payrollMismatch === 0 ? 'passed' : 'failed',
            'severity'    => 'high',
            'details'     => $payrollMismatch === 0
                ? 'جميع مسيرات الرواتب متوازنة حسابياً ومطابقة لمجموع بنود الموظفين.'
                : "تم العثور على ({$payrollMismatch}) مسير راتب لا يتطابق إجماليه مع بنود الموظفين!",
            'count'       => $payrollMismatch,
        ];
        if ($payrollMismatch > 0) {
            $issues[] = "الرواتب: يوجد {$payrollMismatch} مسير راتب غير متوازن حسابياً.";
        }

        // 6. فحص سجلات الحضور الشاذة (Attendance Inconsistencies)
        $attendanceAnomalies = Attendance::whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->whereRaw('check_out <= check_in')
            ->count();
        $checks[] = [
            'key'         => 'attendance_anomalies',
            'name'        => 'التسلسل الزمني لبصمات الحضور والانصراف',
            'status'      => $attendanceAnomalies === 0 ? 'passed' : 'failed',
            'severity'    => 'high',
            'details'     => $attendanceAnomalies === 0
                ? 'جميع البصمات منضبطة زمنياً (وقت الانصراف لاحق لوقت الحضور).'
                : "يوجد ({$attendanceAnomalies}) سجل حضور فيه وقت الانصراف يسبق وقت الحضور!",
            'count'       => $attendanceAnomalies,
        ];

        // 7. فحص بطاريات الكهنة الشاذة (Scrap Inventory Anomalies)
        $orphanScrap = ScrapBatteriesInventory::where('status', 'in_stock')
            ->whereNull('received_by')
            ->count();
        $checks[] = [
            'key'         => 'orphan_scrap_batteries',
            'name'        => 'إسناد بطاريات الكهنة المستلمة لمسؤول (Scrap Chain of Custody)',
            'status'      => $orphanScrap === 0 ? 'passed' : 'warning',
            'severity'    => 'low',
            'details'     => $orphanScrap === 0
                ? 'جميع بطاريات الكهنة المخزنة مسندة لفني أو مستلم رسمي.'
                : "يوجد ({$orphanScrap}) بطارية كهنة بدون تسجيل الفني المستلم.",
            'count'       => $orphanScrap,
        ];

        // 8. فحص الفواتير المفقود دفعاتها (Invoices Missing Payment Entries)
        $invoicesWithoutPayments = Invoice::where('paid_amount', '>', 0)
            ->whereDoesntHave('payments')
            ->count();
        $checks[] = [
            'key'         => 'invoices_payments_breakdown',
            'name'        => 'توثيق مدفوعات الفواتير (Split Payment Records)',
            'status'      => $invoicesWithoutPayments === 0 ? 'passed' : 'warning',
            'severity'    => 'medium',
            'details'     => $invoicesWithoutPayments === 0
                ? 'جميع المبالغ المسددة بالفواتير موثقة بسجلات دفع تفصيلية (كاش/شبكة).'
                : "يوجد ({$invoicesWithoutPayments}) فاتورة تم سدادها بدون تجزئة دفع موثقة.",
            'count'       => $invoicesWithoutPayments,
        ];

        $passedCount = count(array_filter($checks, fn($c) => $c['status'] === 'passed'));
        $totalChecks = count($checks);
        $healthScore = $totalChecks > 0 ? round(($passedCount / $totalChecks) * 100, 1) : 100;
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'health_score' => $healthScore,
            'passed_count' => $passedCount,
            'total_checks' => $totalChecks,
            'duration_ms'  => $durationMs,
            'checks'       => $checks,
            'issues'       => $issues,
            'audited_at'   => now()->toDateTimeString(),
        ];
    }

    /**
     * تشغيل محاكاة دورة الأعمال الشاملة لكافة قطاعات النظام (End-to-End Simulation)
     */
    public function runLiveSimulation(bool $rollback = true): array
    {
        $startTime = microtime(true);
        $steps = [];
        $allPassed = true;

        $run = function () use (&$steps, &$allPassed, $rollback) {
            $user = User::first() ?? User::create([
                'name' => 'مختبر النظام الآلي',
                'email' => 'sim_' . uniqid() . '@alhusseini.com',
                'password' => bcrypt('password'),
            ]);
            $branch = Branch::first() ?? Branch::create([
                'name' => 'فرع الاختبار الحي',
                'code' => 'TEST',
                'address' => 'دمياط الجديدة',
                'is_active' => true,
            ]);

            $category = \App\Models\Category::firstOrCreate(
                ['slug' => 'batteries'],
                ['name' => 'بطاريات السيارات']
            );

            $department = Department::firstOrCreate(
                ['code' => 'WORKSHOP'],
                ['name' => 'ورشة الصيانة']
            );

            $jobTitle = JobTitle::firstOrCreate(
                ['department_id' => $department->id, 'title' => 'فني صيانة وبطاريات'],
                ['min_salary' => 3000, 'max_salary' => 15000]
            );

            $product = null;
            $customer = null;
            $technician = null;
            $invoice = null;
            $batterySerial = 'SIM-SN-' . strtoupper(substr(uniqid(), -6));

            // ─── القطاع 1: المشتريات والتسعير بالمتوسط المرجح (WAC) ─────────────────
            $s1Start = microtime(true);
            try {
                $supplier = Supplier::create([
                    'name' => 'شركة النسر للتوريدات - محاكاة',
                    'company_name' => 'النسر إلكترونيك',
                    'phone' => '010' . rand(10000000, 99999999),
                    'current_balance' => 0,
                    'is_active' => true,
                ]);

                $product = Product::create([
                    'category_id'     => $category->id,
                    'sku'             => 'SIM-SKU-' . strtoupper(substr(uniqid(), -6)),
                    'name'            => 'بطارية كلورايد 70 أمبير - محاكاة',
                    'brand'           => 'Chloride',
                    'capacity_ah'     => 70,
                    'is_battery'      => true,
                    'warranty_months' => 12,
                    'current_stock'   => 10,
                    'cost_price'      => 1000.00,
                    'retail_price'    => 3500.00,
                    'is_active'       => true,
                ]);

                // توريد 10 قطع إضافية بسعر 1200 ج.م
                // WAC المتوقع: ((10 * 1000) + (10 * 1200)) / 20 = 1100.00 ج.م
                $purchaseService = app(PurchaseService::class);
                $purchaseInvoice = $purchaseService->createDirectPurchase([
                    'supplier_id'    => $supplier->id,
                    'branch_id'      => $branch->id,
                    'invoice_number' => 'PO-SIM-' . uniqid(),
                    'invoice_date'   => now()->toDateString(),
                    'paid_amount'    => 4000.00,
                    'items'          => [
                        [
                            'product_id'      => $product->id,
                            'quantity'        => 10,
                            'unit_cost_price' => 1200.00,
                        ],
                    ],
                ], $user->id);

                $product->refresh();
                $expectedWac = 1100.00;
                $expectedStock = 20;

                $wacPassed = (abs((float)$product->cost_price - $expectedWac) < 0.05) && ($product->current_stock == $expectedStock);

                $steps[] = [
                    'sector'   => 'المشتريات والتسعير بالمتوسط المرجح (WAC)',
                    'name'     => 'توريد شحنة جديدة وتحديث تكلفة الصنف بمعادلة المتوسط المرجح',
                    'status'   => $wacPassed ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s1Start) * 1000, 2),
                    'details'  => "التكلفة المتوقعة: {$expectedWac} ج.م | المحسوبة: {$product->cost_price} ج.م | الرصيد: {$product->current_stock} قطعة",
                ];
                if (!$wacPassed) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'المشتريات والتسعير بالمتوسط المرجح (WAC)',
                    'name'     => 'توريد شحنة جديدة وتحديث تكلفة الصنف بمعادلة المتوسط المرجح',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s1Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            // ─── القطاع 2: نقطة البيع (POS) مع خصم الكهنة وتجزئة الدفع وإصدار الضمان ──
            $s2Start = microtime(true);

            try {
                $customer = Customer::create([
                    'name'                   => 'أحمد التميمي - عميل محاكاة',
                    'phone'                  => '011' . rand(10000000, 99999999),
                    'credit_limit'           => 3000.00,
                    'current_credit_balance' => 0.00,
                    'is_active'              => true,
                ]);

                $vehicle = CustomerVehicle::create([
                    'customer_id'  => $customer->id,
                    'car_brand'    => 'Toyota',
                    'car_model'    => 'Corolla',
                    'plate_number' => 'ط س د 9988',
                ]);

                $technician = Employee::create([
                    'branch_id'            => $branch->id,
                    'job_title_id'         => $jobTitle->id,
                    'employee_code'        => 'EMP-TECH-' . rand(1000, 9999),
                    'full_name'            => 'محمود الفني - محاكاة',
                    'phone'                => '012' . rand(10000000, 99999999),
                    'national_id'          => '295' . rand(10000000000, 99999999999),
                    'hire_date'            => now()->toDateString(),
                    'shift_start_time'     => '09:00:00',
                    'shift_end_time'       => '17:00:00',
                    'grace_period_minutes' => 15,
                    'status'               => 'active',
                ]);

                // عملية بيع بـ 3500 ج.م مع خصم كهنة 700 ج.م
                // الصافي المطلوب = 2800 ج.م
                // السداد المجزأ: 1800 كاش + 1000 آجل
                $posService = app(PosOrderService::class);
                $invoice = $posService->processPosSale([
                    'branch_id'              => $branch->id,
                    'customer_id'            => $customer->id,
                    'customer_vehicle_id'    => $vehicle->id,
                    'technician_id'          => $technician->id,
                    'has_scrap'              => true,
                    'scrap_capacity_ah'      => 70,
                    'scrap_count'            => 1,
                    'scrap_deduction_amount' => 700.00,
                    'items'                  => [
                        [
                            'product_id'     => $product->id,
                            'quantity'       => 1,
                            'unit_price'     => 3500.00,
                            'battery_serial' => $batterySerial,
                        ],
                    ],
                    'payments'               => [
                        ['method' => 'cash', 'amount' => 1800.00],
                        ['method' => 'credit', 'amount' => 1000.00],
                    ],
                ], $user->id);

                $product->refresh();
                $customer->refresh();

                $warranty = Warranty::where('serial_number', $batterySerial)->first();
                $scrapBat = ScrapBatteriesInventory::where('invoice_id', $invoice->id)->first();

                $posValid = ($invoice->final_amount == 2800.00)
                    && ($customer->current_credit_balance == 1000.00)
                    && ($warranty !== null && $warranty->status === 'active')
                    && ($scrapBat !== null && $scrapBat->capacity_ah === '70Ah')
                    && ($invoice->technician_id == $technician->id)
                    && ($product->current_stock == 19);

                $steps[] = [
                    'sector'   => 'نقطة البيع ومبيعات الكاشير (POS)',
                    'name'     => 'إصدار فاتورة بيع مركبة (خصم كهنة + تجزئة دفع + إصدار ضمان + إسناد الفني إجباري)',
                    'status'   => $posValid ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s2Start) * 1000, 2),
                    'details'  => "الفاتورة: {$invoice->invoice_number} | الصافي: {$invoice->final_amount} ج.م | مدفوع: 1800 | آجل: 1000 | الضمان: {$batterySerial} | الفني القائم بالتركيب: {$technician->full_name}",
                ];
                if (!$posValid) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'نقطة البيع ومبيعات الكاشير (POS)',
                    'name'     => 'إصدار فاتورة بيع مركبة (خصم كهنة + تجزئة دفع + إصدار ضمان + إسناد الفني إجباري)',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s2Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            // ─── القطاع 3: فحص سقف الائتمان وتجاوزه بكود تفويض المدير ────────────────
            $s3Start = microtime(true);
            try {
                $posService = app(PosOrderService::class);
                // محاولة بيع بـ 2500 آجل (الرصيد سيصبح 1000 + 2500 = 3500 > السقف 3000)
                $rejectedWithoutCode = false;
                try {
                    $posService->processPosSale([
                        'branch_id'     => $branch->id,
                        'customer_id'   => $customer->id,
                        'technician_id' => $technician->id,
                        'items'         => [
                            ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2500.00],
                        ],
                        'payments'      => [
                            ['method' => 'credit', 'amount' => 2500.00],
                        ],
                    ], $user->id);
                } catch (\DomainException $de) {
                    $rejectedWithoutCode = true;
                }

                // المحاولة مع كود المدير المعتمد
                $invoiceOverridden = $posService->processPosSale([
                    'branch_id'             => $branch->id,
                    'customer_id'           => $customer->id,
                    'technician_id'         => $technician->id,
                    'manager_override_code' => 'mgr_override_99',
                    'items'                 => [
                        ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2500.00],
                    ],
                    'payments'              => [
                        ['method' => 'credit', 'amount' => 2500.00],
                    ],
                ], $user->id);

                $creditLimitPassed = $rejectedWithoutCode && ($invoiceOverridden !== null);
                $steps[] = [
                    'sector'   => 'إدارة الآجل والائتمان (Credit Control)',
                    'name'     => 'حظر تجاوز سقف الائتمان تلقائياً واعتماده بكود تفويض المدير',
                    'status'   => $creditLimitPassed ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s3Start) * 1000, 2),
                    'details'  => $creditLimitPassed 
                        ? 'تم حظر البيع بدون كود ونجح اعتماده فورياً بكود التفويض.' 
                        : 'فشل اختبار قيود سقف الائتمان.',
                ];
                if (!$creditLimitPassed) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'إدارة الآجل والائتمان (Credit Control)',
                    'name'     => 'حظر تجاوز سقف الائتمان تلقائياً واعتماده بكود تفويض المدير',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s3Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            // ─── القطاع 4: فحص الضمان والاستبدال الفوري من المخزن ───────────────────
            $s4Start = microtime(true);
            try {
                $warrantyService = app(WarrantyService::class);
                $verifyRes = $warrantyService->verifyBatterySerial($batterySerial);
                $replacementSerial = 'SIM-REP-' . strtoupper(substr(uniqid(), -6));

                // استبدال فوري
                $claim = $warrantyService->processInstantClaim([
                    'defective_serial'           => $batterySerial,
                    'branch_id'                  => $branch->id,
                    'decision'                   => 'replaced',
                    'replacement_product_id'     => $product->id,
                    'replacement_battery_serial' => $replacementSerial,
                    'technician_id'              => $technician->id,
                    'battery_voltage_tested'     => 10.2,
                    'issue_description'          => 'هبوط داخلي في الخلايا - محاكاة',
                ], $user->id);

                $oldWarranty = Warranty::where('serial_number', $batterySerial)->first();
                $newWarranty = Warranty::where('serial_number', $replacementSerial)->first();

                $warrantyPassed = ($verifyRes['is_valid'] === true)
                    && ($claim !== null)
                    && ($oldWarranty->status === 'claimed')
                    && ($newWarranty !== null && $newWarranty->status === 'active');

                $steps[] = [
                    'sector'   => 'الضمانات وخدمات ما بعد البيع (Warranties)',
                    'name'     => 'فحص سريان الضمان والاستبدال الفوري ببطارية بديلة من المخزن',
                    'status'   => $warrantyPassed ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s4Start) * 1000, 2),
                    'details'  => "تذكرة المطالبة: {$claim->claim_number} | القديمة: claimed | البديلة: {$replacementSerial}",
                ];
                if (!$warrantyPassed) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'الضمانات وخدمات ما بعد البيع (Warranties)',
                    'name'     => 'فحص سريان الضمان والاستبدال الفوري ببطارية بديلة من المخزن',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s4Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            // ─── القطاع 5: مخزن الكهنة وبيع لوط لمصنع التدوير ─────────────────────────
            $s5Start = microtime(true);
            try {
                $scrapService = app(ScrapBatteryService::class);
                $scrapBatItem = ScrapBatteriesInventory::where('invoice_id', $invoice->id)->first();

                $saleBatch = $scrapService->dispatchScrapSaleBatch([
                    'scrap_battery_ids' => [$scrapBatItem->id],
                    'buyer_name'        => 'مصنع صهر وتدوير الرصاص - محاكاة',
                    'total_amount'      => 850.00,
                ], $user->id);

                $scrapBatItem->refresh();
                $scrapPassed = ($scrapBatItem->status === 'sold_to_factory') && !empty($saleBatch['batch_number']);

                $steps[] = [
                    'sector'   => 'مخزن الكهنة وإعادة تدوير الرصاص (Scrap Yard)',
                    'name'     => 'تصدير دفعة كهنة مستبدلة وبيعها بالجملة لمصنع التدوير',
                    'status'   => $scrapPassed ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s5Start) * 1000, 2),
                    'details'  => "رقم تشغيلة البيع: {$saleBatch['batch_number']} | الربح: {$saleBatch['gross_profit']} ج.م",
                ];
                if (!$scrapPassed) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'مخزن الكهنة وإعادة تدوير الرصاص (Scrap Yard)',
                    'name'     => 'تصدير دفعة كهنة مستبدلة وبيعها بالجملة لمصنع التدوير',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s5Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            // ─── القطاع 6: الحضور والتأخير وفترة التبريد (Attendance Engine) ──────────
            $s6Start = microtime(true);
            try {
                SalaryStructure::create([
                    'employee_id'         => $technician->id,
                    'basic_salary'        => 6000.00,
                    'housing_allowance'   => 500.00,
                    'transport_allowance' => 500.00,
                    'other_allowances'    => 0.00,
                    'effective_from'      => now()->startOfMonth()->toDateString(),
                ]);

                $attService = app(AttendanceService::class);
                $workDate = now()->toDateString();

                // تسجيل بصمة الساعة 09:45 (تأخير 45 دقيقة > سماح 15 دقيقة)
                $firstPunch = $attService->recordPunch(
                    $technician->id,
                    Carbon::parse("{$workDate} 09:45:00"),
                    'check_in'
                );

                // محاولة بصمة ثانية بعد دقيقة (يجب تفعيل التبريد وتجاهلها)
                $secondPunch = $attService->recordPunch(
                    $technician->id,
                    Carbon::parse("{$workDate} 09:46:00"),
                    'check_in'
                );

                // فحص الجزاء الآلي (تأخير 45 دقيقة >= 30 دقيقة ⬅️ جزاء ربع يوم = 50 ج.م)
                $deduction = EmployeeDeduction::where('attendance_id', $firstPunch->id)->first();
                if ($deduction) {
                    $deduction->update(['status' => 'approved', 'approved_by' => $user->id]);
                }

                $debounceWorking = ($firstPunch->id === $secondPunch->id);
                $lateCalculated = ($firstPunch->late_minutes === 45) && ($firstPunch->status === 'late');
                $deductionCreated = ($deduction !== null && $deduction->amount == 50.00);

                $attPassed = $debounceWorking && $lateCalculated && $deductionCreated;

                $steps[] = [
                    'sector'   => 'الموارد البشرية والبصمة (Attendance Engine)',
                    'name'     => 'فحص فترة التبريد (Debounce 5min) واحتساب التأخير وتوليد الجزاء التلقائي',
                    'status'   => $attPassed ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s6Start) * 1000, 2),
                    'details'  => "دقائق التأخير: {$firstPunch->late_minutes} | التبريد: سليم | قيمة الجزاء الآلي: 50.00 ج.م",
                ];
                if (!$attPassed) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'الموارد البشرية والبصمة (Attendance Engine)',
                    'name'     => 'فحص فترة التبريد (Debounce 5min) واحتساب التأخير وتوليد الجزاء التلقائي',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s6Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            // ─── القطاع 7: محرك الرواتب ودورة الحياة الصارمة (Payroll Engine) ──────────
            $s7Start = microtime(true);
            try {
                // اعتماد عمولة الفني لتظهر في مسير الراتب
                TechnicianCommission::where('employee_id', $technician->id)->update(['status' => 'approved']);

                // تسجيل 25 يوم حضور إضافي للموظف لاكتمال 26 يوم عمل شهري لتفادي خصم الغياب غير المبرر
                $todayStr = now()->toDateString();
                for ($d = 1; $d <= 25; $d++) {
                    $dayDate = now()->startOfMonth()->addDays($d - 1)->toDateString();
                    if ($dayDate !== $todayStr) {
                        Attendance::firstOrCreate(
                            ['employee_id' => $technician->id, 'work_date' => $dayDate],
                            ['status' => 'present', 'check_in' => "{$dayDate} 09:00:00", 'check_out' => "{$dayDate} 17:00:00"]
                        );
                    }
                }

                $payrollService = app(PayrollService::class);
                $payroll = $payrollService->generateMonthlyPayroll($branch->id, now()->year, now()->month);

                $techItem = PayrollItem::where('payroll_id', $payroll->id)
                    ->where('employee_id', $technician->id)
                    ->first();

                // المعادلة: أساسي (6000) + بدلات (1000) - جزاءات (50) = 6950.00 ج.م (بدون عمولة بعد إلغائها)
                $expectedNet = 6950.00;
                $payrollApproved = $payrollService->approvePayroll($payroll, $user->id);
                $payrollDisbursed = $payrollService->disbursePayroll($payroll);
                $payroll->refresh();

                // اختبار قفل المسير بعد الصرف
                $disburseLockWorking = false;
                try {
                    $payrollService->generateMonthlyPayroll($branch->id, now()->year, now()->month);
                } catch (\Exception $ex) {
                    $disburseLockWorking = true;
                }

                $payrollPassed = ($techItem !== null)
                    && (abs((float)$techItem->net_salary - $expectedNet) < 0.1)
                    && ($payroll->status === 'disbursed')
                    && $disburseLockWorking;

                $steps[] = [
                    'sector'   => 'مسير الرواتب والأجور (Payroll Lifecycle)',
                    'name'     => 'توليد المسير ومطابقة معادلة الصافي الدقيقة وقفل المسير بعد الصرف',
                    'status'   => $payrollPassed ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s7Start) * 1000, 2),
                    'details'  => "الصافي المتوقع: {$expectedNet} ج.م | المحسوب: {$techItem?->net_salary} ج.م | قفل الصرف: محكم",
                ];
                if (!$payrollPassed) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'مسير الرواتب والأجور (Payroll Lifecycle)',
                    'name'     => 'توليد المسير ومطابقة معادلة الصافي الدقيقة وقفل المسير بعد الصرف',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s7Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            // ─── القطاع 8: محرك البحث الشامل والمطابقة العربية ───────────────────────
            $s8Start = microtime(true);
            try {
                $searchService = app(SearchService::class);
                // فحص البحث بدون همزة "محمود" أو "احمد"
                $searchRes = $searchService->search('محمود', 5);
                $searchPassed = ($searchRes['total_count'] > 0);

                $steps[] = [
                    'sector'   => 'البحث الشامل والمطابقة الهجائية (Spotlight Search)',
                    'name'     => 'البحث اللحظي ومطابقة الحروف والهمزات العربية عبر الجداول',
                    'status'   => $searchPassed ? 'passed' : 'failed',
                    'duration' => round((microtime(true) - $s8Start) * 1000, 2),
                    'details'  => "نتائج البحث عن 'محمود': {$searchRes['total_count']} نتيجة عبر القطاعات",
                ];
                if (!$searchPassed) $allPassed = false;
            } catch (Throwable $e) {
                $steps[] = [
                    'sector'   => 'البحث الشامل والمطابقة الهجائية (Spotlight Search)',
                    'name'     => 'البحث اللحظي ومطابقة الحروف والهمزات العربية عبر الجداول',
                    'status'   => 'failed',
                    'duration' => round((microtime(true) - $s8Start) * 1000, 2),
                    'details'  => 'استثناء: ' . $e->getMessage(),
                ];
                $allPassed = false;
            }

            if ($rollback) {
                throw new \RuntimeException('__SIMULATION_ROLLBACK__');
            }
        };

        if ($rollback) {
            try {
                DB::transaction($run);
            } catch (\RuntimeException $re) {
                if ($re->getMessage() !== '__SIMULATION_ROLLBACK__') {
                    throw $re;
                }
            }
        } else {
            DB::transaction($run);
        }

        $totalDurationMs = round((microtime(true) - $startTime) * 1000, 2);
        $passedCount = count(array_filter($steps, fn($s) => $s['status'] === 'passed'));

        return [
            'all_passed'     => $allPassed && ($passedCount === count($steps)),
            'passed_count'   => $passedCount,
            'total_steps'    => count($steps),
            'execution_time' => $totalDurationMs,
            'steps'          => $steps,
            'simulated_at'   => now()->toDateTimeString(),
            'is_rolled_back' => $rollback,
        ];
    }
}
