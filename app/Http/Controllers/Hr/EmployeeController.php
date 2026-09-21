<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreEmployeeRequest;
use App\Http\Requests\Hr\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobTitle;
use App\Models\SalaryStructure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $branches = Branch::where('is_active', true)->get();
        $departments = Department::with('jobTitles')->get();

        $query = Employee::with(['branch', 'jobTitle.department', 'currentSalary'])->latest();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                  ->orWhere('employee_code', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('national_id', 'like', "%{$s}%");
            });
        }

        $employees = $query->paginate(15);

        // حساب الإحصائيات السريعة للبطاقات
        $stats = [
            'total' => Employee::count(),
            'active' => Employee::where('status', 'active')->count(),
            'on_leave' => Employee::where('status', 'on_leave')->count(),
            'avg_salary' => (float) SalaryStructure::where('is_current', true)->avg('basic_salary'),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'employees' => $employees,
                'stats' => $stats,
            ]);
        }

        return view('admin.hr.employees', compact('employees', 'branches', 'departments', 'stats'));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $employee = Employee::create([
                'branch_id' => $data['branch_id'],
                'job_title_id' => $data['job_title_id'],
                'employee_code' => $data['employee_code'],
                'full_name' => $data['full_name'],
                'national_id' => $data['national_id'],
                'phone' => $data['phone'],
                'hire_date' => $data['hire_date'],
                'shift_start_time' => $data['shift_start_time'],
                'shift_end_time' => $data['shift_end_time'],
                'grace_period_minutes' => $data['grace_period_minutes'],
                'zkteco_pin' => $data['zkteco_pin'] ?? null,
                'status' => 'active',
            ]);

            // إنشاء هيكل الراتب
            SalaryStructure::create([
                'employee_id' => $employee->id,
                'basic_salary' => $data['basic_salary'],
                'housing_allowance' => $data['housing_allowance'] ?? 0,
                'transport_allowance' => $data['transport_allowance'] ?? 0,
                'other_allowances' => $data['other_allowances'] ?? 0,
                'effective_from' => $data['hire_date'],
                'is_current' => true,
            ]);

            return $employee;
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الموظف بنجاح وهيكل راتبه الأساسي.',
            'employee' => $employee->load(['jobTitle', 'branch', 'currentSalary']),
        ]);
    }

    public function show(Employee $employee): JsonResponse
    {
        $employee->load([
            'branch',
            'jobTitle.department',
            'currentSalary',
            'attendances' => fn($q) => $q->latest()->take(30),
            'deductions' => fn($q) => $q->latest()->take(10),
            'leaves' => fn($q) => $q->latest()->take(5),
            'commissions' => fn($q) => $q->latest()->take(10),
        ]);

        return response()->json($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        DB::transaction(function () use ($request, $employee) {
            $data = $request->validated();
            $employee->update($data);

            if (isset($data['basic_salary'])) {
                // تحديث الراتب الحالي
                $currentSalary = $employee->currentSalary;
                if ($currentSalary) {
                    $currentSalary->update([
                        'basic_salary' => $data['basic_salary'],
                        'housing_allowance' => $data['housing_allowance'] ?? $currentSalary->housing_allowance,
                        'transport_allowance' => $data['transport_allowance'] ?? $currentSalary->transport_allowance,
                        'other_allowances' => $data['other_allowances'] ?? $currentSalary->other_allowances,
                    ]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الموظف بنجاح.',
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();
        return response()->json([
            'success' => true,
            'message' => 'تم أرشفة سجل الموظف بنجاح.',
        ]);
    }
}
