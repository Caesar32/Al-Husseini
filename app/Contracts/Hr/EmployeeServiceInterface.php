<?php

namespace App\Contracts\Hr;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeServiceInterface
{
    /**
     * Get paginated employees filtered by branch, status, or search keywords.
     */
    public function getPaginatedEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Calculate summary statistics for employees (total, active, on leave, avg salary).
     */
    public function getEmployeeStats(): array;

    /**
     * Get supplementary form reference data (active branches, departments with job titles).
     */
    public function getFormData(): array;

    /**
     * Create a new employee along with their initial salary structure within a transaction.
     */
    public function createEmployee(array $data): Employee;

    /**
     * Update employee personal details and their salary structure within a transaction.
     */
    public function updateEmployee(Employee $employee, array $data): bool;

    /**
     * Load comprehensive employee profile relations.
     */
    public function getEmployeeDetails(Employee $employee): Employee;

    /**
     * Soft delete/archive an employee.
     */
    public function deleteEmployee(Employee $employee): bool;
}
