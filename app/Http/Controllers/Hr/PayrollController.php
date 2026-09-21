<?php

namespace App\Http\Controllers\Hr;

use App\Contracts\Hr\PayrollServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\GeneratePayrollRequest;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Exception;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollServiceInterface $payrollService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $data = $this->payrollService->getPayrollIndexData($branchId);

        if ($request->wantsJson()) {
            return response()->json([
                'payrolls' => $data['payrolls'],
                'latest_payroll' => $data['latestPayroll'],
            ]);
        }

        return view('admin.hr.payroll', [
            'payrolls' => $data['payrolls'],
            'branches' => $data['branches'],
            'employees' => $data['employees'],
            'recentDeductions' => $data['recentDeductions'],
            'latestPayroll' => $data['latestPayroll'],
        ]);
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        try {
            $payroll = $this->payrollService->generateMonthlyPayroll(
                (int) $request->branch_id,
                (int) $request->year,
                (int) $request->month
            );

            return response()->json([
                'success' => true,
                'message' => 'تم احتساب مسير الرواتب بنجاح وتوليد المسودة للمراجعة.',
                'payroll_id' => $payroll->id,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Payroll $payroll): View|JsonResponse
    {
        $detailedPayroll = $this->payrollService->getPayrollDetails($payroll);

        if (request()->wantsJson()) {
            return response()->json($detailedPayroll);
        }

        return view('admin.hr.payroll_show', ['payroll' => $detailedPayroll]);
    }

    public function approve(Payroll $payroll): JsonResponse
    {
        try {
            $this->payrollService->approvePayroll($payroll, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'تم اعتماد مسير الرواتب بنجاح.',
            ]);
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
