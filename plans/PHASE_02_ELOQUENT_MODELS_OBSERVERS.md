# المرحلة الثانية: نماذج Eloquent والمراقبين (Eloquent Models, Scopes & Observers)

> **طبيعة الملف:** وثيقة تقنية متقدمة تحدد تعريفات الـ Eloquent Models، العلاقات، الـ Scopes، و الـ Model Observers التي تضمن أتمتة المنطق المحاسبي والمخزني لمركز الحسيني للبطاريات.

---

## 1. نماذج الموظفين والرواتب (HR & Payroll Models)

### `app/Models/Employee.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id', 'job_title_id', 'user_id', 'employee_code',
        'full_name', 'national_id', 'phone', 'hire_date',
        'shift_start_time', 'shift_end_time', 'grace_period_minutes',
        'zkteco_pin', 'status',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'grace_period_minutes' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function currentSalary(): HasOne
    {
        return $this->hasOne(SalaryStructure::class)->where('is_current', true);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeTechnicians(Builder $query): void
    {
        $query->whereHas('jobTitle', fn($q) => $q->where('title', 'like', '%فني%')->orWhere('title', 'like', '%كهربائي%'));
    }
}
```

---

### `app/Models/Attendance.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'check_in', 'check_out',
        'late_minutes', 'early_leave_minutes', 'overtime_hours',
        'status', 'source',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function scopeLateToday(Builder $query): void
    {
        $query->where('work_date', today())->where('late_minutes', '>', 0);
    }

    public function scopeAbsentToday(Builder $query): void
    {
        $query->where('work_date', today())->where('status', 'absent');
    }
}
```

---

## 2. نماذج العملاء والمديونيات (Customer & Credit Models)

### `app/Models/Customer.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'national_id', 'credit_limit',
        'current_credit_balance', 'tier', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_credit_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(CustomerVehicle::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditLedgers(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class)->orderByDesc('id');
    }

    public function scopeInDebt(Builder $query): void
    {
        $query->where('current_credit_balance', '>', 0);
    }

    public function scopeExceededLimit(Builder $query): void
    {
        $query->whereColumn('current_credit_balance', '>', 'credit_limit')
              ->where('credit_limit', '>', 0);
    }
}
```

---

### `app/Models/CustomerVehicle.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerVehicle extends Model
{
    protected $fillable = [
        'customer_id', 'plate_number', 'car_brand', 'car_model',
        'model_year', 'chassis_number', 'last_odometer', 'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }
}
```

---

## 3. نماذج المخزون والبطاريات (Inventory & Batteries)

### `app/Models/Product.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'name', 'brand',
        'capacity_ah', 'voltage', 'terminal_type', 'warranty_months',
        'cost_price', 'retail_price', 'wholesale_price', 'current_stock',
        'reorder_threshold', 'is_battery', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'current_stock' => 'integer',
            'reorder_threshold' => 'integer',
            'is_battery' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeBatteriesOnly(Builder $query): void
    {
        $query->where('is_battery', true);
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('current_stock', '<=', 'reorder_threshold');
    }
}
```

---

## 4. نماذج نقاط البيع والفواتير والضمان (POS, Invoices & Warranties)

### `app/Models/Invoice.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'branch_id', 'customer_id', 'customer_vehicle_id',
        'technician_id', 'cashier_id', 'subtotal', 'discount_amount',
        'scrap_deduction_amount', 'tax_amount', 'final_amount', 'paid_amount',
        'remaining_amount', 'payment_method', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'scrap_deduction_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function customerVehicle(): BelongsTo { return $this->belongsTo(CustomerVehicle::class); }
    public function technician(): BelongsTo { return $this->belongsTo(Employee::class, 'technician_id'); }
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'cashier_id'); }
    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function scrapBattery(): HasOne { return $this->hasOne(ScrapBatteriesInventory::class); }
    public function creditEntries(): HasMany { return $this->hasMany(CreditLedgerEntry::class); }
}
```

---

### `app/Models/InvoiceItem.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'product_id', 'quantity', 'unit_price',
        'total_price', 'battery_serial_number', 'warranty_duration_months',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function warranty(): HasOne { return $this->hasOne(Warranty::class); }
}
```

---

## 5. مراقبو النماذج (Model Observers & Business Automation)

### `app/Observers/InvoiceObserver.php`
ينفذ هذا المراقب العمليات التلقائية بمجرد حفظ فاتورة المبيعات الجديدة لضمان سلامة الدورة المستندية دون أي تدخل يدوي:
1. **خصم الكميات المباعة من المخزون**
2. **إصدار شهادات الضمان للبطاريات ذات الأرقام التسلسلية**
3. **تحديث مديونية العميل ودفتر الأستاذ المزدوج**
4. **تسجيل بطارية الكهنة في مخزن الخردة إذا كان هناك خصم استبدال**

```php
<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\CreditLedgerEntry;
use App\Models\ScrapBatteriesInventory;
use App\Models\Warranty;
use Illuminate\Support\Facades\DB;

