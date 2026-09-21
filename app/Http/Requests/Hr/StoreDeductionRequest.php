<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'deduction_rule_id' => ['nullable', 'exists:deduction_rules,id'],
            'deduction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى اختيار الموظف المستهدف.',
            'amount.required' => 'مبلغ الجزاء مطلوب.',
            'amount.min' => 'أقل قيمة للجزاء هي 1 ج.م.',
            'reason.required' => 'يرجى كتابة سبب الجزاء.',
        ];
    }
}
