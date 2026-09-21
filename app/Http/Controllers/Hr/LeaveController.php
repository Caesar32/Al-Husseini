<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveRequest;
use App\Models\EmployeeLeave;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\LeaveRequestedNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $leaves = EmployeeLeave::with(['employee.branch', 'actionedByUser'])
            ->latest()
            ->paginate(15);

        return response()->json($leaves);
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $data = $request->validated();
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

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلب الإجازة بنجاح وإشعار الإدارة للموافقة.',
            'leave' => $leave->load('employee'),
        ]);
    }

    public function updateStatus(Request $request, EmployeeLeave $leave): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'action_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $leave->update([
            'status' => $request->status,
            'actioned_by' => auth()->id(),
            'action_notes' => $request->action_notes,
        ]);

        if ($request->status === 'approved') {
            $leave->employee->update(['status' => 'on_leave']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة طلب الإجازة بنجاح.',
        ]);
    }
}
