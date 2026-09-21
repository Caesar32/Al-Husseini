<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type' => ['required', 'in:annual,sick,emergency,unpaid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'يرجى تحديد الموظف.',
            'leave_type.required' => 'نوع الإجازة مطلوب.',
            'start_date.required' => 'تاريخ بداية الإجازة مطلوب.',
            'end_date.after_or_equal' => 'تاريخ نهاية الإجازة يجب أن يكون مساوياً أو بعد تاريخ البداية.',
        ];
    }
}
