<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id ?? $this->input('employee_id');

        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'job_title_id' => ['required', 'exists:job_titles,id'],
            'employee_code' => ['required', 'string', 'max:30', Rule::unique('employees', 'employee_code')->ignore($employeeId)],
            'full_name' => ['required', 'string', 'max:150'],
            'national_id' => ['required', 'string', 'size:14', Rule::unique('employees', 'national_id')->ignore($employeeId)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('employees', 'phone')->ignore($employeeId)],
            'hire_date' => ['required', 'date'],
            'shift_start_time' => ['required'],
            'shift_end_time' => ['required'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'zkteco_pin' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'zkteco_pin')->ignore($employeeId)],
            'status' => ['required', Rule::in(['active', 'on_leave', 'terminated'])],
            'basic_salary' => ['nullable', 'numeric', 'min:500'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
