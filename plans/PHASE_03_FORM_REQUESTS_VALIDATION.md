# المرحلة الثالثة: طلبات التحقق وتأمين المدخلات (Form Requests & Validation Architecture)

> **طبيعة الملف:** وثيقة تقنية لمطوري الـ Backend تحدد فئات الـ Form Requests الخاصة بنظام الحسيني للبطاريات مع رسائل الخطأ العربية وقواعد التحقق المتقدمة (Custom Validation & Business Invariants).

---

## 1. شجرة فئات التحقق (Requests Directory Structure)

```
app/Http/Requests/
├── Pos/
│   ├── CreatePosInvoiceRequest.php
│   └── SettleCreditPaymentRequest.php
├── Hr/
│   ├── StoreEmployeeRequest.php
│   ├── UpdateEmployeeRequest.php
│   ├── RecordPunchRequest.php
│   └── StoreDeductionRequest.php
└── Inventory/
    ├── StoreProductRequest.php
    └── ScrapBatchTransferRequest.php
```

---

## 2. فئات طلبات نقاط البيع (POS Requests)

### `app/Http/Requests/Pos/CreatePosInvoiceRequest.php`
يقوم بالتحقق من سلامة الفاتورة ومنع التجاوزات المالية والائتمانية:
- التحقق من كفاية المخزون لكل بطارية وصنف.
- إلزام إدخال الرقم التسلسلي في حال كان الصنف بطارية.
- منع تجاوز سقف الائتمان (Credit Limit) للعميل في حالة البيع بالآجل.

```php
<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Customer;
use App\Models\Product;

class CreatePosInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_vehicle_id' => [
                'nullable',
                Rule::exists('customer_vehicles', 'id')->where(function ($query) {
                    return $query->where('customer_id', $this->input('customer_id'));
                }),
            ],
            'technician_id' => ['nullable', 'exists:employees,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.battery_serial_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('warranties', 'serial_number'),
            ],
            'items.*.warranty_duration_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'scrap_deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'credit', 'split'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // 1. التحقق من المخزون والأرقام التسلسلية
            if (is_array($this->items)) {
                foreach ($this->items as $index => $itemData) {
                    $product = Product::find($itemData['product_id'] ?? null);
                    if ($product) {
                        if ($product->current_stock < ($itemData['quantity'] ?? 0)) {
                            $validator->errors()->add("items.{$index}.quantity", "المخزون غير كافٍ للصنف ({$product->name}). المتاح: {$product->current_stock}");
                        }
                        if ($product->is_battery && empty($itemData['battery_serial_number'])) {
                            $validator->errors()->add("items.{$index}.battery_serial_number", "يجب إدخال الرقم التسلسلي لكارت الضمان للصنف ({$product->name})");
                        }
                    }
                }
            }

            // 2. التحقق من سقف المديونية في حالة الآجل
            if ($this->customer_id) {
                $customer = Customer::find($this->customer_id);
                if ($customer) {
                    $subtotal = collect($this->items)->sum(fn($i) => ($i['quantity'] ?? 0) * ($i['unit_price'] ?? 0));
                    $netTotal = max(0, $subtotal - ($this->discount_amount ?? 0) - ($this->scrap_deduction_amount ?? 0));
                    $remaining = max(0, $netTotal - ($this->paid_amount ?? 0));

                    if ($remaining > 0 && ($customer->current_credit_balance + $remaining) > $customer->credit_limit) {
                        $validator->errors()->add(
                            'paid_amount',
                            "المبلغ المتبقي ({$remaining} ج.م) يتجاوز الحد الائتماني للعميل. الرصيد الحالي: ({$customer->current_credit_balance})، الحد المسموح: ({$customer->credit_limit})."
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'يرجى تحديد الفرع الذي يتم البيع منه.',
            'items.required' => 'يجب إضافة صنف واحد على الأقل إلى الفاتورة.',
            'items.*.product_id.required' => 'معرف الصنف مطلوب.',
            'items.*.quantity.min' => 'يجب ألا تقل الكمية عن 1.',
            'items.*.battery_serial_number.unique' => 'الرقم التسلسلي للبطارية مسجل مسبقاً في ضمان سارٍ.',
            'paid_amount.required' => 'يرجى إدخال المبلغ المدفوع.',
            'payment_method.required' => 'يرجى تحديد طريقة الدفع.',
        ];
    }
}
```

