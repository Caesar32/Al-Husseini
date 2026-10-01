<?php

namespace App\Services\Hr;

use App\Contracts\Hr\AttendanceServiceInterface;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use App\Models\EmployeeLeave;
use App\Notifications\EmployeeLateNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class AttendanceService implements AttendanceServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function getDailyAttendance(string $date, ?int $branchId = null, ?string $status = null): Collection
    {
        $query = Attendance::with(['employee.branch', 'employee.jobTitle', 'deductions'])
            ->whereDate('work_date', $date);

        if (!empty($branchId)) {
            $query->whereHas('employee', fn($q) => $q->where('branch_id', $branchId));
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        return $query->latest('id')->get();
    }

    /**
     * {@inheritDoc}
     */
    public function getDailyStats(string $date): array
    {
        $totalExpected = Employee::active()->count();
        $present = Attendance::whereDate('work_date', $date)->where('status', 'present')->count();
        $late = Attendance::whereDate('work_date', $date)->where('status', 'late')->count();
        $absent = Attendance::whereDate('work_date', $date)->where('status', 'absent')->count();
        $holiday = Attendance::whereDate('work_date', $date)->where('status', 'holiday')->count();
        $onLeave = EmployeeLeave::where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->count();

        return [
            'total_expected' => $totalExpected,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'holiday' => $holiday,
            'on_leave' => $onLeave,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getFormData(): array
    {
        return [
            'branches' => Branch::where('is_active', true)->get(),
            'employees' => Employee::active()->with(['branch', 'jobTitle'])->get(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function recordPunch(mixed $employeeIdentifier, Carbon $punchTime, string|int $punchState, string $source = 'manual'): Attendance
    {
        return DB::transaction(function () use ($employeeIdentifier, $punchTime, $punchState, $source) {
            $employee = is_numeric($employeeIdentifier)
                ? Employee::where('id', $employeeIdentifier)->orWhere('zkteco_pin', (string)$employeeIdentifier)->firstOrFail()
                : Employee::where('zkteco_pin', $employeeIdentifier)->firstOrFail();

            $workDate = $punchTime->toDateString();

            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->first();

            if (!$attendance) {
                $attendance = Attendance::create([
                    'employee_id' => $employee->id,
                    'work_date' => $workDate,
                    'status' => 'present',
                    'source' => $source,
                ]);
            }

            $isCheckIn = ($punchState === 0 || $punchState === '0' || $punchState === 'check_in');
            $isCheckOut = ($punchState === 1 || $punchState === '1' || $punchState === 'check_out');

            if (!$isCheckIn && !$isCheckOut) {
                throw new Exception("نوع البصمة غير صالح، يجب أن يكون حضور أو انصراف.");
            }

            // فحص ما إذا كان الموظف في إجازة معتمدة لليوم
            $isOnLeave = $employee->status === 'on_leave' || EmployeeLeave::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $workDate)
                ->whereDate('end_date', '>=', $workDate)
                ->exists();

            if ($isCheckIn) {
                // إذا كان الموظف مسجل حضور بالفعل لنفس اليوم
                if ($attendance && $attendance->check_in) {
                    $diffInMinutes = abs($punchTime->diffInMinutes($attendance->check_in));

                    // 1. قاعدة فترة التبريد (Debounce 5 دقائق): بصمة مكررة عرضية يتم تجاهلها والحفاظ على الأصلية
                    if ($diffInMinutes < 5) {
                        return $attendance;
                    }

                    // 2. إذا مضت أكثر من 5 دقائق: رفض العملية صراحة
                    $checkInFormatted = $attendance->check_in->format('h:i A');
                    throw new Exception("الموظف ({$employee->full_name}) مسجل حضور بالفعل اليوم في تمام الساعة ({$checkInFormatted}). لا يمكن تسجيل حضور مكرر.");
                }

                if (!$attendance) {
                    $attendance = Attendance::create([
                        'employee_id' => $employee->id,
                        'work_date' => $workDate,
                        'status' => $isOnLeave ? 'holiday' : 'present',
                        'source' => $source,
                    ]);
                } else {
                    $attendance->status = $isOnLeave ? 'holiday' : 'present';
                    $attendance->source = $source;
                }

                $attendance->check_in = $punchTime;
                $this->calculateLateness($attendance, $employee, $punchTime);

                // الموظف في إجازة معتمدة: لا نلغي الإجازة، بل نجعل الحالة holiday (بدون تأخير؛ الساعات تُحتسب إضافي)
                if ($isOnLeave) {
                    $attendance->status = 'holiday';
                    $attendance->late_minutes = 0;
                }

            } elseif ($isCheckOut) {
                // 3. رفض الانصراف بدون تسجيل حضور مسبق
                if (!$attendance || !$attendance->check_in) {
                    throw new Exception("لا يمكن تسجيل انصراف لموظف لم يسجل حضوره اليوم ({$employee->full_name}). يرجى تسجيل الحضور أولاً أو مراجعة المشرف.");
                }

                // 4. رفض الانصراف إذا كان وقته يسبق أو يساوي وقت الحضور
                if ($punchTime->lessThanOrEqualTo($attendance->check_in)) {
                    $checkInFormatted = $attendance->check_in->format('h:i A');
                    $checkOutFormatted = $punchTime->format('h:i A');
                    throw new Exception("وقت الانصراف ({$checkOutFormatted}) لا يمكن أن يسبق أو يساوي وقت الحضور المسجل ({$checkInFormatted}).");
                }

                // فترة التبريد للانصراف (Debounce 5 دقائق)
                if ($attendance->check_out) {
                    $diffInMinutes = abs($punchTime->diffInMinutes($attendance->check_out));
                    if ($diffInMinutes < 5) {
                        return $attendance;
                    }
                }

                $attendance->check_out = $punchTime;

                // إذا كان الموظف في إجازة معتمدة، تُحتسب ساعات عمله كعمل إضافي (Overtime)
                if ($attendance->status === 'holiday' || $isOnLeave) {
                    $workedHours = round($attendance->check_in->diffInMinutes($punchTime) / 60, 2);
                    $attendance->overtime_hours = $workedHours;
                    $attendance->early_leave_minutes = 0;
                } else {
                    $this->calculateEarlyLeaveAndOvertime($attendance, $employee, $punchTime);
                }
            }

            $attendance->save();

            // إرسال إشعار فوري في حالة التأخير — مرة واحدة فقط عند تسجيل التأخير، لا عند بصمة الانصراف
            if ($attendance->late_minutes > 0 && $attendance->wasChanged('late_minutes')) {
                $this->notifyAdminsAboutLateness($attendance);
            }

            return $attendance;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function markAbsent(int $employeeId, string $date, ?string $reason = null): Attendance
    {
        return DB::transaction(function () use ($employeeId, $date, $reason) {
            $employee = Employee::findOrFail($employeeId);

            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('work_date', $date)
                ->first();

            if (!$attendance) {
                $attendance = Attendance::create([
                    'employee_id' => $employee->id,
                    'work_date' => $date,
                    'status' => 'absent',
                    'source' => 'manual',
                ]);
            } else {
                $attendance->status = 'absent';
                $attendance->check_in = null;
                $attendance->check_out = null;
                $attendance->late_minutes = 0;
                $attendance->early_leave_minutes = 0;
                $attendance->overtime_hours = 0;
                $attendance->source = 'manual';
            }

            $attendance->save();

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
        } else {
            $attendance->late_minutes = 0;
        }
    }

    private function calculateEarlyLeaveAndOvertime(Attendance $attendance, Employee $employee, Carbon $checkOut): void
    {
        $shiftEnd = Carbon::parse($attendance->work_date->format('Y-m-d') . ' ' . $employee->shift_end_time);

        // إذا كان موعد نهاية الشفت أقل من موعد بدايته (وردية ليلية تعبر منتصف الليل للطوارئ)
        if (Carbon::parse($employee->shift_end_time)->lessThan(Carbon::parse($employee->shift_start_time))) {
            $shiftEnd->addDay();
        }

        if ($checkOut->lessThan($shiftEnd)) {
            $attendance->early_leave_minutes = $checkOut->diffInMinutes($shiftEnd);
            $attendance->overtime_hours = 0;
        } else {
            $attendance->early_leave_minutes = 0;
            $overtimeMinutes = $shiftEnd->diffInMinutes($checkOut);
            if ($overtimeMinutes >= 30) {
                $attendance->overtime_hours = round($overtimeMinutes / 60, 2);
            } else {
                $attendance->overtime_hours = 0;
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
