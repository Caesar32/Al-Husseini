<?php

namespace App\Http\Requests\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('customers.edit');
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'plate_number'   => [
                'required', 'string', 'max:50',
                Rule::unique('customer_vehicles', 'plate_number')
                    ->where('customer_id', $customerId)
                    ->ignore($this->route('vehicle')?->id),
            ],
            'car_brand'      => ['required', 'string', 'max:50'],
            'car_model'      => ['required', 'string', 'max:50'],
            'model_year'     => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'chassis_number' => ['nullable', 'string', 'max:100'],
            'last_odometer'  => ['nullable', 'integer', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate_number.required' => 'رقم اللوحة مطلوب.',
            'plate_number.unique'   => 'هذه اللوحة مسجلة بالفعل لنفس العميل.',
            'car_brand.required'    => 'ماركة السيارة مطلوبة.',
            'car_model.required'    => 'موديل السيارة مطلوب.',
        ];
    }
}
