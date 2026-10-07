<?php

namespace App\Http\Resources\Owner;

use App\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Note: national_id is strictly withheld for privacy and surveillance scope.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $balance = (float) $this->current_credit_balance;
        $limit = (float) ($this->credit_limit ?? 0);
        $compactInfo = MoneyHelper::formatCompactCurrency($balance);

        $utilization = $limit > 0 ? round(($balance / $limit) * 100, 1) : 0;

        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'phone'                  => $this->phone,
            'current_credit_balance' => [
                'raw'     => $balance,
                'compact' => $compactInfo['compact'],
                'exact'   => $compactInfo['exact'],
            ],
            'credit_limit'           => [
                'raw'       => $limit,
                'formatted' => number_format($limit, 2) . ' ج.م',
            ],
            'utilization_rate'       => $limit > 0 ? "{$utilization}%" : null,
        ];
    }
}
