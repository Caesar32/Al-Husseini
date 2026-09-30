<?php

namespace App\Http\Requests\Admin\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('suppliers.create');
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:150'],
            'company_name'        => ['required', 'string', 'max:150'],
            'phone'               => ['required', 'string', 'max:30', 'unique:suppliers,phone'],
            'alt_phone'           => ['nullable', 'string', 'max:30'],
            'email'               => ['nullable', 'email', 'max:100'],
            'tax_number'          => ['nullable', 'string', 'max:50'],
            'commercial_register' => ['nullable', 'string', 'max:50'],
            'address'             => ['nullable', 'string', 'max:255'],
            'credit_limit'        => ['required', 'numeric', 'min:0'],
            'is_active'           => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'اسم مسؤول المورد مطلوب.',
            'company_name.required' => 'اسم شركة التوريد مطلوب.',
            'phone.required'        => 'رقم هاتف المورد مطلوب.',
            'phone.unique'          => 'رقم الهاتف مسجل لمورد آخر بالفعل.',
            'credit_limit.required' => 'يرجى تحديد سقف المديونية / الائتمان للمورد.',
            'credit_limit.min'      => 'سقف الائتمان لا يمكن أن يكون بالسالب.',
        ];
    }
}
