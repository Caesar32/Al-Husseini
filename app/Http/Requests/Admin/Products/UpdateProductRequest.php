<?php

namespace App\Http\Requests\Admin\Products;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('products.edit');
    }

    public function rules(): array
    {
        $rules = StoreProductRequest::sharedRules($this->route('product')?->id);
        $rules['is_active'] = ['required', 'boolean'];

        return $rules;
    }

    public function messages(): array
    {
        return StoreProductRequest::sharedMessages();
    }
}