class InvoiceObserver
{
    public function created(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // 1. معالجة بنود الفاتورة: خصم المخزون وإنشاء الضمان
            foreach ($invoice->items as $item) {
                // خصم المخزون
                $item->product->decrement('current_stock', $item->quantity);

                // إنشاء كارت الضمان إذا توفر رقم تسلسلي للمنتج وكان بطارية
                if ($item->battery_serial_number && $invoice->customer_id) {
                    Warranty::create([
                        'invoice_item_id' => $item->id,
                        'customer_id' => $invoice->customer_id,
                        'customer_vehicle_id' => $invoice->customer_vehicle_id,
                        'serial_number' => $item->battery_serial_number,
                        'start_date' => now()->toDateString(),
                        'end_date' => now()->addMonths($item->warranty_duration_months)->toDateString(),
                        'status' => 'active',
                    ]);
                }
            }

            // 2. معالجة الآجل وإضافة قيد في دفتر الأستاذ
            if ($invoice->remaining_amount > 0 && $invoice->customer_id) {
                $customer = $invoice->customer;
                $balanceBefore = $customer->current_credit_balance;
                $balanceAfter = $balanceBefore + $invoice->remaining_amount;

                // تحديث رصيد العميل
                $customer->update(['current_credit_balance' => $balanceAfter]);

                // تسجيل القيد المزدوج
                CreditLedgerEntry::create([
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'entry_type' => 'invoice_debt',
                    'amount' => $invoice->remaining_amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'collected_by' => $invoice->cashier_id,
                    'notes' => "تسجيل متبقي آجل من الفاتورة رقم {$invoice->invoice_number}",
                ]);
            }

            // 3. معالجة بطارية الكهنة (Scrap Replacement)
            if ($invoice->scrap_deduction_amount > 0) {
                ScrapBatteriesInventory::create([
                    'branch_id' => $invoice->branch_id,
                    'invoice_id' => $invoice->id,
                    'capacity_ah' => 'Old/Replaced',
                    'scrap_value' => $invoice->scrap_deduction_amount,
                    'status' => 'in_stock',
                    'received_by' => $invoice->technician_id ?? 1,
                ]);
            }
        });
    }
}
```

---

### `app/Observers/AttendanceObserver.php`
يقوم هذا المراقب باحتساب التأخير تلقائياً وتوليد مقترح جزاء مالي (Draft Deduction):

```php
<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\EmployeeDeduction;
use App\Models\DeductionRule;
use Carbon\Carbon;

class AttendanceObserver
{
    public function saving(Attendance $attendance): void
    {
        // حساب دقائق التأخير بناءً على بداية الشفت وسماحية الموظف
        if ($attendance->check_in) {
            $employee = $attendance->employee;
            $shiftStart = Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $employee->shift_start_time);
            $checkIn = Carbon::parse($attendance->check_in);

            if ($checkIn->greaterThan($shiftStart)) {
                $diffMinutes = $shiftStart->diffInMinutes($checkIn);
                if ($diffMinutes > $employee->grace_period_minutes) {
                    $attendance->late_minutes = $diffMinutes;
                    $attendance->status = 'late';
                } else {
                    $attendance->late_minutes = 0;
                }
            } else {
                $attendance->late_minutes = 0;
            }
        }
    }

    public function saved(Attendance $attendance): void
    {
        // إذا كان متأخراً أكثر من 30 دقيقة يتم إنشاء مسودة جزاء مالي تلقائية
        if ($attendance->late_minutes >= 30) {
            $employee = $attendance->employee;
            $currentSalary = $employee->currentSalary?->basic_salary ?? 0;
            $dayWage = $currentSalary > 0 ? ($currentSalary / 30) : 0;
            $deductionAmount = round($dayWage * 0.25, 2); // خصم ربع يوم للتأخير أكثر من نصف ساعة

            if ($deductionAmount > 0) {
                EmployeeDeduction::firstOrCreate(
                    ['attendance_id' => $attendance->id],
                    [
                        'employee_id' => $attendance->employee_id,
                        'deduction_date' => $attendance->work_date,
                        'amount' => $deductionAmount,
                        'reason' => "تأخير تلقائي لمدة {$attendance->late_minutes} دقيقة",
                        'status' => 'pending',
                    ]
                );
            }
        }
    }
}
```
