<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDeductionRequest;
use App\Models\EmployeeDeduction;
use App\Models\DeductionRule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeductionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $deductions = EmployeeDeduction::with(['employee', 'deductionRule', 'approvedByUser'])
            ->latest()
            ->paginate(15);

        return response()->json($deductions);
    }

    public function store(StoreDeductionRequest $request): JsonResponse
    {
        $deduction = EmployeeDeduction::create([
            'employee_id' => $request->employee_id,
            'deduction_rule_id' => $request->deduction_rule_id,
            'deduction_date' => $request->deduction_date,
            'amount' => $request->amount,
            'reason' => $request->reason,
            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
            'status' => 'approved',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم توقيع الجزاء المالي بنجاح وسيتم خصمه في مسير الراتب الشهري.',
            'deduction' => $deduction->load('employee'),
        ]);
    }

    public function updateStatus(Request $request, EmployeeDeduction $deduction): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:approved,cancelled'],
        ]);

        $deduction->update([
            'status' => $request->status,
            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الجزاء المالي بنجاح.',
        ]);
    }
}
