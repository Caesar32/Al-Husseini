<?php

namespace App\Http\Controllers\Hr;

use App\Contracts\Hr\DeductionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDeductionRequest;
use App\Http\Requests\Hr\UpdateDeductionStatusRequest;
use App\Models\EmployeeDeduction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DeductionController extends Controller
{
    public function __construct(
        protected DeductionServiceInterface $deductionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $deductions = $this->deductionService->getPaginatedDeductions();

        return response()->json($deductions);
    }

    public function store(StoreDeductionRequest $request): JsonResponse
    {
        $deduction = $this->deductionService->applyDeduction(
            $request->validated(),
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم توقيع الجزاء المالي بنجاح وسيتم خصمه في مسير الراتب الشهري.',
            'deduction' => $deduction->load('employee'),
        ]);
    }

    public function updateStatus(UpdateDeductionStatusRequest $request, EmployeeDeduction $deduction): JsonResponse
    {
        try {
            $this->deductionService->updateDeductionStatus(
                $deduction,
                $request->validated('status'),
                Auth::id()
            );
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة الجزاء المالي بنجاح.',
        ]);
    }
}
