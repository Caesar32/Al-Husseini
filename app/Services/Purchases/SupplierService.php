<?php

namespace App\Services\Purchases;

use App\Contracts\Purchases\SupplierServiceInterface;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierService implements SupplierServiceInterface
{
    public function getPaginatedSuppliers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Supplier::query()->with(['products']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('tax_number', 'like', "%{$search}%")
                  ->orWhere('commercial_register', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function getAllActiveSuppliers(): Collection
    {
        return Supplier::where('is_active', true)
            ->orderBy('company_name')
            ->get();
    }

    public function createSupplier(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::create([
                'name'                => $data['name'],
                'company_name'        => $data['company_name'],
                'phone'               => $data['phone'],
                'alt_phone'           => $data['alt_phone'] ?? null,
                'email'               => $data['email'] ?? null,
                'tax_number'          => $data['tax_number'] ?? null,
                'commercial_register' => $data['commercial_register'] ?? null,
                'address'             => $data['address'] ?? null,
                'credit_limit'        => $data['credit_limit'] ?? 0,
                'current_balance'     => 0,
                'is_active'           => $data['is_active'] ?? true,
            ]);

            if (!empty($data['products']) && is_array($data['products'])) {
                $this->syncSupplierProducts($supplier->id, $data['products']);
            }

            return $supplier;
        });
    }

    public function updateSupplier(int $supplierId, array $data): Supplier
    {
        return DB::transaction(function () use ($supplierId, $data) {
            $supplier = Supplier::findOrFail($supplierId);
            $supplier->update($data);

            if (isset($data['products']) && is_array($data['products'])) {
                $this->syncSupplierProducts($supplier->id, $data['products']);
            }

            return $supplier->fresh(['products']);
        });
    }

    public function deleteSupplier(int $supplierId): bool
    {
        $supplier = Supplier::findOrFail($supplierId);

        if ($supplier->purchaseInvoices()->exists()) {
            throw new \DomainException('لا يمكن حذف مورد مسجل له فواتير مشتريات. يمكنك إلغاء تفعيله بدلاً من ذلك.');
        }

        // نتحقق من الرصيد باستخدام abs() لتجنب مشاكل الـ floating-point precision
        if (abs((float) $supplier->current_balance) > 0.01) {
            throw new \DomainException('لا يمكن حذف مورد له رصيد دائن قائم (' . number_format($supplier->current_balance, 2) . ' ج.م). يرجى تسوية الحساب أولاً أو إلغاء تفعيل المورد.');
        }

        return $supplier->delete();
    }

    public function syncSupplierProducts(int $supplierId, array $products): void
    {
        $supplier = Supplier::findOrFail($supplierId);
        $syncData = [];

        foreach ($products as $item) {
            $productId = $item['product_id'] ?? null;
            if ($productId) {
                $syncData[$productId] = [
                    'supplier_sku'        => $item['supplier_sku'] ?? null,
                    'last_purchase_price' => $item['last_purchase_price'] ?? 0,
                    'min_order_qty'       => max(1, (int) ($item['min_order_qty'] ?? 1)),
                    'lead_time_days'      => max(0, (int) ($item['lead_time_days'] ?? 1)),
                    'is_primary_supplier' => (bool) ($item['is_primary_supplier'] ?? false),
                    'notes'               => $item['notes'] ?? null,
                ];
            }
        }

        $supplier->products()->sync($syncData);
    }

    public function getSupplierStatistics(int $supplierId): array
    {
        // نجلب المورد فقط بدون eager load لـ ledgerEntries لأنها لا تُستخدم هنا
        $supplier = Supplier::findOrFail($supplierId);

        $totalInvoicesCount = $supplier->purchaseInvoices()->count();
        $totalPurchases = (float) $supplier->purchaseInvoices()->sum('final_amount');
        $totalPaid = (float) $supplier->purchaseInvoices()->sum('paid_amount');
        $unpaidInvoicesCount = $supplier->purchaseInvoices()->where('payment_status', '!=', 'paid')->count();

        return [
            'supplier'              => $supplier,
            'current_balance'       => (float) $supplier->current_balance,
            'credit_limit'          => (float) $supplier->credit_limit,
            'total_invoices_count'  => $totalInvoicesCount,
            'unpaid_invoices_count' => $unpaidInvoicesCount,
            'total_purchases'       => $totalPurchases,
            'total_paid'            => $totalPaid,
            'remaining_balance'     => (float) $supplier->current_balance,
        ];
    }
}
