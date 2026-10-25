<?php

namespace App\Http\Requests\Admin\Sales;

use App\Models\Invoice;
use App\Rules\ValidQuantityRule;
use App\Support\Quantity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'items.*.quantity'        => ['required', new ValidQuantityRule()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $invoice = $this->route('invoice');
            if (! $invoice instanceof Invoice) {
                $invoice = Invoice::with('items.product')->find($invoice);
            } else {
                $invoice->loadMissing('items.product');
            }

            if (! $invoice) {
                return;
            }

            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $quantity = $row['quantity'] ?? null;
                if (Quantity::validationError($quantity) !== null) {
                    continue; // reported by ValidQuantityRule
                }

                $line = ! empty($row['invoice_item_id'])
                    ? $invoice->items->firstWhere('id', (int) $row['invoice_item_id'])
                    : $invoice->items->firstWhere('product_id', (int) ($row['product_id'] ?? 0));

                $product = $line?->product;
                $pieceError = $product ? Quantity::validationError($quantity, ! $product->allowsFraction(), $product->name) : null;
                if ($pieceError !== null) {
                    $validator->errors()->add("items.{$index}.quantity", $pieceError);
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'سبب المرتجع مطلوب.',
            'items.required'  => 'يرجى تحديد الأصناف المراد إرجاعها.',
            'items.min'       => 'يرجى تحديد صنف واحد على الأقل للإرجاع.',
            'items.*.quantity.numeric' => 'كمية المرتجع يجب أن تكون رقماً.',
            'items.*.quantity.gt' => 'كمية المرتجع يجب أن تكون أكبر من صفر.',
        ];
    }
}
