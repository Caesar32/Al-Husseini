<?php

namespace App\Services\Hr;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * HR attendance / deductions reports built from the database (replaces the browser-only
 * hr-store.js mock reports). The payload shape matches what the reports page renders.
 *
 * Report statuses: on_time (present), late, absent (explicit absence record),
 * on_leave (approved leave day, or a holiday/excused attendance row), not_recorded
 * (no attendance record and no approved leave for that day).
 */
class HrReportService
{
    /** Deduction statuses that are effective (pending and cancelled are excluded). */
    private const EFFECTIVE_DEDUCTION_STATUSES = ['approved', 'applied'];

    public function dailyReport(string $date): array
    {
        $date = Carbon::parse($date)->toDateString();
        $employees = $this->employees();
        $ids = $employees->pluck('id');

        $attendances = Attendance::whereIn('employee_id', $ids)
            ->whereDate('work_date', $date)
            ->get()
            ->keyBy('employee_id');

        $onLeaveIds = $this->approvedLeaves($ids, $date, $date)->pluck('employee_id')->unique();

        $deductions = $this->deductions($ids, $date, $date);

        $rows = $employees->map(function (Employee $employee) use ($attendances, $onLeaveIds, $deductions, $date) {
            $attendance = $attendances->get($employee->id);
            $employeeDeductions = $deductions->where('employee_id', $employee->id);

            $status = $attendance
                ? $this->reportStatus($attendance->status)
                : ($onLeaveIds->contains($employee->id) ? 'on_leave' : 'not_recorded');

            return [
                'employee'        => $this->employeePayload($employee),
                'date'            => $date,
                'status'          => $status,
                'punchIn'         => $attendance?->check_in?->format('H:i') ?? '-',
                'punchOut'        => $attendance?->check_out?->format('H:i') ?? '-',
                'latenessMinutes' => (int) ($attendance?->late_minutes ?? 0),
                'totalDeductions' => round((float) $employeeDeductions->sum('amount'), 2),
            ];
        })->values();

        $present = $rows->whereIn('status', ['on_time', 'late'])->count();
        $onTime = $rows->where('status', 'on_time')->count();

        return [
            'date'           => $date,
            'employees'      => $rows,
            'deductionsList' => $this->deductionsPayload($deductions, $employees),
            'departments'    => $this->departmentNames($employees),
            'summary'        => [
                'totalStaff'            => $employees->count(),
                'presentCount'          => $present,
                'onTimeCount'           => $onTime,
                'lateCount'             => $rows->where('status', 'late')->count(),
                'absentCount'           => $rows->where('status', 'absent')->count(),
                'leaveCount'            => $rows->where('status', 'on_leave')->count(),
                'notRecordedCount'      => $rows->where('status', 'not_recorded')->count(),
                'totalLateMins'         => (int) $rows->sum('latenessMinutes'),
                'totalDeductionsAmount' => round((float) $deductions->sum('amount'), 2),
                'deductionsCount'       => $deductions->count(),
                'attendanceRate'        => $employees->count() > 0 ? (int) round($present / $employees->count() * 100) : 0,
                'punctualityRate'       => $present > 0 ? (int) round($onTime / $present * 100) : 0,
            ],
        ];
    }

    public function monthlyReport(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();

        return $this->rangeReport($start->toDateString(), $start->copy()->endOfMonth()->toDateString());
    }

