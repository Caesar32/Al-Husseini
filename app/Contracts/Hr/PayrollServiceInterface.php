<?php

namespace App\Contracts\Hr;

use App\Models\Payroll;

interface PayrollServiceInterface
{
    /**
     * Get index data (paginated payrolls, latest payroll, branches, employees, recent deductions).
     */
    public function getPayrollIndexData(?int $branchId = null, int $perPage = 15): array;

    /**
     * Generate or recalculate draft monthly payroll for a specific branch and month/year.
     */
    public function generateMonthlyPayroll(int $branchId, int $year, int $month): Payroll;

    /**
     * Approve a drafted monthly payroll batch for disbursement.
     */
    public function approvePayroll(Payroll $payroll, ?int $approvedBy = null, bool $confirmDebtReview = false): bool;

    /**
     * Mark an approved payroll batch as disbursed and permanently close the monthly period.
     */
    public function disbursePayroll(Payroll $payroll): bool;

    /**
     * Load payroll relations for viewing details.
     */
    public function getPayrollDetails(Payroll $payroll): Payroll;
}
