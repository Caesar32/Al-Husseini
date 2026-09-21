<?php

namespace App\Services\Hr;

use App\Contracts\Hr\DeductionServiceInterface;
use App\Models\EmployeeDeduction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class DeductionService implements DeductionServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function getPaginatedDeductions(int $perPage = 15): LengthAwarePaginator
    {
        return EmployeeDeduction::with(['employee', 'deductionRule', 'approvedByUser'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function applyDeduction(array $data, ?int $approvedBy = null): EmployeeDeduction
    {
        return EmployeeDeduction::create([
            'employee_id' => $data['employee_id'],
            'deduction_rule_id' => $data['deduction_rule_id'] ?? null,
            'deduction_date' => $data['deduction_date'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'approved_by' => $approvedBy ?? Auth::id(),
            'status' => 'approved',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeductionStatus(EmployeeDeduction $deduction, string $status, ?int $approvedBy = null): bool
    {
        return $deduction->update([
            'status' => $status,
            'approved_by' => $approvedBy ?? Auth::id(),
        ]);
    }
}
