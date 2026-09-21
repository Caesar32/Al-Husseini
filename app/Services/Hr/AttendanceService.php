<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\User;
use App\Notifications\EmployeeLateNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * تسجيل حركة حضور أو انصراف
     */
    public function recordPunch(mixed $employeeIdentifier, Carbon $punchTime, string|int $punchState, string $source = 'manual'): Attendance
    {
        return DB::transaction(function () use ($employeeIdentifier, $punchTime, $punchState, $source) {
            $employee = is_numeric($employeeIdentifier)
                ? Employee::where('id', $employeeIdentifier)->orWhere('zkteco_pin', (string)$employeeIdentifier)->firstOrFail()
                : Employee::where('zkteco_pin', $employeeIdentifier)->firstOrFail();

            $workDate = $punchTime->toDateString();

            $attendance = Attendance::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'work_date' => $workDate,
                ],
                [
                    'status' => 'present',
                    'source' => $source,
                ]
            );

            // 0 أو check_in
            if ($punchState === 0 || $punchState === '0' || $punchState === 'check_in') {
                if (!$attendance->check_in || $punchTime->lessThan($attendance->check_in)) {
                    $attendance->check_in = $punchTime;
                    $this->calculateLateness($attendance, $employee, $punchTime);
                }
            } elseif ($punchState === 1 || $punchState === '1' || $punchState === 'check_out') {
                if (!$attendance->check_out || $punchTime->greaterThan($attendance->check_out)) {
                    $attendance->check_out = $punchTime;
                    $this->calculateEarlyLeaveAndOvertime($attendance, $employee, $punchTime);
                }
            }

            $attendance->save();

            // إذا كان الموظف متأخراً يتم إرسال إشعار فوري للإدارة
            if ($attendance->late_minutes > 0) {
                $this->notifyAdminsAboutLateness($attendance);
            }

            return $attendance;
        });
    }

    private function calculateLateness(Attendance $attendance, Employee $employee, Carbon $checkIn): void
    {
        $shiftStart = Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $employee->shift_start_time);

        if ($checkIn->greaterThan($shiftStart)) {
            $diffMinutes = $shiftStart->diffInMinutes($checkIn);
            if ($diffMinutes > $employee->grace_period_minutes) {
                $attendance->late_minutes = $diffMinutes;
                $attendance->status = 'late';
            } else {
                $attendance->late_minutes = 0;
            }
        }
    }

    private function calculateEarlyLeaveAndOvertime(Attendance $attendance, Employee $employee, Carbon $checkOut): void
    {
        $shiftEnd = Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $employee->shift_end_time);

        if ($checkOut->lessThan($shiftEnd)) {
            $attendance->early_leave_minutes = $checkOut->diffInMinutes($shiftEnd);
        } else {
            $overtimeMinutes = $shiftEnd->diffInMinutes($checkOut);
            if ($overtimeMinutes >= 30) {
                $attendance->overtime_hours = round($overtimeMinutes / 60, 2);
            }
        }
    }

    private function notifyAdminsAboutLateness(Attendance $attendance): void
    {
        // إشعار المشرف العام ومدير الفرع
        $admins = User::role(['super-admin', 'branch-manager'])
            ->where(function ($q) use ($attendance) {
                $q->whereNull('branch_id')
                  ->orWhere('branch_id', $attendance->employee->branch_id);
            })
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new EmployeeLateNotification($attendance));
        }
    }
}
