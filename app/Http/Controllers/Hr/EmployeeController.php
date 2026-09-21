<?php

namespace App\Http\Controllers\Hr;

use App\Contracts\Hr\EmployeeServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeServiceInterface $employeeService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->only(['branch_id', 'status', 'search']);
        $employees = $this->employeeService->getPaginatedEmployees($filters);
        $stats = $this->employeeService->getEmployeeStats();

        if ($request->wantsJson()) {
            return response()->json([
                'employees' => $employees,
                'stats' => $stats,
            ]);
        }

        $formData = $this->employeeService->getFormData();
        $branches = $formData['branches'];
        $departments = $formData['departments'];

        return view('admin.hr.employees', compact('employees', 'branches', 'departments', 'stats'));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->createEmployee($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الموظف بنجاح وهيكل راتبه الأساسي.',
            'employee' => $employee->load(['jobTitle', 'branch', 'currentSalary']),
        ]);
    }

    public function show(Employee $employee): JsonResponse
    {
        $detailedEmployee = $this->employeeService->getEmployeeDetails($employee);

        return response()->json($detailedEmployee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->employeeService->updateEmployee($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الموظف بنجاح.',
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->employeeService->deleteEmployee($employee);

        return response()->json([
            'success' => true,
            'message' => 'تم أرشفة سجل الموظف بنجاح.',
        ]);
    }
}
