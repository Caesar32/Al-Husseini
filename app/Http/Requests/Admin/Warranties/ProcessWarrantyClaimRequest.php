<?php

namespace App\Http\Requests\Admin\Warranties;

use App\Models\Product;
use App\Models\Warranty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProcessWarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'defective_serial'           => ['required', 'string', 'exists:warranties,serial_number'],
            'technician_id'              => ['required', 'exists:employees,id'],
            'battery_voltage_tested'     => ['required', 'numeric', 'min:0', 'max:25'],
            'cca_tested'                 => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'issue_description'          => ['required', 'string', 'min:5', 'max:1000'],
            'decision'                   => ['required', 'in:replaced,recharged,repaired,rejected'],

            // If replacement
            'replacement_product_id'     => ['nullable', 'required_if:decision,replaced', 'exists:products,id'],
            'replacement_battery_serial' => ['nullable', 'required_if:decision,replaced', 'string', 'max:100'],

            // If rejection
            'rejection_reason'           => ['nullable', 'required_if:decision,rejected', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $serial = trim((string) $this->input('defective_serial'));
            $warranty = Warranty::where('serial_number', $serial)->first();

            if ($warranty) {
                // Check if warranty is expired
                if ($warranty->end_date && $warranty->end_date->isPast()) {
                    $validator->errors()->add(
                        'defective_serial',
                        "فترة ضمان هذه البطارية انتهت بتاريخ {$warranty->end_date->format('Y-m-d')}."
                    );
                }

                // Check if status is voided
                if ($warranty->status === 'voided') {
                    $validator->errors()->add('defective_serial', 'شهادة الضمان هذه ملغاة أو باطلة مسبقاً.');
                }

                if ($warranty->status === 'claimed') {
                    $validator->errors()->add('defective_serial', 'تم صرف بديل مسبقاً لهذه البطارية؛ يرجى تقديم المطالبة على سيريال البطارية البديلة.');
                }
            }

            // If replacement is decided:
            if ($this->input('decision') === 'replaced') {
                $replacementSerial = trim((string) $this->input('replacement_battery_serial'));
                $replacementProductId = $this->input('replacement_product_id');

                if ($replacementSerial !== '') {
                    if ($replacementSerial === $serial) {
                        $validator->errors()->add('replacement_battery_serial', 'لا يمكن استخدام نفس سيريال البطارية المعيبة كبطارية بديلة.');
                    }

                    if (Warranty::where('serial_number', $replacementSerial)->exists()) {
                        $validator->errors()->add('replacement_battery_serial', 'سيريال البطارية البديلة مسجل في النظام مسبقاً ولديه ضمان قائم.');
                    }
                }

                if ($replacementProductId) {
                    $product = Product::find($replacementProductId);
                    if ($product && $product->current_stock < 1) {
                        $validator->errors()->add('replacement_product_id', "رصيد المخزون للبطارية البديلة ({$product->name}) نافد حالياً (الرصيد: 0).");
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'defective_serial.required'           => 'سيريال البطارية التالفة مطلوب.',
            'defective_serial.exists'             => 'سيريال البطارية غير مسجل في شهادات الضمان الصادرة.',
            'technician_id.required'              => 'يرجى تحديد الفني الذي قام بالفحص.',
            'battery_voltage_tested.required'     => 'قراءة الفولتية الناتجة عن جهاز الفحص مطلوبة.',
            'issue_description.required'          => 'وصف العطل وتقرير الفحص الفني مطلوب.',
            'decision.required'                   => 'يرجى تحديد القرار الفني النهائي للضمان.',
            'replacement_product_id.required_if'  => 'يرجى اختيار الصنف البديل الممنوح للعميل.',
            'replacement_battery_serial.required_if' => 'سيريال البطارية الجديدة البديلة مطلوب.',
            'rejection_reason.required_if'        => 'سبب رفض الضمان مطلوب لتوضيحه للعميل.',
        ];
    }
}
