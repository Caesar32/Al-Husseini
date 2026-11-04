<?php

namespace App\Http\Requests\Admin\Labels;

use App\Contracts\Catalog\BarcodeLabelServiceInterface;

class PrintProductLabelsRequest extends PrintBarcodeLabelsRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('products.view');
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'copies' => ['required', 'integer', 'min:1', 'max:' . BarcodeLabelServiceInterface::MAX_LABELS],
        ]);
    }

    public function messages(): array
    {
        return [
            'copies.required' => 'حدد عدد الملصقات المطلوب طباعتها.',
            'copies.integer' => 'عدد الملصقات يجب أن يكون رقماً صحيحاً.',
            'copies.min' => 'حدد ملصقاً واحداً على الأقل للطباعة.',
            'copies.max' => 'الحد الأقصى ' . BarcodeLabelServiceInterface::MAX_LABELS . ' ملصق في أمر الطباعة الواحد.',
        ];
    }
}
