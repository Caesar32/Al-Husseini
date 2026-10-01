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
     * Evaluate a batch against the same consistency rules used by approve/disburse.
     *
     * @return array{basic: float, allowances: float, overtime: float, deductions: float, carried_debt: float,
     *               expected_net: float, stored_net: float, zero_with_components: bool, consistent: bool, reasons: list<string>}
     */
    public function evaluateConsistency(Payroll $payroll): array;

    /**
     * Load payroll relations for viewing details.
     */
    public function getPayrollDetails(Payroll $payroll): Payroll;
}
