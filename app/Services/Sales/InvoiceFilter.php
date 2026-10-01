<?php

namespace App\Services\Sales;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class InvoiceFilter
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?int $branch_id = null,
        public ?string $date_from = null,
        public ?string $date_to = null,
        public ?string $payment_method = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $search = isset($data['search']) ? trim((string) $data['search']) : null;
        $search = $search === '' ? null : $search;

        $status = isset($data['status']) ? trim((string) $data['status']) : null;
        $status = $status === '' ? null : $status;

        $branchId = isset($data['branch_id']) && $data['branch_id'] !== '' ? (int) $data['branch_id'] : null;

        $dateFrom = isset($data['date_from']) ? trim((string) $data['date_from']) : null;
        $dateFrom = $dateFrom === '' ? null : $dateFrom;

        $dateTo = isset($data['date_to']) ? trim((string) $data['date_to']) : null;
        $dateTo = $dateTo === '' ? null : $dateTo;

        // Validate date_from <= date_to
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            throw ValidationException::withMessages([
                'date_from' => ['تاريخ البداية لا يمكن أن يكون بعد تاريخ النهاية.'],
                'date_to' => ['تاريخ النهاية لا يمكن أن يكون قبل تاريخ البداية.'],
            ]);
        }

        $paymentMethod = isset($data['payment_method']) ? trim((string) $data['payment_method']) : null;
        $paymentMethod = $paymentMethod === '' ? null : $paymentMethod;

        return new self(
            search: $search,
            status: $status,
            branch_id: $branchId,
            date_from: $dateFrom,
            date_to: $dateTo,
            payment_method: $paymentMethod,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
            'branch_id' => $this->branch_id,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'payment_method' => $this->payment_method,
        ], fn($v) => $v !== null && $v !== '');
    }

    /**
     * Apply filters to an Invoice query.
     * Uses whereBetween with 00:00:00 / 23:59:59 to utilize INDEX(branch_id, created_at).
     */
    public function apply(Builder $query): Builder
    {
        if ($this->search !== null) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  })
                  // Extended search: product name, sku, barcode, battery serial (FIN-H02)
                  ->orWhereHas('items.product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('sku', 'like', "%{$search}%")
                         ->orWhere('barcode', 'like', "%{$search}%");
                  })
                  ->orWhereHas('items', function ($iq) use ($search) {
                      $iq->where('battery_serial_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->branch_id !== null) {
            $query->where('branch_id', $this->branch_id);
        }

        if ($this->date_from !== null) {
            $query->where('created_at', '>=', $this->date_from . ' 00:00:00');
        }

        if ($this->date_to !== null) {
            $query->where('created_at', '<=', $this->date_to . ' 23:59:59');
        }

        if ($this->payment_method !== null) {
            $query->where('payment_method', $this->payment_method);
        }

        return $query;
    }

    /**
     * Apply and, without an explicit status filter, keep only invoices that count as sales
     * (cancelled and fully refunded excluded; partially refunded kept and netted by the caller).
     */
    public function applyForStats(Builder $query): Builder
    {
        $this->apply($query);
        if ($this->status === null) {
            $query->countable();
        }
        return $query;
    }
}
