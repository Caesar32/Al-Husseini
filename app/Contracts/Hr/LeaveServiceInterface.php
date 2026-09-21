<?php

namespace App\Contracts\Hr;

use App\Models\EmployeeLeave;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeaveServiceInterface
{
    /**
     * Get paginated leaves with related employee, branch, and actioned user.
     */
    public function getPaginatedLeaves(int $perPage = 15): LengthAwarePaginator;

    /**
     * Submit an employee leave request, compute leave days, and notify administrators.
     */
    public function applyForLeave(array $data): EmployeeLeave;

    /**
     * Approve or reject a leave request and update employee status accordingly.
     */
    public function updateLeaveStatus(EmployeeLeave $leave, string $status, ?string $notes = null, ?int $actionedBy = null): bool;
}
