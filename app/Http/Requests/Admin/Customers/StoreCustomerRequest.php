<?php

namespace App\Http\Requests\Admin\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('customers.create');
    }

    public function rules(): array
    {
        return array_merge(self::customerRules(null, $this->canAdjustLimit()), [
            'vehicle'                => ['nullable', 'array'],
            'vehicle.plate_number'   => ['nullable', 'string', 'max:50'],
            'vehicle.car_brand'      => ['nullable', 'required_with:vehicle.plate_number', 'string', 'max:50'],
            'vehicle.car_model'      => ['nullable', 'required_with:vehicle.plate_number', 'string', 'max:50'],
            'vehicle.model_year'     => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'vehicle.chassis_number' => ['nullable', 'string', 'max:100'],
            'vehicle.notes'          => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * current_credit_balance is never accepted. credit_limit requires credit.adjust_limit;
     * without it the field is rejected (not silently ignored) and the database default applies on create.
     */
    public static function customerRules(?int $ignoreCustomerId, bool $canAdjustLimit): array
    {
        return [
            'name'         => ['required', 'string', 'max:150'],
            'phone'        => ['required', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($ignoreCustomerId)],
            'national_id'  => ['nullable', 'string', 'max:30'],
            'tier'         => ['required', Rule::in(['standard', 'vip', 'fleet'])],
            'is_active'    => ['sometimes', 'boolean'],
            'credit_limit' => $canAdjustLimit
                ? ['sometimes', 'numeric', 'min:0', 'max:99999999']
                : ['prohibited'],
        ];
    }

    protected function canAdjustLimit(): bool
    {
        return (bool) $this->user()?->can('credit.adjust_limit');
    }

    public function messages(): array
    {
        return self::sharedMessages();
    }

    public static function sharedMessages(): array
    {
        return [
            'name.required'                   => 'اسم العميل مطلوب.',
            'phone.required'                  => 'رقم الهاتف مطلوب.',
            'phone.unique'                    => 'رقم الهاتف مسجل لعميل آخر.',
            'tier.required'                   => 'يرجى اختيار تصنيف العميل.',
            'credit_limit.prohibited'         => 'تعديل سقف الائتمان يتطلب صلاحية تعديل سقف الائتمان.',
            'vehicle.car_brand.required_with' => 'ماركة السيارة مطلوبة عند إدخال رقم اللوحة.',
            'vehicle.car_model.required_with' => 'موديل السيارة مطلوب عند إدخال رقم اللوحة.',
        ];
    }
}
