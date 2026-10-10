<?php

namespace App\Services\Sales;

use App\Contracts\Sales\CustomerServiceInterface;
use App\Models\Customer;
use App\Models\CustomerVehicle;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerService implements CustomerServiceInterface
{
    /**
     * Phone of the shared walk-in customer that PosOrderService attaches walk-in battery
     * warranties to (PosOrderService::processPosSale, firstOrCreate by this phone).
     */
    public const WALK_IN_PHONE = '00000000000';

    /** Invoice statuses that do not count as purchases. */
    private const NON_PURCHASE_STATUSES = ['cancelled', 'refunded'];

    /**
     * current_credit_balance is never accepted from input: it is moved only by credit
     * sales, returns and collections. credit_limit is included only when the caller
     * (controller/FormRequest) has checked credit.adjust_limit.
     */
    private const CUSTOMER_FIELDS = ['name', 'phone', 'national_id', 'tier', 'is_active', 'credit_limit'];

    private const VEHICLE_FIELDS = ['plate_number', 'car_brand', 'car_model', 'model_year', 'chassis_number', 'last_odometer', 'notes'];

    public function getPaginatedCustomers(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Customer::with(['vehicles:id,customer_id,plate_number,car_brand,car_model,model_year'])
            ->withCount(['invoices as purchases_count' => fn($q) => $q->whereNotIn('status', self::NON_PURCHASE_STATUSES)])
            ->withSum(['invoices as purchases_total' => fn($q) => $q->whereNotIn('status', self::NON_PURCHASE_STATUSES)], 'final_amount');

        if (!empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('national_id', 'like', "%{$term}%")
                  ->orWhereHas('vehicles', function ($vq) use ($term) {
                      $vq->where('plate_number', 'like', "%{$term}%")
                         ->orWhere('car_brand', 'like', "%{$term}%")
                         ->orWhere('car_model', 'like', "%{$term}%");
                  });
            });
        }

        if (!empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function getCustomerStats(): array
    {
        return [
            'total'           => Customer::count(),
            'in_debt_count'   => Customer::inDebt()->count(),
            'in_debt_total'   => round((float) Customer::inDebt()->sum('current_credit_balance'), 2),
            'standard_count'  => Customer::where('tier', 'standard')->count(),
            'commercial_count'=> Customer::whereIn('tier', ['vip', 'fleet'])->count(),
        ];
    }

    public function search(string $term, int $limit = 20): Collection
    {
        $term = trim($term);

        return Customer::with('vehicles:id,customer_id,plate_number,car_brand,car_model,model_year')
            ->where('is_active', true)
            ->where('phone', '!=', self::WALK_IN_PHONE)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%")
                      ->orWhereHas('vehicles', fn($vq) => $vq->where('plate_number', 'like', "%{$term}%"));
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function getCustomerProfile(Customer $customer): array
    {
        $customer->load('vehicles');

        $invoices = $customer->invoices()
            ->with(['items.product:id,name,is_battery', 'items.warranty:id,invoice_item_id,serial_number,end_date,status'])
            ->latest('id')
            ->limit(20)
            ->get(['id', 'invoice_number', 'customer_id', 'final_amount', 'paid_amount', 'remaining_amount', 'status', 'created_at']);

        $warranties = $customer->warranties()
            ->with('invoiceItem.product:id,name')
            ->latest('id')
            ->limit(20)
            ->get(['id', 'invoice_item_id', 'customer_id', 'serial_number', 'start_date', 'end_date', 'status']);

        return [
            'customer'   => $customer,
            'invoices'   => $invoices,
            'warranties' => $warranties,
        ];
    }

    public function createCustomer(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create($this->only($data, self::CUSTOMER_FIELDS));

            $vehicle = $data['vehicle'] ?? null;
            if (is_array($vehicle) && !empty($vehicle['plate_number'])) {
                $customer->vehicles()->create($this->only($vehicle, self::VEHICLE_FIELDS));
            }

            return $customer->fresh('vehicles');
        });
    }

    public function updateCustomer(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer->update($this->only($data, self::CUSTOMER_FIELDS));

            return $customer->fresh('vehicles');
        });
    }

    public function deleteCustomer(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($customer->phone === self::WALK_IN_PHONE) {
                throw new DomainException('لا يمكن حذف حساب العميل النقدي العابر لأنه مستخدم في ضمانات مبيعات نقطة البيع.');
            }

            $eps = (float) config('finance.epsilon', 0.01);
            if ((float) $customer->current_credit_balance > $eps) {
                throw new DomainException('لا يمكن حذف عميل عليه رصيد آجل مستحق (' . number_format((float) $customer->current_credit_balance, 2) . ' ج.م).');
            }

            $customer->delete();
        });
    }

    public function addVehicle(Customer $customer, array $data): CustomerVehicle
    {
        return $customer->vehicles()->create($this->only($data, self::VEHICLE_FIELDS));
    }

    public function updateVehicle(CustomerVehicle $vehicle, array $data): CustomerVehicle
    {
        $vehicle->update($this->only($data, self::VEHICLE_FIELDS));

        return $vehicle->fresh();
    }

    public function deleteVehicle(CustomerVehicle $vehicle): void
    {
        if ($vehicle->invoices()->exists() || $vehicle->warranties()->exists()) {
            throw new DomainException('لا يمكن حذف مركبة مرتبطة بفواتير أو ضمانات سابقة.');
        }

        $vehicle->delete();
    }

    private function only(array $data, array $fields): array
    {
        return array_intersect_key($data, array_flip($fields));
    }
}
