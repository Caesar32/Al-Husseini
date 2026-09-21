<?php

namespace App\Services\Hr;

use App\Contracts\Hr\EmployeeServiceInterface;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\SalaryStructure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EmployeeService implements EmployeeServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function getPaginatedEmployees(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Employee::with(['branch', 'jobTitle.department', 'currentSalary'])->latest('id');

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                  ->orWhere('employee_code', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('national_id', 'like', "%{$s}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function getEmployeeStats(): array
    {
        return [
            'total' => Employee::count(),
            'active' => Employee::where('status', 'active')->count(),
            'on_leave' => Employee::where('status', 'on_leave')->count(),
            'avg_salary' => (float) SalaryStructure::where('is_current', true)->avg('basic_salary'),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getFormData(): array
    {
        return [
            'branches' => Branch::where('is_active', true)->get(),
            'departments' => Department::with('jobTitles')->get(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
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
    }

    /**
     * {@inheritDoc}
     */
    public function updateEmployee(Employee $employee, array $data): bool
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->update($data);

            if (isset($data['basic_salary'])) {
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

            return true;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getEmployeeDetails(Employee $employee): Employee
    {
        return $employee->load([
            'branch',
            'jobTitle.department',
            'currentSalary',
            'attendances' => fn($q) => $q->latest()->take(30),
            'deductions' => fn($q) => $q->latest()->take(10),
            'leaves' => fn($q) => $q->latest()->take(5),
            'commissions' => fn($q) => $q->latest()->take(10),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteEmployee(Employee $employee): bool
    {
        return (bool) $employee->delete();
    }
}
