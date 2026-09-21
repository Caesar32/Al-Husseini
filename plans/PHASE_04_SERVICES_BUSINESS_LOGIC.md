# المرحلة الرابعة: طبقة الخدمات والمنطق التجاري (Services & Business Logic Architecture)

> **طبيعة الملف:** وثيقة تقنية مركزية تعزل منطق العمليات الحسابية والمالية وقواعد العمل (Business Logic) داخل Services مع استخدام الـ Database Transactions و Pessimistic Locking لمنع التعارضات والـ Race Conditions.

---

## 1. شجرة الخدمات (Services Directory)

```
app/Services/
├── Hr/
│   ├── AttendanceService.php
│   └── PayrollService.php
├── Pos/
│   ├── PosOrderService.php
│   └── CreditLedgerService.php
└── Inventory/
    └── ScrapInventoryService.php
```

---

## 2. خدمة الحضور والانصراف (AttendanceService)

تتكفل الخدمة بمعالجة البصمات الواردة واحتساب التأخيرات وساعات العمل الإضافي وفق القواعد الدقيقة:

```php
<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * تسجيل حركة بصمة (حضور أو انصراف)
     */
    public function recordPunch(string $zktecoPin, Carbon $punchTime, int $punchState): Attendance
    {
        return DB::transaction(function () use ($zktecoPin, $punchTime, $punchState) {
            $employee = Employee::where('zkteco_pin', $zktecoPin)->firstOrFail();
            $workDate = $punchTime->toDateString();

            $attendance = Attendance::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_date' => $workDate,
                ],
                [
                    'status' => 'present',
                    'source' => 'zkteco',
                ]
            );

            if ($punchState === 0) { // Check-In
                if (!$attendance->check_in || $punchTime->lessThan($attendance->check_in)) {
                    $attendance->check_in = $punchTime;
                    $this->calculateLateness($attendance, $employee, $punchTime);
                }
            } elseif ($punchState === 1) { // Check-Out
                if (!$attendance->check_out || $punchTime->greaterThan($attendance->check_out)) {
                    $attendance->check_out = $punchTime;
                    $this->calculateEarlyLeaveAndOvertime($attendance, $employee, $punchTime);
                }
            }

            $attendance->save();
            return $attendance;
        });
    }

    private function calculateLateness(Attendance $attendance, Employee $employee, Carbon $checkIn): void
    {
        $shiftStart = Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $employee->shift_start_time);
        if ($checkIn->greaterThan($shiftStart)) {
            $diffMinutes = $shiftStart->diffInMinutes($checkIn);
            if ($diffMinutes > $employee->grace_period_minutes) {
                $attendance->late_minutes = $diffMinutes;
                $attendance->status = 'late';
            } else {
                $attendance->late_minutes = 0;
            }
        }
    }

    private function calculateEarlyLeaveAndOvertime(Attendance $attendance, Employee $employee, Carbon $checkOut): void
    {
        $shiftEnd = Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $employee->shift_end_time);

        if ($checkOut->lessThan($shiftEnd)) {
            $attendance->early_leave_minutes = $checkOut->diffInMinutes($shiftEnd);
        } else {
            $overtimeMinutes = $shiftEnd->diffInMinutes($checkOut);
            // احتساب الوقت الإضافي إذا تجاوز 30 دقيقة بعد انتهاء الشفت
            if ($overtimeMinutes >= 30) {
                $attendance->overtime_hours = round($overtimeMinutes / 60, 2);
            }
        }
    }
}
```

---

## 3. محرك احتساب الرواتب الشهري (PayrollService)

يقوم بحساب صافي رواتب الموظفين مع خصم أيام الغياب ودقائق التأخير وإضافة البدلات والأوفر تايم:

$$\text{Net Salary} = \text{Basic} + \text{Allowances} + (\text{Overtime Hours} \times \text{Hourly Rate} \times 1.5) - \text{Deductions} - (\text{Absence Days} \times \text{Day Rate})$$

```php
<?php

namespace App\Services\Hr;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeDeduction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class PayrollService
{
    /**
     * توليد مسودة مسير الرواتب لفرع محدد لشهر وسنة معينة
     */
    public function generateMonthlyPayroll(int $branchId, int $year, int $month): Payroll
    {
        return DB::transaction(function () use ($branchId, $year, $month) {
            $existing = Payroll::where('branch_id', $branchId)->where('year', $year)->where('month', $month)->first();
            if ($existing && $existing->status === 'disbursed') {
                throw new Exception("لا يمكن إعادة احتساب مسير رواتب تم صرفه بالفعل.");
            }

            $payroll = Payroll::updateOrCreate(
                ['branch_id' => $branchId, 'year' => $year, 'month' => $month],
                ['status' => 'draft']
            );

            // حذف البنود القديمة إذا كانت مسودة
            $payroll->items()->delete();

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $totalMonthDays = $startDate->daysInMonth;

            $employees = Employee::with(['currentSalary'])
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->get();

            $totalBasic = 0;
            $totalAllowances = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            foreach ($employees as $employee) {
                $salary = $employee->currentSalary;
                if (!$salary) continue;

                $basic = $salary->basic_salary;
                $allowances = $salary->housing_allowance + $salary->transport_allowance + $salary->other_allowances;
                $dayRate = $basic / $totalMonthDays;
                $hourlyRate = $dayRate / 8; // معيار شفت 8 ساعات

                // 1. حساب الحضور والغياب والتأخير
                $attendances = Attendance::where('employee_id', $employee->id)
                    ->whereBetween('work_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->get();

                $presentDays = $attendances->where('status', '!=', 'absent')->count();
                $absentDays = max(0, 26 - $presentDays); // معيار 26 يوم عمل (4 أيام عطلة أسبوعية)
                $absenceCost = $absentDays * $dayRate;

                $totalLateMinutes = $attendances->sum('late_minutes');
                $totalOvertimeHours = $attendances->sum('overtime_hours');
                $overtimeValue = round($totalOvertimeHours * $hourlyRate * 1.5, 2);

                // 2. تجميع الجزاءات المعتمدة خلال الشهر
                $approvedDeductions = EmployeeDeduction::where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereBetween('deduction_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->sum('amount');

                $allDeductions = round($absenceCost + $approvedDeductions, 2);
                $netSalary = max(0, round(($basic + $allowances + $overtimeValue) - $allDeductions, 2));

                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $basic,
                    'total_allowance' => $allowances,
                    'total_deduction' => $allDeductions,
                    'total_overtime' => $overtimeValue,
                    'net_salary' => $netSalary,
                    'absent_days' => $absentDays,
                    'late_minutes_total' => $totalLateMinutes,
                ]);

                $totalBasic += $basic;
                $totalAllowances += $allowances;
                $totalDeductions += $allDeductions;
                $totalNet += $netSalary;
            }

            $payroll->update([
                'total_basic' => $totalBasic,
                'total_allowances' => $totalAllowances,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
            ]);

            return $payroll;
        });
    }
}
```

