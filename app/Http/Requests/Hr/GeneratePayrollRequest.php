<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'year' => ['required', 'integer', 'min:2024', 'max:2035'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'يرجى اختيار الفرع المراد احتساب رواتبه.',
            'year.required' => 'سنة المسير مطلوبة.',
            'month.required' => 'شهر المسير مطلوب.',
        ];
    }
}
