<?php

namespace App\Services\Sales;

use App\Contracts\Sales\WarrantyServiceInterface;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WarrantyService implements WarrantyServiceInterface
{
    public function getPaginatedClaims(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = WarrantyClaim::query()
            ->with([
                'warranty.customer:id,name,phone',
                'warranty.customerVehicle:id,car_brand,car_model,plate_number',
                'replacementProduct:id,name,brand',
                'supplier:id,name,company_name',
                'technician:id,full_name',
                'receivedByUser:id,name',
                'settledByUser:id,name',
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%")
                  ->orWhere('defective_battery_serial', 'like', "%{$search}%")
                  ->orWhere('replacement_battery_serial', 'like', "%{$search}%")
                  ->orWhereHas('warranty.customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['decision'])) {
            $query->where('decision', $filters['decision']);
        }

        if (!empty($filters['supplier_resolution'])) {
            $query->where('supplier_resolution', $filters['supplier_resolution']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        return $query->latest('id')->paginate($perPage)->withQueryString();
    }

    public function verifyBatterySerial(string $serialNumber): array
    {
        $warranty = Warranty::where('serial_number', trim($serialNumber))
            ->with([
                'customer:id,name,phone',
                'customerVehicle:id,car_brand,car_model,plate_number',
                'invoiceItem.product:id,name,brand,capacity_ah',
                'invoiceItem.invoice:id,invoice_number,created_at,branch_id',
                'claims',
            ])
            ->first();

        if (!$warranty) {
            return [
                'exists'       => false,
                'is_valid'     => false,
                'message'      => 'سيريال البطارية غير مسجل في منظومة الضمان الإلكتروني.',
                'warranty'     => null,
            ];
        }

        $isExpired = $warranty->end_date && $warranty->end_date->isPast();
        $isVoided = $warranty->status === 'voided';
        $isClaimed = $warranty->status === 'claimed';
        $isValid = !$isExpired && !$isVoided && !$isClaimed;

        $daysRemaining = $warranty->end_date ? (int) now()->diffInDays($warranty->end_date, false) : 0;

        return [
            'exists'         => true,
            'is_valid'       => $isValid,
            'is_expired'     => $isExpired,
            'is_voided'      => $isVoided,
            'is_claimed'     => $isClaimed,
            'days_remaining' => max(0, $daysRemaining),
            'warranty'       => $warranty,
            'message'        => $isValid
                ? 'شهادة الضمان سارية ومعتمدة.'
                : ($isExpired ? 'شهادة الضمان منتهية الصلاحية.' : 'شهادة الضمان تم صرف استبدال مسبق لها أو ملغاة.'),
        ];
    }

    public function issueWarranty(int $invoiceItemId, int $customerId, ?int $vehicleId, string $serial, int $months): Warranty
    {
        return Warranty::create([
            'invoice_item_id'     => $invoiceItemId,
            'customer_id'         => $customerId,
            'customer_vehicle_id' => $vehicleId,
            'serial_number'       => trim($serial),
            'start_date'          => now()->toDateString(),
            'end_date'            => now()->addMonths($months)->toDateString(),
            'status'              => 'active',
        ]);
    }

    public function processInstantClaim(array $data, int $receivedByUserId): WarrantyClaim
    {
        return DB::transaction(function () use ($data, $receivedByUserId) {
            $defectiveSerial = trim($data['defective_serial']);
            $warranty = Warranty::where('serial_number', $defectiveSerial)->lockForUpdate()->firstOrFail();

            if ($warranty->end_date && $warranty->end_date->isPast()) {
                throw new \DomainException('لا يمكن قبول مطالبة ضمان لبطارية انتهت فترة صلاحيتها.');
            }

            if ($warranty->status === 'voided') {
                throw new \DomainException('شهادة الضمان هذه ملغاة.');
            }

            $decision = $data['decision'] ?? 'replaced';
            $replacementProductId = $data['replacement_product_id'] ?? null;
            $replacementSerial = !empty($data['replacement_battery_serial']) ? trim($data['replacement_battery_serial']) : null;
            $supplierId = null;

            // 1. If replacement was approved
            if ($decision === 'replaced') {
                if (!$replacementProductId || !$replacementSerial) {
                    throw new \InvalidArgumentException('بيانات البطارية البديلة (الصنف والسيريال) مطلوبة لإتمام الاستبدال الفوري.');
                }

                $replacementProduct = Product::where('id', $replacementProductId)->lockForUpdate()->firstOrFail();
                if ($replacementProduct->current_stock < 1) {
                    throw new \DomainException("رصيد المخزون للبطارية البديلة ({$replacementProduct->name}) غير كافٍ.");
                }

                // Decrement 1 piece for instant customer replacement
                $replacementProduct->decrement('current_stock', 1);

                // Mark defective warranty as claimed
                $warranty->update(['status' => 'claimed']);

                // Issue new warranty for the new replacement battery given to customer
                Warranty::create([
                    'invoice_item_id'     => $warranty->invoice_item_id,
                    'customer_id'         => $warranty->customer_id,
                    'customer_vehicle_id' => $warranty->customer_vehicle_id,
                    'serial_number'       => $replacementSerial,
                    'start_date'          => now()->toDateString(),
                    'end_date'            => now()->addMonths($replacementProduct->warranty_months ?? 12)->toDateString(),
                    'status'              => 'active',
                    'notes'               => "بديل معتمد لتذكرة الضمان للبطارية ({$defectiveSerial})",
                ]);

                // Identify Supplier to follow up defective unit return
                $primarySupplier = $replacementProduct->primarySupplier()->first();
                $supplierId = $primarySupplier?->id;
            }

            // 2. Create Claim Ticket (Observer will auto-generate claim_number CLM-YYYYMM-XXXX)
            $claim = WarrantyClaim::create([
                'warranty_id'                => $warranty->id,
                'customer_id'                => $warranty->customer_id,
                'branch_id'                  => $data['branch_id'] ?? auth()->user()?->branch_id ?? 1,
                'defective_battery_serial'   => $defectiveSerial,
                'replacement_product_id'     => $replacementProductId,
                'replacement_battery_serial' => $replacementSerial,
                'supplier_id'                => $supplierId,
                'technician_id'              => $data['technician_id'],
                'claim_date'                 => now()->toDateString(),
                'battery_voltage_tested'     => $data['battery_voltage_tested'],
                'cca_tested'                 => $data['cca_tested'] ?? null,
                'issue_description'          => $data['issue_description'],
                'decision'                   => $decision,
                'supplier_resolution'        => 'pending',
                'received_by_user_id'        => $receivedByUserId,
                'received_at'                => now(),
            ]);

            return $claim->fresh([
                'warranty.customer',
                'replacementProduct',
                'technician',
                'supplier',
            ]);
        });
    }

    public function settleClaimWithSupplier(int $claimId, string $action, array $resolutionData, int $userId): WarrantyClaim
    {
        return DB::transaction(function () use ($claimId, $action, $resolutionData, $userId) {
            $claim = WarrantyClaim::with(['replacementProduct', 'supplier'])->lockForUpdate()->findOrFail($claimId);

            if (!in_array($action, ['sent_to_supplier', 'settled_replacement', 'settled_credit_note', 'rejected'], true)) {
                throw new \InvalidArgumentException('إجراء تسوية المورد غير صالح.');
            }

            // 1. If supplier sent replacement battery: replenish stock
            if ($action === 'settled_replacement') {
                if ($claim->replacement_product_id) {
                    $product = Product::where('id', $claim->replacement_product_id)->lockForUpdate()->first();
                    $product?->increment('current_stock', 1);
                }
            }

            // 2. If supplier settled via credit note (إشعار خصم دائن في كشف الحساب)
            if ($action === 'settled_credit_note' && $claim->supplier_id) {
                $supplier = Supplier::where('id', $claim->supplier_id)->lockForUpdate()->firstOrFail();
                $creditAmount = (float) ($resolutionData['credit_amount'] ?? $claim->replacementProduct?->cost_price ?? 0);

                if ($creditAmount > 0) {
                    $before = (float) $supplier->current_balance;
                    $after = round($before - $creditAmount, 2);
                    $supplier->update(['current_balance' => $after]);

                    SupplierLedgerEntry::create([
                        'supplier_id'    => $supplier->id,
                        'entry_type'     => 'adjustment',
                        'amount'         => $creditAmount,
                        'balance_before' => $before,
                        'balance_after'  => $after,
                        'payment_method' => 'cash',
                        'paid_by'        => $userId,
                        'notes'          => "إشعار خصم ضمان لبطارية تالفة تذكرة رقم {$claim->claim_number}",
                    ]);
                }
            }

            // Update claim status
            $claim->update([
                'supplier_resolution' => $action,
                'settled_by_user_id'  => $userId,
                'resolved_at'         => now(),
            ]);

            return $claim->fresh(['supplier', 'replacementProduct', 'settledByUser']);
        });
    }
}
