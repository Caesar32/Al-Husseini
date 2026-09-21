<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
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
            'basic_salary' => ['required', 'numeric', 'min:500'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'يرجى اختيار الفرع التابع له الموظف.',
            'job_title_id.required' => 'يرجى تحديد المسمى الوظيفي.',
            'employee_code.required' => 'كود الموظف مطلوب.',
            'employee_code.unique' => 'كود الموظف مسجل مسبقاً.',
            'full_name.required' => 'الاسم الكامل للموظف مطلوب.',
            'national_id.size' => 'الرقم القومي يجب أن يتكون من 14 رقماً بالضبط.',
            'national_id.unique' => 'الرقم القومي مسجل لموظف آخر.',
            'phone.unique' => 'رقم الهاتف مسجل لموظف آخر.',
            'shift_end_time.after' => 'وقت نهاية الشفت يجب أن يكون بعد وقت البداية.',
            'basic_salary.required' => 'الراتب الأساسي مطلوب.',
        ];
    }
}
