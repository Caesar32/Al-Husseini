<?php

namespace App\Http\Requests\Admin\Scrap;

use App\Models\ScrapBatteriesInventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScrapSaleBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buyer_name'              => ['required', 'string', 'max:150'],
            'buyer_phone'             => ['nullable', 'string', 'max:30'],
            'total_amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_method'          => ['required', 'in:cash,bank_transfer,cheque'],
            'scrap_battery_ids'       => ['required', 'array', 'min:1'],
            'scrap_battery_ids.*'     => ['required', 'integer'],
            'notes'                   => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $ids = $this->input('scrap_battery_ids', []);
            if (!is_array($ids) || empty($ids)) {
                return;
            }

            // Verify all selected scrap batteries are currently 'in_stock'
            $invalidCount = ScrapBatteriesInventory::whereIn('id', $ids)
                ->where('status', '!=', 'in_stock')
                ->count();

            if ($invalidCount > 0) {
                $validator->errors()->add(
                    'scrap_battery_ids',
                    "يوجد {$invalidCount} بطارية كهنة محددة تم بيعها أو تخريدها مسبقاً وغير متاحة للبيع في هذه الشحنة."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'buyer_name.required'         => 'اسم المشتري أو مصنع تدوير الرصاص مطلوب.',
            'total_amount.required'       => 'إجمالي قيمة بيع الشحنة مطلوب.',
            'payment_method.required'     => 'طريقة الدفع والتسوية مطلوبة.',
            'scrap_battery_ids.required'  => 'يرجى تحديد بطاريات الكهنة المراد بيعها من رصيد المخزن.',
            'scrap_battery_ids.min'       => 'يجب تحديد بطارية كهنة واحدة على الأقل لإتمام البيع.',
        ];
    }
}
