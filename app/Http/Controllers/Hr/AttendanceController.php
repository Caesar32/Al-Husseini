<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RecordPunchRequest;
use App\Services\Hr\AttendanceService;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService) {}

    public function index(Request $request): View|JsonResponse
    {
        $date = $request->get('date', today()->toDateString());
        $branches = Branch::where('is_active', true)->get();
        $employees = Employee::active()->with('branch')->get();

        $query = Attendance::with(['employee.branch', 'employee.jobTitle', 'deductions'])
            ->where('work_date', $date);

        if ($request->filled('branch_id')) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $request->branch_id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->latest()->get();

        // إحصائيات اليوم
        $stats = [
            'total_expected' => Employee::active()->count(),
            'present' => Attendance::where('work_date', $date)->where('status', 'present')->count(),
            'late' => Attendance::where('work_date', $date)->where('status', 'late')->count(),
            'absent' => Attendance::where('work_date', $date)->where('status', 'absent')->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'attendances' => $attendances,
                'stats' => $stats,
            ]);
        }

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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
