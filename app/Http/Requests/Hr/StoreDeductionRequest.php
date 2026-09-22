<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('deductions.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id'       => [
                'required',
                // يجب أن يكون الموظف موجوداً وفي حالة active فقط
                Rule::exists('employees', 'id')->where('status', 'active'),
            ],
            'deduction_rule_id' => ['nullable', 'exists:deduction_rules,id'],
            'deduction_date'    => ['required', 'date', 'before_or_equal:today'],
            'amount'            => ['required', 'numeric', 'min:1'],
            'reason'            => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى اختيار الموظف المستهدف.',
            'employee_id.exists'   => 'الموظف المختار غير موجود أو غير نشط — لا يمكن تطبيق خصم على موظف منتهية خدمته.',
            'deduction_date.before_or_equal' => 'تاريخ الجزاء لا يمكن أن يكون في المستقبل.',
            'amount.required'      => 'مبلغ الجزاء مطلوب.',
            'amount.min'           => 'أقل قيمة للجزاء هي 1 ج.م.',
            'reason.required'      => 'يرجى كتابة سبب الجزاء.',
        ];
    }
}
