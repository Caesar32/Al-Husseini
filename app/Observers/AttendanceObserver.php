<?php

namespace App\Observers;

use App\Models\Attendance;
use App\Models\EmployeeDeduction;
use Carbon\Carbon;

class AttendanceObserver
{
    public function saving(Attendance $attendance): void
    {
        // حساب دقائق التأخير بناءً على بداية الشفت وسماحية الموظف
        if ($attendance->check_in) {
            $employee = $attendance->employee;
            if ($employee) {
                $shiftStart = Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $employee->shift_start_time);
                $checkIn = Carbon::parse($attendance->check_in);

                if ($checkIn->greaterThan($shiftStart)) {
                    $diffMinutes = $shiftStart->diffInMinutes($checkIn);
                    if ($diffMinutes > $employee->grace_period_minutes) {
                        $attendance->late_minutes = $diffMinutes;
                        $attendance->status = 'late';
                    } else {
                        $attendance->late_minutes = 0;
                    }
                } else {
                    $attendance->late_minutes = 0;
                }
            }
        }
    }

    public function saved(Attendance $attendance): void
    {
        // إذا كان متأخراً أكثر من 30 دقيقة يتم إنشاء مسودة جزاء مالي تلقائية
        if ($attendance->late_minutes >= 30) {
            $employee = $attendance->employee;
            if ($employee) {
                $currentSalary = $employee->currentSalary?->basic_salary ?? 0;
                $dayWage = $currentSalary > 0 ? ($currentSalary / 30) : 0;
                $deductionAmount = round($dayWage * 0.25, 2); // خصم ربع يوم للتأخير أكثر من نصف ساعة

                if ($deductionAmount > 0) {
                    EmployeeDeduction::firstOrCreate(
                        ['attendance_id' => $attendance->id],
                        [
                            'employee_id' => $attendance->employee_id,
                            'deduction_date' => $attendance->work_date,
                            'amount' => $deductionAmount,
                            'reason' => "تأخير تلقائي لمدة {$attendance->late_minutes} دقيقة عن شفت العمل",
                            'status' => 'pending',
                        ]
                    );
                }
            }
        }
    }
}
