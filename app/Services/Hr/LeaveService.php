<?php

namespace App\Services\Hr;

use App\Contracts\Hr\LeaveServiceInterface;
use App\Models\EmployeeLeave;
use App\Models\User;
use App\Notifications\LeaveRequestedNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveService implements LeaveServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function getPaginatedLeaves(int $perPage = 15): LengthAwarePaginator
    {
        return EmployeeLeave::with(['employee.branch', 'actionedByUser'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function applyForLeave(array $data): EmployeeLeave
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $daysCount = $start->diffInDays($end) + 1;

        $leave = EmployeeLeave::create([
            'employee_id' => $data['employee_id'],
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_count' => $daysCount,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        // إشعار الإدارة بطلب إجازة جديد
        $admins = User::role(['super-admin', 'branch-manager'])->get();
        foreach ($admins as $admin) {
            $admin->notify(new LeaveRequestedNotification($leave));
        }

        return $leave;
    }

    /**
     * {@inheritDoc}
     */
    public function updateLeaveStatus(EmployeeLeave $leave, string $status, ?string $notes = null, ?int $actionedBy = null): bool
    {
        return DB::transaction(function () use ($leave, $status, $notes, $actionedBy) {
            $updated = $leave->update([
                'status' => $status,
                'actioned_by' => $actionedBy ?? Auth::id(),
                'action_notes' => $notes,
            ]);

            if ($status === 'approved') {
                $leave->employee->update(['status' => 'on_leave']);
            }

            return $updated;
        });
    }
}