---

## 4. خدمة مبيعات نقطة البيع (PosOrderService)

تتحكم في العملية الذرية (Atomic Transaction) لإصدار الفاتورة، توليد الكود التسلسلي الموحد، وتوزيع الحسابات:

```php
<?php

namespace App\Services\Pos;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Exception;

class PosOrderService
{
    /**
     * إنشاء فاتورة مبيعات جديدة بالكامل
     */
    public function createInvoice(array $data, int $cashierId): Invoice
    {
        return DB::transaction(function () use ($data, $cashierId) {
            // توليد رقم الفاتورة التسلسلي (INV-YYYYMM-XXXXX)
            $prefix = 'INV-' . date('Ym') . '-';
            $latestInvoice = Invoice::where('invoice_number', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $nextSequence = 1;
            if ($latestInvoice) {
                $lastNumber = (int) str_replace($prefix, '', $latestInvoice->invoice_number);
                $nextSequence = $lastNumber + 1;
            }
            $invoiceNumber = $prefix . str_pad((string)$nextSequence, 5, '0', STR_PAD_LEFT);

            // احتساب إجماليات الأصناف
            $subtotal = 0;
            $itemsToCreate = [];

            foreach ($data['items'] as $itemData) {
                $product = Product::where('id', $itemData['product_id'])->lockForUpdate()->firstOrFail();

                if ($product->current_stock < $itemData['quantity']) {
                    throw new Exception("رصيد المخزون لا يكفي للصنف: {$product->name}");
                }

                $lineTotal = round($itemData['quantity'] * $itemData['unit_price'], 2);
                $subtotal += $lineTotal;

                $itemsToCreate[] = [
                    'product' => $product,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $lineTotal,
                    'battery_serial_number' => $itemData['battery_serial_number'] ?? null,
                    'warranty_duration_months' => $itemData['warranty_duration_months'] ?? $product->warranty_months,
                ];
            }

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $scrapDeductionAmount = (float) ($data['scrap_deduction_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);

            $finalAmount = max(0, round(($subtotal - $discountAmount - $scrapDeductionAmount) + $taxAmount, 2));
            $paidAmount = min($finalAmount, (float) ($data['paid_amount'] ?? 0));
            $remainingAmount = max(0, round($finalAmount - $paidAmount, 2));

            $status = $remainingAmount == 0 ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid');

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'customer_vehicle_id' => $data['customer_vehicle_id'] ?? null,
                'technician_id' => $data['technician_id'] ?? null,
                'cashier_id' => $cashierId,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'scrap_deduction_amount' => $scrapDeductionAmount,
                'tax_amount' => $taxAmount,
                'final_amount' => $finalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_method' => $data['payment_method'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($itemsToCreate as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'battery_serial_number' => $item['battery_serial_number'],
                    'warranty_duration_months' => $item['warranty_duration_months'],
                ]);
            }

            return $invoice; // InvoiceObserver يتكفل آلياً بإنشاء الضمان وخصم المخزون وتسجيل دفتر الآجل
        });
    }
}
```

---

## 5. خدمة تسوية المديونيات (CreditLedgerService)

```php
<?php

namespace App\Services\Pos;

use App\Models\Customer;
use App\Models\CreditLedgerEntry;
use Illuminate\Support\Facades\DB;
use Exception;

class CreditLedgerService
{
    /**
     * تحصيل دفعة نقدية لسداد جزء أو كل مديونية العميل
     */
    public function settlePayment(int $customerId, float $amount, int $collectedBy, ?string $receiptNumber = null, ?string $notes = null): CreditLedgerEntry
    {
        return DB::transaction(function () use ($customerId, $amount, $collectedBy, $receiptNumber, $notes) {
            $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

            if ($amount > $customer->current_credit_balance) {
                throw new Exception("المبلغ المدفوع يتجاوز رصيد المديونية المستحقة.");
            }

            $balanceBefore = $customer->current_credit_balance;
            $balanceAfter = round($balanceBefore - $amount, 2);

            $customer->update(['current_credit_balance' => $balanceAfter]);

            return CreditLedgerEntry::create([
                'customer_id' => $customer->id,
                'entry_type' => 'payment_collection',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'collected_by' => $collectedBy,
                'receipt_number' => $receiptNumber,
                'notes' => $notes ?? 'تحصيل دفعة نقدية من المديونية',
            ]);
        });
    }
}
```
