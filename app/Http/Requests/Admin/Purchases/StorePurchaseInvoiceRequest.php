<?php

namespace App\Http\Requests\Admin\Purchases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'             => ['required', 'exists:suppliers,id'],
            'branch_id'               => ['nullable', 'exists:branches,id'],
            'invoice_number'          => ['required', 'string', 'max:50', 'unique:purchase_invoices,invoice_number'],
            'invoice_date'            => ['required', 'date'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.product_id'      => ['required', 'exists:products,id'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.unit_cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.batch_number'    => ['nullable', 'string', 'max:50'],
            'items.*.production_date' => ['nullable', 'date'],
            'items.*.supplier_sku'    => ['nullable', 'string', 'max:100'],
            'tax_amount'              => ['nullable', 'numeric', 'min:0'],
            'discount_amount'         => ['nullable', 'numeric', 'min:0'],
            'paid_amount'             => ['required', 'numeric', 'min:0'],
            'payment_method'          => ['required', 'in:cash,bank_transfer,cheque'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (!is_array($items) || empty($items)) {
                return;
            }

            $subtotal = 0;
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $price = (float) ($item['unit_cost_price'] ?? 0);
                $subtotal += ($qty * $price);
            }

            $tax = (float) $this->input('tax_amount', 0);
            $discount = (float) $this->input('discount_amount', 0);
            $finalAmount = max(0, $subtotal + $tax - $discount);
            $paidAmount = (float) $this->input('paid_amount', 0);

            if ($paidAmount > $finalAmount + 0.01) {
                $validator->errors()->add('paid_amount', 'المبلغ المدفوع (' . number_format($paidAmount, 2) . ' ج.م) لا يمكن أن يتجاوز صافي الفاتورة الإجمالي (' . number_format($finalAmount, 2) . ' ج.م).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'supplier_id.required'             => 'يرجى اختيار المورد.',
            'supplier_id.exists'               => 'المورد المحدد غير موجود.',
            'invoice_number.required'          => 'رقم فاتورة المشتريات مطلوب.',
            'invoice_number.unique'            => 'رقم فاتورة المشتريات مسجل مسبقاً لنفس الشحنة أو مورد آخر.',
            'invoice_date.required'            => 'تاريخ الفاتورة مطلوب.',
            'items.required'                   => 'يجب إضافة صنف واحد على الأقل لفاتورة المشتريات.',
            'items.*.product_id.required'      => 'يرجى اختيار المنتج لكل بند.',
            'items.*.quantity.required'        => 'الكمية الموردة مطلوبة.',
            'items.*.quantity.min'             => 'الكمية يجب أن تكون 1 على الأقل.',
            'items.*.unit_cost_price.required' => 'سعر تكلفة الشراء للوحدة مطلوب.',
            'paid_amount.required'             => 'المبلغ المدفوع للمورد مطلوب (يمكن كتابة 0 في حالة الآجل بالكامل).',
            'payment_method.required'          => 'طريقة الدفع مطلوبة.',
        ];
    }
}