    public function rangeReport(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();

        $employees = $this->employees();
        $ids = $employees->pluck('id');

        $attendances = Attendance::whereIn('employee_id', $ids)
            ->whereDate('work_date', '>=', $startStr)
            ->whereDate('work_date', '<=', $endStr)
            ->orderBy('work_date')
            ->get();

        $leaves = $this->approvedLeaves($ids, $startStr, $endStr);
        $deductions = $this->deductions($ids, $startStr, $endStr);

        $distinctDates = $attendances->map(fn (Attendance $a) => $a->work_date->toDateString())->unique()->sort()->values();
        $recordedDays = max(1, $distinctDates->count());

        $staffReport = $employees->map(function (Employee $employee) use ($attendances, $leaves, $deductions, $recordedDays, $start, $end) {
            $rows = $attendances->where('employee_id', $employee->id);
            $statuses = $rows->map(fn (Attendance $a) => $this->reportStatus($a->status));
            $employeeDeductions = $deductions->where('employee_id', $employee->id);

            // Leave days: approved leave days in range without an attendance row, plus holiday/excused rows.
            $attendedDates = $rows->map(fn (Attendance $a) => $a->work_date->toDateString())->all();
            $leaveDays = $statuses->filter(fn ($s) => $s === 'on_leave')->count();
            foreach ($leaves->where('employee_id', $employee->id) as $leave) {
                $from = Carbon::parse($leave->start_date)->max($start);
                $to = Carbon::parse($leave->end_date)->min($end);
                if ($from->lessThanOrEqualTo($to)) {
                    foreach (CarbonPeriod::create($from, $to) as $day) {
                        if (!in_array($day->toDateString(), $attendedDates, true)) {
                            $leaveDays++;
                        }
                    }
                }
            }

            $presentDays = $statuses->filter(fn ($s) => in_array($s, ['on_time', 'late'], true))->count();

            return [
                'employee'              => $this->employeePayload($employee),
                'totalWorkDays'         => $recordedDays,
                'presentDays'           => $presentDays,
                'onTimeDays'            => $statuses->filter(fn ($s) => $s === 'on_time')->count(),
                'lateDays'              => $statuses->filter(fn ($s) => $s === 'late')->count(),
                'absentDays'            => $statuses->filter(fn ($s) => $s === 'absent')->count(),
                'leaveDays'             => $leaveDays,
                'totalLateMinutes'      => (int) $rows->sum('late_minutes'),
                'deductionsCount'       => $employeeDeductions->count(),
                'totalDeductionsAmount' => round((float) $employeeDeductions->sum('amount'), 2),
                'attendanceRate'        => (int) round($presentDays / $recordedDays * 100),
                'attendanceDetails'     => $rows->map(fn (Attendance $a) => [
                    'date'            => $a->work_date->toDateString(),
                    'status'          => $this->reportStatus($a->status),
                    'punchIn'         => $a->check_in?->format('H:i'),
                    'punchOut'        => $a->check_out?->format('H:i'),
                    'latenessMinutes' => (int) $a->late_minutes,
                ])->values(),
            ];
        })->values();

        $dailySeries = $distinctDates->map(function (string $date) use ($attendances) {
            $day = $attendances->filter(fn (Attendance $a) => $a->work_date->toDateString() === $date);
            $statuses = $day->map(fn (Attendance $a) => $this->reportStatus($a->status));

            return [
                'date'        => $date,
                'present'     => $statuses->filter(fn ($s) => in_array($s, ['on_time', 'late'], true))->count(),
                'late'        => $statuses->filter(fn ($s) => $s === 'late')->count(),
                'absent'      => $statuses->filter(fn ($s) => $s === 'absent')->count(),
                'lateMinutes' => (int) $day->sum('late_minutes'),
            ];
        })->values();

        return [
            'startDate'          => $startStr,
            'endDate'            => $endStr,
            'distinctDatesCount' => $distinctDates->count(),
            'distinctDates'      => $distinctDates,
            'staffReport'        => $staffReport,
            'deductionsList'     => $this->deductionsPayload($deductions, $employees),
            'dailySeries'        => $dailySeries,
            'departments'        => $this->departmentNames($employees),
            'summary'            => [
                'totalStaff'            => $employees->count(),
                'totalPresents'         => (int) $staffReport->sum('presentDays'),
                'totalOnTimes'          => (int) $staffReport->sum('onTimeDays'),
                'totalLates'            => (int) $staffReport->sum('lateDays'),
                'totalAbsents'          => (int) $staffReport->sum('absentDays'),
                'totalLeaveDays'        => (int) $staffReport->sum('leaveDays'),
                'totalLateMins'         => (int) $staffReport->sum('totalLateMinutes'),
                'totalDeductionsAmount' => round((float) $deductions->sum('amount'), 2),
                'deductionsCount'       => $deductions->count(),
                'avgAttendanceRate'     => $staffReport->count() > 0 ? (int) round($staffReport->avg('attendanceRate')) : 0,
            ],
        ];
    }

