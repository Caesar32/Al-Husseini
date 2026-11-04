<?php

namespace App\Http\Requests\Admin\Labels;

use App\Contracts\Catalog\BarcodeLabelServiceInterface;

class PrintPurchaseLabelsRequest extends PrintBarcodeLabelsRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('purchases.view');
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'items' => ['required', 'array'],
            'items.*' => ['required', 'integer', 'min:0', 'max:' . BarcodeLabelServiceInterface::MAX_LABELS],
        ]);
    }

    public function messages(): array
    {
        return [
            'items.required' => 'حدد ملصقاً واحداً على الأقل للطباعة.',
            'items.*.integer' => 'عدد الملصقات يجب أن يكون رقماً صحيحاً.',
            'items.*.min' => 'عدد الملصقات لا يمكن أن يكون سالباً.',
            'items.*.max' => 'الحد الأقصى ' . BarcodeLabelServiceInterface::MAX_LABELS . ' ملصق في أمر الطباعة الواحد.',
        ];
    }
}
