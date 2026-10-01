<?php

namespace App\Http\Requests\Admin\Pos;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ScrapPricingTier;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class StorePosInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id'             => ['nullable', 'exists:branches,id'],
            'customer_id'           => ['nullable', 'exists:customers,id'],
            'customer_vehicle_id'   => ['nullable', 'exists:customer_vehicles,id'],
            'technician_id'         => ['required', 'exists:employees,id'],

            // Items
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.unit_price'    => ['nullable', 'numeric', 'min:0'],
            'items.*.battery_serial'=> ['nullable', 'string', 'max:100'],

            // Scrap trade-in
            'has_scrap'             => ['sometimes', 'boolean'],
            'scrap_capacity_ah'     => ['nullable', 'integer', 'min:30', 'max:250'],
            'scrap_count'           => ['nullable', 'integer', 'min:1'],
            'scrap_price_override'  => ['nullable', 'numeric', 'min:0'],
            'scrap_deduction_amount'=> ['nullable', 'numeric', 'min:0'],

            // Discounts & Tax
            'discount_amount'       => ['nullable', 'numeric', 'min:0'],
            'tax_amount'            => ['nullable', 'numeric', 'min:0'],

            // Split Payments
            'payments'              => ['required', 'array', 'min:1'],
            'payments.*.method'     => ['required', 'in:cash,card,bank_transfer,credit'],
            'payments.*.amount'     => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference'  => ['nullable', 'string', 'max:100'],

            // Manager Override Code for Credit Limit Exceed
            'manager_override_code' => ['nullable', 'string'],
            'notes'                 => ['nullable', 'string', 'max:500'],
            // Client-generated per-checkout key; a retried submission returns the original invoice.
            'idempotency_key'       => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // A retry of an already-committed checkout (same idempotency key) must not be re-validated:
            // its serials and stock were consumed by the original sale. The service returns that invoice.
            $idempotencyKey = trim((string) $this->input('idempotency_key', ''));
            if ($idempotencyKey !== '') {
                $existingCashierId = \App\Models\Invoice::where('idempotency_key', $idempotencyKey)->value('cashier_id');
                if ($existingCashierId !== null) {
                    if ((int) $existingCashierId !== (int) $this->user()?->id) {
                        $validator->errors()->add('idempotency_key', 'مفتاح منع التكرار مستخدم لعملية بيع أخرى.');
                    }
                    return;
                }
            }

            $items = $this->input('items', []);
            if (!is_array($items) || empty($items)) {
                return;
            }

            // 1. Validate Product stock and Battery Serials
            $productIds = collect($items)->pluck('product_id')->filter()->unique()->values()->all();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $batterySerials = [];
            $subtotal = 0.0;

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $qty = (int) ($item['quantity'] ?? 1);
                $product = $products->get($productId);

                if (!$product) {
                    continue;
                }

                // Check stock
                if ($product->current_stock < $qty) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "الرصيد المتاح من الصنف ({$product->name}) هو {$product->current_stock} فقط، لا يكفي لصرف {$qty}."
                    );
                }

                // Unit price
                $price = isset($item['unit_price']) && is_numeric($item['unit_price'])
                    ? (float) $item['unit_price']
                    : (float) $product->retail_price;
                $subtotal += ($price * $qty);

                // Battery Serial Validation
                if ($product->is_battery) {
                    $serial = trim((string) ($item['battery_serial'] ?? ''));
                    if ($serial === '') {
                        $validator->errors()->add(
                            "items.{$index}.battery_serial",
                            "السيريال مطلوب إجبارياً للبطارية ({$product->name})."
                        );
                    } else {
                        // Check if serial duplicated within this order
                        if (in_array($serial, $batterySerials, true)) {
                            $validator->errors()->add(
                                "items.{$index}.battery_serial",
                                "سيريال البطارية ({$serial}) مكرر في بنود نفس الفاتورة."
                            );
                        } else {
                            $batterySerials[] = $serial;
                        }

                        // Check if serial already exists in active warranties
                        if (Warranty::where('serial_number', $serial)->exists()) {
                            $validator->errors()->add(
                                "items.{$index}.battery_serial",
                                "سيريال البطارية ({$serial}) مسجل في النظام مسبقاً ولديه شهادة ضمان سابقة."
                            );
                        }
                    }
                }
            }

            // 2. Validate Scrap Deduction (Flexible: supports manual custom price per battery or direct total scrap deduction)
            $scrapDeduction = 0.0;
            if ($this->boolean('has_scrap')) {
                $count = max(1, (int) $this->input('scrap_count', 1));
                $ah = (int) $this->input('scrap_capacity_ah', 70);

                if ($this->filled('scrap_deduction_amount')) {
                    $scrapDeduction = round((float) $this->input('scrap_deduction_amount'), 2);
                } elseif ($this->filled('scrap_price_override')) {
                    $scrapDeduction = round((float) $this->input('scrap_price_override') * $count, 2);
                } else {
                    $tier = ScrapPricingTier::findPriceForCapacity($ah);
                    if ($tier) {
                        $scrapDeduction = (float) $tier->default_scrap_price * $count;
                    }
                }
            }

            // 3. Validate Discount & Price Permissions (Cashier restrictions)
            /** @var \App\Models\User|null $currentUser */
            $currentUser = $this->user();
            $canDiscount = $currentUser ? $currentUser->can('invoices.discount') : true;

            $discount = (float) $this->input('discount_amount', 0);
            if ($discount > 0 && !$canDiscount) {
                $overrideCode = (string) $this->input('manager_override_code');
                if (!$this->isManagerOverrideValid($overrideCode)) {
                    $validator->errors()->add(
                        'discount_amount',
                        'ليس لديك صلاحية تطبيق خصومات على الفاتورة. يلزم إدخال كود موافقة المشرف/المدير للمتابعة.'
                    );
                }
            }

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $product = $products->get($productId);
                if ($product && isset($item['unit_price']) && is_numeric($item['unit_price'])) {
                    $inputPrice = (float) $item['unit_price'];
                    if ($inputPrice < (float) $product->retail_price && !$canDiscount) {
                        $overrideCode = (string) $this->input('manager_override_code');
                        if (!$this->isManagerOverrideValid($overrideCode)) {
                            $validator->errors()->add(
                                "items.{$index}.unit_price",
                                "ليس لديك صلاحية تخفيض سعر بيع الصنف ({$product->name}) عن السعر الرسمي ({$product->retail_price} ج.م). يلزم إدخال كود موافقة المشرف."
                            );
                        }
                    }
                }
            }

            // 4. Validate Discount + Scrap does not exceed gross
            $tax = round((float) $this->input('tax_amount', 0), 2);
            $gross = round($subtotal + $tax, 2);
            if ($discount + $scrapDeduction > $gross + 0.01) {
                $validator->errors()->add(
                    'discount_amount',
                    'مجموع الخصم وخصم الكهنة (' . number_format($discount + $scrapDeduction, 2) . ' ج.م) يتجاوز إجمالي الفاتورة قبل الخصم (' . number_format($gross, 2) . ' ج.م).'
                );
            }
            $finalAmount = round(max(0, $gross - $discount - $scrapDeduction), 2);

            $payments = $this->input('payments', []);
            $totalPayments = 0.0;
            $creditAmount = 0.0;

            foreach ($payments as $payment) {
                $amount = (float) ($payment['amount'] ?? 0);
                $totalPayments += $amount;
                if (($payment['method'] ?? '') === 'credit') {
                    $creditAmount += $amount;
                }
            }

            $eps = (float) config('finance.epsilon', 0.01);
            if (abs($totalPayments - $finalAmount) > $eps) {
                $validator->errors()->add(
                    'payments',
                    sprintf(
                        'إجمالي مبالغ الدفعات المجزأة (%s ج.م) لا يتطابق مع صافي الفاتورة الإجمالي بعد خصم الكهنة (%s ج.م).',
                        number_format($totalPayments, 2),
                        number_format($finalAmount, 2)
                    )
                );
            }

            // 5. Validate Credit Limit & Manager Override
            if ($creditAmount > 0) {
                $customerId = $this->input('customer_id');
                if (!$customerId) {
                    $validator->errors()->add('payments', 'لا يمكن استخدام طريقة الدفع بالآجل لعميل نقدي عابر غير مسجل.');
                    return;
                }

                $customer = Customer::find($customerId);
                if ($customer) {
                    $newBalance = (float) $customer->current_credit_balance + $creditAmount;
                    $creditLimit = (float) $customer->credit_limit;

                    if ($newBalance > $creditLimit) {
                        // Manager override code is mandatory
                        $overrideCode = (string) $this->input('manager_override_code');
                        if (!$this->isManagerOverrideValid($overrideCode)) {
                            $validator->errors()->add(
                                'manager_override_code',
                                sprintf(
                                    'الرصيد الآجل المطلوب (%s ج.م) سيتجاوز سقف ائتمان العميل (%s ج.م). الرصيد الحالي: %s ج.م. يلزم إدخال كود موافقة المدير للاستثناء.',
                                    number_format($newBalance, 2),
                                    number_format($creditLimit, 2),
                                    number_format((float) $customer->current_credit_balance, 2)
                                )
                            );
                        }
                    }
                }
            }
        });
    }

    protected function isManagerOverrideValid(?string $code): bool
    {
        return app(\App\Services\Finance\ManagerOverrideService::class)->isValid($code);
    }

    public function messages(): array
    {
        return [
            'technician_id.required'            => 'يجب اختيار الفني / العامل المسؤول عن التركيب.',
            'technician_id.exists'              => 'الفني المختار غير مسجل بالنظام.',
            'items.required'                    => 'يجب إضافة منتج واحد على الأقل في الفاتورة.',
            'items.*.product_id.required'       => 'يرجى اختيار المنتج.',
            'items.*.quantity.required'         => 'الكمية مطلوبة.',
            'items.*.quantity.min'              => 'الكمية يجب أن تكون 1 على الأقل.',
            'scrap_capacity_ah.required_if'     => 'يرجى تحديد سعة بطارية الكهنة بالأمبير (Ah).',
            'scrap_count.required_if'           => 'يرجى تحديد عدد بطاريات الكهنة المستلمة.',
            'payments.required'                 => 'يجب تحديد طريقة الدفع وتوزيع المبالغ.',
            'payments.*.method.required'        => 'طريقة الدفع مطلوبة.',
            'payments.*.amount.required'        => 'مبلغ الدفعة مطلوب.',
            'payments.*.amount.min'             => 'مبلغ الدفعة يجب أن يكون أكبر من الصفر.',
        ];
    }
}