    /**
     * Full discipline history for one employee (the "details" modal).
     */
    public function employeeHistory(Employee $employee): array
    {
        $employee->loadMissing(['jobTitle.department']);

        $attendances = $employee->attendances()->get();
        $deductions = $employee->deductions()
            ->whereIn('status', self::EFFECTIVE_DEDUCTION_STATUSES)
            ->orderByDesc('deduction_date')
            ->get();

        $statuses = $attendances->map(fn (Attendance $a) => $this->reportStatus($a->status));

        return [
            'employee'              => $this->employeePayload($employee),
            'presentDays'           => $statuses->filter(fn ($s) => in_array($s, ['on_time', 'late'], true))->count(),
            'lateDays'              => $statuses->filter(fn ($s) => $s === 'late')->count(),
            'totalLateMinutes'      => (int) $attendances->sum('late_minutes'),
            'totalDeductionsAmount' => round((float) $deductions->sum('amount'), 2),
            'deductions'            => $this->deductionsPayload($deductions, collect([$employee])),
        ];
    }

    private function employees(): Collection
    {
        return Employee::whereIn('status', ['active', 'on_leave'])
            ->with(['jobTitle.department'])
            ->orderBy('full_name')
            ->get();
    }

    private function approvedLeaves(Collection $employeeIds, string $from, string $to): Collection
    {
        return EmployeeLeave::whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();
    }

    private function deductions(Collection $employeeIds, string $from, string $to): Collection
    {
        return EmployeeDeduction::whereIn('employee_id', $employeeIds)
            ->whereIn('status', self::EFFECTIVE_DEDUCTION_STATUSES)
            ->whereDate('deduction_date', '>=', $from)
            ->whereDate('deduction_date', '<=', $to)
            ->orderByDesc('deduction_date')
            ->get();
    }

    private function reportStatus(?string $attendanceStatus): string
    {
        return match ($attendanceStatus) {
            'present'             => 'on_time',
            'late'                => 'late',
            'absent'              => 'absent',
            'holiday', 'excused'  => 'on_leave',
            default               => 'not_recorded',
        };
    }

    private function employeePayload(Employee $employee): array
    {
        return [
            'id'         => $employee->id,
            'code'       => $employee->employee_code,
            'name'       => $employee->full_name,
            'role'       => $employee->jobTitle?->title_name ?? '',
            'department' => $employee->jobTitle?->department?->name ?? '',
            'startTime'  => substr((string) $employee->shift_start_time, 0, 5),
            'endTime'    => substr((string) $employee->shift_end_time, 0, 5),
        ];
    }

    private function deductionsPayload(Collection $deductions, Collection $employees): Collection
    {
        $byId = $employees->keyBy('id');

        return $deductions->map(fn (EmployeeDeduction $d) => [
            'id'         => $d->id,
            'decisionNo' => 'DED-' . $d->id,
            'date'       => $d->deduction_date->toDateString(),
            'amount'     => round((float) $d->amount, 2),
            'reason'     => $d->reason,
            'status'     => $d->status,
            'employee'   => $byId->has($d->employee_id) ? $this->employeePayload($byId->get($d->employee_id)) : null,
        ])->values();
    }

    private function departmentNames(Collection $employees): Collection
    {
        return $employees->map(fn (Employee $e) => $e->jobTitle?->department?->name)->filter()->unique()->sort()->values();
    }
}
