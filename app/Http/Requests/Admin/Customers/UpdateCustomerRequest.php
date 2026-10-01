<?php

namespace App\Http\Requests\Admin\Customers;

use App\Services\Sales\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('customers.edit');
    }

    public function rules(): array
    {
        return StoreCustomerRequest::customerRules(
            $this->route('customer')?->id,
            (bool) $this->user()?->can('credit.adjust_limit')
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $customer = $this->route('customer');

            // The walk-in account is looked up by this phone by the POS; changing it would orphan it.
            if ($customer && $customer->phone === CustomerService::WALK_IN_PHONE
                && $this->input('phone') !== CustomerService::WALK_IN_PHONE) {
                $validator->errors()->add('phone', 'لا يمكن تغيير رقم هاتف حساب العميل النقدي العابر.');
            }
        });
    }

    public function messages(): array
    {
        return StoreCustomerRequest::sharedMessages();
    }
}
