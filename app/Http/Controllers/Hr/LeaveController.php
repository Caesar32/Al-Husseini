<?php

namespace App\Http\Controllers\Hr;

use App\Contracts\Hr\LeaveServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveRequest;
use App\Http\Requests\Hr\UpdateLeaveStatusRequest;
use App\Models\EmployeeLeave;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveServiceInterface $leaveService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $leaves = $this->leaveService->getPaginatedLeaves();

        return response()->json($leaves);
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $leave = $this->leaveService->applyForLeave($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تقديم طلب الإجازة بنجاح وإشعار الإدارة للموافقة.',
            'leave' => $leave->load('employee'),
        ]);
    }

    public function updateStatus(UpdateLeaveStatusRequest $request, EmployeeLeave $leave): JsonResponse
    {
        $validated = $request->validated();

        $this->leaveService->updateLeaveStatus(
            $leave,
            $validated['status'],
            $validated['action_notes'] ?? null,
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة طلب الإجازة بنجاح.',
        ]);
    }
}
