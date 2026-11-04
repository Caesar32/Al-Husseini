<?php

namespace App\Http\Requests\Admin\Labels;

use App\Enums\BarcodeLabelSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class PrintBarcodeLabelsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'size' => ['nullable', Rule::enum(BarcodeLabelSize::class)],
            'show_price' => ['nullable', 'boolean'],
        ];
    }

    public function labelSize(): BarcodeLabelSize
    {
        return BarcodeLabelSize::tryFrom((string) $this->input('size')) ?? BarcodeLabelSize::Medium;
    }

    public function showPrice(): bool
    {
        return $this->boolean('show_price');
    }
}
