<?php

namespace App\Services\Hr;

use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeDeduction;
use App\Models\TechnicianCommission;
use App\Models\EmployeeLeave;
use App\Models\Branch;
use App\Models\User;
use App\Notifications\PayrollGeneratedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class PayrollService implements PayrollServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function getPayrollIndexData(?int $branchId = null, int $perPage = 15): array
    {
        $branches = Branch::where('is_active', true)->get();
        $employees = Employee::active()->with(['branch', 'jobTitle.department', 'currentSalary'])->get();
        $recentDeductions = EmployeeDeduction::with(['employee', 'approvedByUser'])->latest('id')->take(20)->get();

        $query = Payroll::with(['branch', 'approvedBy', 'items.employee.jobTitle'])->latest('id');

        if (!empty($branchId)) {
            $query->where('branch_id', $branchId);
        }

        $payrolls = $query->paginate($perPage);
        $latestPayroll = (clone $query)->first();

        return compact('payrolls', 'branches', 'employees', 'recentDeductions', 'latestPayroll');
    }

    /**
     * {@inheritDoc}
     */
    public function generateMonthlyPayroll(int $branchId, int $year, int $month): Payroll
    {
        return DB::transaction(function () use ($branchId, $year, $month) {
            $existing = Payroll::where('branch_id', $branchId)->where('year', $year)->where('month', $month)->first();
            if ($existing && $existing->status === 'disbursed') {
                throw new Exception("لا يمكن إعادة احتساب مسير رواتب تم صرفه وإغلاقه بالفعل.");
            }

            $payroll = Payroll::updateOrCreate(
                ['branch_id' => $branchId, 'year' => $year, 'month' => $month],
                ['status' => 'draft']
            );

            // حذف البنود القديمة لإعادة الاحتساب بدقة
            $payroll->items()->delete();

            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate   = $startDate->copy()->endOfMonth();
            $totalMonthDays = $startDate->daysInMonth;

            $employees = Employee::with(['currentSalary'])
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->get();

            $employeeIds = $employees->pluck('id');

            // ─── جلب كل بيانات الحضور والإجازات والعمولات والجزاءات دفعة واحدة (4 استعلامات فقط بدلاً من 4N) ───
            $allAttendances = Attendance::whereIn('employee_id', $employeeIds)
                ->whereBetween('work_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get()
                ->groupBy('employee_id');

            $allLeaves = EmployeeLeave::whereIn('employee_id', $employeeIds)
                ->where('status', 'approved')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereDate('start_date', '<=', $endDate->toDateString())
                          ->whereDate('end_date', '>=', $startDate->toDateString());
                })
                ->get()
                ->groupBy('employee_id');

            $allCommissions = TechnicianCommission::whereIn('employee_id', $employeeIds)
                ->where('status', 'approved')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get()
                ->groupBy('employee_id');

            $allDeductions = EmployeeDeduction::whereIn('employee_id', $employeeIds)
                ->where('status', 'approved')
                ->whereBetween('deduction_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get()
                ->groupBy('employee_id');
            // ────────────────────────────────────────────────────────────────────────────────────────────

            $totalBasic       = 0;
            $totalAllowances  = 0;
            $totalDeductions  = 0;
            $totalNet         = 0;

            foreach ($employees as $employee) {
                $salary = $employee->currentSalary;
                if (!$salary) continue;

                $basic      = (float) $salary->basic_salary;
                $allowances = (float) ($salary->housing_allowance + $salary->transport_allowance + $salary->other_allowances);
                $dayRate    = $basic / $totalMonthDays;
                $hourlyRate = $dayRate / 8; // شفت 8 ساعات عمل

                // 1. حساب الحضور والغياب والإجازات والتأخير (من الذاكرة — بدون استعلام)
                $attendances = $allAttendances->get($employee->id, collect());
                $employeeLeaves = $allLeaves->get($employee->id, collect());

                $paidLeaveDays = 0;
                $unpaidLeaveDays = 0;

                foreach ($employeeLeaves as $leave) {
                    $leaveStart = Carbon::parse($leave->start_date)->startOfDay();
                    $leaveEnd = Carbon::parse($leave->end_date)->startOfDay();
                    $windowStart = $startDate->copy()->startOfDay();
                    $windowEnd = $endDate->copy()->startOfDay();

                    $effectiveStart = $leaveStart->greaterThan($windowStart) ? $leaveStart : $windowStart;
                    $effectiveEnd = $leaveEnd->lessThan($windowEnd) ? $leaveEnd : $windowEnd;

                    if ($effectiveStart->lessThanOrEqualTo($effectiveEnd)) {
                        $days = (int) $effectiveStart->diffInDays($effectiveEnd) + 1;
                        if ($leave->leave_type === 'unpaid') {
                            $unpaidLeaveDays += $days;
                        } else {
                            $paidLeaveDays += $days;
                        }
                    }
                }

                $presentDays         = $attendances->where('status', '!=', 'absent')->count();
                $coveredDays         = $presentDays + $paidLeaveDays;
                $unexcusedAbsentDays = max(0, 26 - $coveredDays); // معيار 26 يوم عمل شهري
                $absentDays          = $unexcusedAbsentDays + $unpaidLeaveDays;
                $absenceCost         = $absentDays * $dayRate;

                $totalLateMinutes  = $attendances->sum('late_minutes');
                $totalOvertimeHours = (float) $attendances->sum('overtime_hours');
                $overtimeValue     = round($totalOvertimeHours * $hourlyRate * 1.5, 2);

                // 2. تجميع عمولات الفني (من الذاكرة — بدون استعلام)
                $commissions = (float) $allCommissions->get($employee->id, collect())->sum('commission_amount');

                // 3. تجميع الجزاءات المعتمدة (من الذاكرة — بدون استعلام)
                $approvedDeductions = (float) $allDeductions->get($employee->id, collect())->sum('amount');

                $allDeductionsTotal = round($absenceCost + $approvedDeductions, 2);
                $netSalary          = max(0, round(($basic + $allowances + $overtimeValue + $commissions) - $allDeductionsTotal, 2));

                PayrollItem::create([
                    'payroll_id'         => $payroll->id,
                    'employee_id'        => $employee->id,
                    'basic_salary'       => $basic,
                    'total_allowance'    => $allowances + $commissions,
                    'total_deduction'    => $allDeductionsTotal,
                    'total_overtime'     => $overtimeValue,
                    'net_salary'         => $netSalary,
                    'absent_days'        => $absentDays,
                    'late_minutes_total' => $totalLateMinutes,
                ]);

                $totalBasic      += $basic;
                $totalAllowances += ($allowances + $commissions);
                $totalDeductions += $allDeductionsTotal;
                $totalNet        += $netSalary;
            }

            $payroll->update([
                'total_basic'      => $totalBasic,
                'total_allowances' => $totalAllowances,
                'total_deductions' => $totalDeductions,
                'total_net'        => $totalNet,
            ]);

            // إشعار المشرف العام بأن المسير جاهز للاعتماد
            $superAdmins = User::role('super-admin')->get();
            foreach ($superAdmins as $superAdmin) {
                $superAdmin->notify(new PayrollGeneratedNotification($payroll));
            }

            return $payroll;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function approvePayroll(Payroll $payroll, ?int $approvedBy = null): bool
    {
        if ($payroll->status !== 'draft') {
            throw new Exception("المسير معتمد مسبقاً أو تم صرفه.");
        }

        $approverId = $approvedBy ?? Auth::id();

        if (empty($approverId)) {
            throw new Exception("يجب تحديد المستخدم المعتمِد — لا يمكن اعتماد مسير الرواتب بدون تسجيل المسؤول.");
        }

        return $payroll->update([
            'status'      => 'approved',
            'approved_by' => $approverId,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function disbursePayroll(Payroll $payroll): bool
    {
        if ($payroll->status !== 'approved') {
            throw new Exception("يجب اعتماد مسير الرواتب أولاً قبل الصرف.");
        }

        return $payroll->update([
            'status' => 'disbursed',
            'disbursed_at' => now(),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function getPayrollDetails(Payroll $payroll): Payroll
    {
        return $payroll->load(['branch', 'items.employee.jobTitle', 'approvedBy']);
    }
}