---

### `app/Http/Requests/Pos/SettleCreditPaymentRequest.php`
يتحقق من سداد مديونية العميل:

```php
<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Customer;

class SettleCreditPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'receipt_number' => ['nullable', 'string', 'max:50', 'unique:credit_ledger_entries,receipt_number'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $customer = Customer::find($this->customer_id);
            if ($customer && $this->amount > $customer->current_credit_balance) {
                $validator->errors()->add(
                    'amount',
                    "المبلغ المدخل ({$this->amount} ج.م) أكبر من إجمالي المديونية المستحقة على العميل ({$customer->current_credit_balance} ج.م)."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'يرجى اختيار العميل المسدد.',
            'amount.required' => 'يرجى إدخال مبلغ التحصيل.',
            'amount.min' => 'أقل قيمة للتحصيل هي 1 ج.م.',
            'receipt_number.unique' => 'رقم إيصال الاستلام مسجل مسبقاً.',
        ];
    }
}
```

---

## 3. فئات طلبات شؤون الموظفين (HR Requests)

### `app/Http/Requests/Hr/StoreEmployeeRequest.php`
```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage-employees') ?? true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'job_title_id' => ['required', 'exists:job_titles,id'],
            'employee_code' => ['required', 'string', 'max:30', 'unique:employees,employee_code'],
            'full_name' => ['required', 'string', 'max:150'],
            'national_id' => ['required', 'string', 'size:14', 'unique:employees,national_id'],
            'phone' => ['required', 'string', 'max:20', 'unique:employees,phone'],
            'hire_date' => ['required', 'date'],
            'shift_start_time' => ['required', 'date_format:H:i'],
            'shift_end_time' => ['required', 'date_format:H:i', 'after:shift_start_time'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'zkteco_pin' => ['nullable', 'string', 'max:50', 'unique:employees,zkteco_pin'],
            'basic_salary' => ['required', 'numeric', 'min:1000'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_code.unique' => 'كود الموظف مستخدم بالفعل.',
            'national_id.size' => 'الرقم القومي يجب أن يتكون من 14 رقماً بالضبط.',
            'national_id.unique' => 'الرقم القومي مسجل لموظف آخر.',
            'phone.unique' => 'رقم الهاتف مسجل مسبقاً.',
            'shift_end_time.after' => 'وقت نهاية الشفت يجب أن يكون بعد وقت البداية.',
            'basic_salary.required' => 'الراتب الأساسي مطلوب.',
        ];
    }
}
```

---

### `app/Http/Requests/Hr/RecordPunchRequest.php`
يستقبل نبضات أجهزة البصمة البيومترية ZKTeco عبر بروتوكول ADMS/HTTP Webhook:

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class RecordPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التحقق من API Token الخاص بجهاز البصمة
        return $this->header('X-ZKTeco-Token') === config('services.zkteco.webhook_secret');
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'exists:employees,zkteco_pin'],
            'timestamp' => ['required', 'date_format:Y-m-d H:i:s'],
            'punch_state' => ['required', 'in:0,1,4,5'], // 0=CheckIn, 1=CheckOut, 4=OvertimeIn, 5=OvertimeOut
            'device_sn' => ['required', 'string', 'max:50'],
        ];
    }
}
```

---

### `app/Http/Requests/Hr/StoreDeductionRequest.php`
تسجيل جزاء مالي يدوي أو استقطاع سلفة:

```php
<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('manage-deductions') ?? true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'deduction_rule_id' => ['nullable', 'exists:deduction_rules,id'],
            'deduction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى اختيار الموظف المستهدف.',
            'amount.required' => 'مبلغ الجزاء مطلوب.',
            'amount.min' => 'أقل قيمة للجزاء هي 1 ج.م.',
            'reason.required' => 'يرجى كتابة سبب توقيع الجزاء بشكل مفصل.',
        ];
    }
}
```
