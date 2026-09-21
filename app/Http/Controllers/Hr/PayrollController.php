<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\GeneratePayrollRequest;
use App\Services\Hr\PayrollService;
use App\Models\Payroll;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payrollService) {}

    public function index(Request $request): View|JsonResponse
    {
        $branches = Branch::where('is_active', true)->get();
        $employees = \App\Models\Employee::active()->with(['branch', 'jobTitle.department', 'currentSalary'])->get();
        $recentDeductions = \App\Models\EmployeeDeduction::with(['employee', 'approvedByUser'])->latest()->take(20)->get();

        $query = Payroll::with(['branch', 'approvedBy', 'items.employee.jobTitle'])->latest();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $payrolls = $query->paginate(15);
        $latestPayroll = (clone $query)->first();

        if ($request->wantsJson()) {
            return response()->json([
                'payrolls' => $payrolls,
                'latest_payroll' => $latestPayroll,
            ]);
        }

        return view('admin.hr.payroll', compact('payrolls', 'branches', 'employees', 'recentDeductions', 'latestPayroll'));
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        try {
            $payroll = $this->payrollService->generateMonthlyPayroll(
                $request->branch_id,
                $request->year,
                $request->month
            );

            return response()->json([
                'success' => true,
                'message' => 'تم احتساب مسير الرواتب بنجاح وتوليد المسودة للمراجعة.',
                'payroll_id' => $payroll->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Payroll $payroll): View|JsonResponse
    {
        $payroll->load(['branch', 'items.employee.jobTitle', 'approvedBy']);

        if (request()->wantsJson()) {
            return response()->json($payroll);
        }

        return view('admin.hr.payroll_show', compact('payroll'));
    }

    public function approve(Payroll $payroll): JsonResponse
    {
        try {
            $this->payrollService->approvePayroll($payroll, \Illuminate\Support\Facades\Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'تم اعتماد مسير الرواتب بنجاح.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function disburse(Payroll $payroll): JsonResponse
    {
        try {
            $this->payrollService->disbursePayroll($payroll);

            return response()->json([
                'success' => true,
                'message' => 'تم صرف مسير الرواتب وإغلاقه نهائياً.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
