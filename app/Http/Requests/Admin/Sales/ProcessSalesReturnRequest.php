<?php

namespace App\Http\Requests\Admin\Sales;

use Illuminate\Foundation\Http\FormRequest;

class ProcessSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoices.cancel') ?? false;
    }

    /**
     * The return modal posts one row per invoice line; an unchecked checkbox sends only the
     * quantity field. Drop rows that identify no line so they are not validated or returned.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $this->merge([
                'items' => array_values(array_filter(
                    $items,
                    fn ($row) => is_array($row) && (!empty($row['invoice_item_id']) || !empty($row['product_id']))
                )),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reason'                  => ['required', 'string', 'max:500'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'items.*.product_id'      => ['nullable', 'required_without:items.*.invoice_item_id', 'integer', 'exists:products,id'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'سبب المرتجع مطلوب.',
            'items.required'  => 'يرجى تحديد الأصناف المراد إرجاعها.',
            'items.min'       => 'يرجى تحديد صنف واحد على الأقل للإرجاع.',
        ];
    }
}
