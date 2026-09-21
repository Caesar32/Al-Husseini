<?php

namespace App\Contracts\Hr;

use App\Models\EmployeeDeduction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeductionServiceInterface
{
    /**
     * Get paginated list of employee deductions with related models.
     */
    public function getPaginatedDeductions(int $perPage = 15): LengthAwarePaginator;

    /**
     * Apply an administrative deduction to an employee.
     */
    public function applyDeduction(array $data, ?int $approvedBy = null): EmployeeDeduction;

    /**
     * Update deduction status (approved, cancelled) and record the actioned user.
     */
    public function updateDeductionStatus(EmployeeDeduction $deduction, string $status, ?int $approvedBy = null): bool;
}
