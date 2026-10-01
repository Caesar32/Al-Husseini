<?php

namespace App\Http\Requests\Admin\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('products.create');
    }

    public function rules(): array
    {
        return array_merge(self::sharedRules(null), [
            // Initial cost only; afterwards cost_price is the weighted average maintained by purchases.
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);
    }

    /**
     * Rules shared by create and update. current_stock is intentionally not accepted.
     */
    public static function sharedRules(?int $ignoreProductId): array
    {
        return [
            'category_id'       => ['required', 'integer', Rule::exists('categories', 'id')],
            'sku'               => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($ignoreProductId)],
            'barcode'           => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($ignoreProductId)],
            'name'              => ['required', 'string', 'max:200'],
            'brand'             => ['required', 'string', 'max:100'],
            'capacity_ah'       => ['nullable', 'string', 'max:20'],
            'voltage'           => ['nullable', 'string', 'max:20'],
            'terminal_type'     => ['nullable', Rule::in(['regular', 'reverse', 'side'])],
            'warranty_months'   => ['required', 'integer', 'min:0', 'max:120'],
            'retail_price'      => ['required', 'numeric', 'min:0', 'max:9999999'],
            'wholesale_price'   => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'reorder_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_battery'        => ['required', 'boolean'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return self::sharedMessages();
    }

    public static function sharedMessages(): array
    {
        return [
            'category_id.required'   => 'يرجى اختيار قسم الصنف.',
            'category_id.exists'     => 'القسم المختار غير موجود.',
            'sku.required'           => 'كود الصنف (SKU) مطلوب.',
            'sku.unique'             => 'كود الصنف (SKU) مستخدم لصنف آخر.',
            'barcode.unique'         => 'الباركود مستخدم لصنف آخر.',
            'name.required'          => 'اسم الصنف مطلوب.',
            'brand.required'         => 'الماركة مطلوبة.',
            'warranty_months.required' => 'مدة الضمان مطلوبة (0 إن لم يوجد ضمان).',
            'retail_price.required'  => 'سعر البيع مطلوب.',
            'retail_price.min'       => 'سعر البيع لا يمكن أن يكون سالباً.',
        ];
    }
}
