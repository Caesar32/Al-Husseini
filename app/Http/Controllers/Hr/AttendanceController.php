<?php

namespace App\Http\Controllers\Hr;

use App\Contracts\Hr\AttendanceServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RecordPunchRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Exception;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceServiceInterface $attendanceService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $date = $request->get('date', today()->toDateString());
        $attendances = $this->attendanceService->getDailyAttendance(
            $date,
            $request->filled('branch_id') ? (int) $request->branch_id : null,
            $request->filled('status') ? $request->status : null
        );
        $stats = $this->attendanceService->getDailyStats($date);

        if ($request->wantsJson()) {
            return response()->json([
                'attendances' => $attendances,
                'stats' => $stats,
            ]);
        }

        $formData = $this->attendanceService->getFormData();
        $branches = $formData['branches'];
        $employees = $formData['employees'];

        return view('admin.hr.attendance', compact('attendances', 'branches', 'employees', 'date', 'stats'));
    }

    public function recordManual(RecordPunchRequest $request): JsonResponse
    {
        try {
            $timestamp = Carbon::parse($request->timestamp);
            $identifier = $request->employee_id ?? $request->pin;

            $attendance = $this->attendanceService->recordPunch(
                $identifier,
                $timestamp,
                $request->punch_state,
                'manual'
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل حركة البصمة بنجاح واحتساب التأخير.',
                'attendance' => $attendance->load('employee'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
