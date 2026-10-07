<?php

namespace App\Http\Controllers\Api\V1\Owner;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    /**
     * Staff & Workshop Live Attendance.
     * Surveillance of all on-duty technicians and shop staff today.
     */
    public function todayAttendance(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        // Fetch all active employees
        $employeesQuery = Employee::query()->active()->with('jobTitle');
        if ($branchId !== null) {
            $employeesQuery->where('branch_id', $branchId);
        }
        $employees = $employeesQuery->get();

        // Fetch today's attendances
        $attendancesQuery = Attendance::query()
            ->where('work_date', $today)
            ->with(['employee.jobTitle']);

        if ($branchId !== null) {
            $attendancesQuery->whereHas('employee', fn($q) => $q->where('branch_id', $branchId));
        }
        $attendances = $attendancesQuery->get()->keyBy('employee_id');

        $staffList = [];
        $presentCount = 0;
        $lateCount = 0;
        $absentCount = 0;

        foreach ($employees as $employee) {
            $attendance = $attendances->get($employee->id);

            $status = 'absent';
            $checkInTime = null;
            $checkOutTime = null;
            $lateMinutes = 0;

            if ($attendance) {
                $status = $attendance->status ?? 'present';
                $lateMinutes = (int) $attendance->late_minutes;

                if ($attendance->check_in) {
                    $ci = $attendance->check_in;
                    $checkInTime = $ci->format('h:i') . ' ' . ($ci->format('A') === 'AM' ? 'ص' : 'م');
                }

                if ($attendance->check_out) {
                    $co = $attendance->check_out;
                    $checkOutTime = $co->format('h:i') . ' ' . ($co->format('A') === 'AM' ? 'ص' : 'م');
                }
            }

            if ($status === 'present') {
                $presentCount++;
            } elseif ($status === 'late') {
                $lateCount++;
                $presentCount++; // late employees are also counted as present at work
            } else {
                $absentCount++;
            }

            $statusLabels = [
                'present' => 'حاضر',
                'late'    => 'متأخر',
                'absent'  => 'غائب',
                'leave'   => 'إجازة',
            ];

            $staffList[] = [
                'employee_id'    => $employee->id,
                'employee_code'  => $employee->employee_code,
                'name'           => $employee->full_name,
                'role'           => $employee->jobTitle?->title ?? 'فني / موظف',
                'phone'          => $employee->phone,
                'status'         => $status,
                'status_label'   => $statusLabels[$status] ?? $status,
                'check_in_time'  => $checkInTime,
                'check_out_time' => $checkOutTime,
                'late_minutes'   => $lateMinutes,
            ];
        }

        $totalEmployees = $employees->count();
        $rate = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100) : 0;

        return response()->json([
            'status'  => 'success',
            'summary' => [
                'total_employees' => $totalEmployees,
                'present_count'   => $presentCount,
                'late_count'      => $lateCount,
                'absent_count'    => $absentCount,
                'attendance_rate' => "{$rate}%",
            ],
            'data'    => $staffList,
        ]);
    }
}
