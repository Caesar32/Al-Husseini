<?php

use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use App\Models\EmployeePayrollDebt;
use App\Models\PayrollItem;
use App\Models\SalaryStructure;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->admin = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->actingAs($this->admin);
});

/** September 2026 has 30 days; the working day norm is 26, so a day's absence on 5200 is 200. */
function policyEmployee(Branch $branch, string $hireDate, float $basic = 5200, float $allowances = 0): Employee
{
    $employee = Employee::factory()->create(['branch_id' => $branch->id, 'hire_date' => $hireDate, 'status' => 'active']);
    SalaryStructure::where('employee_id', $employee->id)->delete();
    SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'basic_salary' => $basic,
        'housing_allowance' => $allowances,
        'transport_allowance' => 0,
        'other_allowances' => 0,
        'effective_from' => '2020-01-01',
        'is_current' => true,
    ]);

    return $employee;
}

function policyAttendance(Employee $employee, int $firstDay, int $days): void
{
    foreach (range($firstDay, $firstDay + $days - 1) as $day) {
        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => sprintf('2026-09-%02d', $day),
            'status' => 'present',
        ]);
    }
}

function policyItem(Branch $branch, Employee $employee): PayrollItem
{
    return app(PayrollServiceInterface::class)->generateMonthlyPayroll($branch->id, 2026, 9)
        ->items()->where('employee_id', $employee->id)->firstOrFail();
}

test('a full month of absence deducts the basic salary exactly, with no debt', function () {
    $employee = policyEmployee($this->branch, '2020-01-01');

    $item = policyItem($this->branch, $employee);

    expect((float) $item->basic_salary)->toBe(5200.0)
        ->and($item->absent_days)->toBe(26)
        ->and((float) $item->total_deduction)->toBe(5200.0)
        ->and((float) $item->net_salary)->toBe(0.0)
        ->and((float) $item->carried_debt)->toBe(0.0)
        ->and(EmployeePayrollDebt::where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('a full month of absence takes the basic only and leaves the allowances', function () {
    $employee = policyEmployee($this->branch, '2020-01-01', 5200, 800);

    $item = policyItem($this->branch, $employee);

    expect((float) $item->total_deduction)->toBe(5200.0)
        ->and((float) $item->net_salary)->toBe(800.0);
});

test('a batch that nets to zero through absence alone can be approved', function () {
    $employee = policyEmployee($this->branch, '2020-01-01');
    $service = app(PayrollServiceInterface::class);
    $payroll = $service->generateMonthlyPayroll($this->branch->id, 2026, 9);

    expect($service->evaluateConsistency($payroll->fresh())['consistent'])->toBeTrue()
        ->and($service->approvePayroll($payroll->fresh(), $this->admin->id))->toBeTrue();
});

test('one day of absence is the basic divided by the working days', function () {
    $employee = policyEmployee($this->branch, '2020-01-01');
    policyAttendance($employee, 1, 25);

    $item = policyItem($this->branch, $employee);

    expect($item->absent_days)->toBe(1)
        ->and((float) $item->total_deduction)->toBe(200.0)
        ->and((float) $item->net_salary)->toBe(5000.0);
});

test('unpaid leave cannot push a full month of absence past the basic', function () {
    $employee = policyEmployee($this->branch, '2020-01-01');
    EmployeeLeave::factory()->create([
        'employee_id' => $employee->id,
        'leave_type' => 'unpaid',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'status' => 'approved',
    ]);

    $item = policyItem($this->branch, $employee);

    expect($item->absent_days)->toBe(26)
        ->and((float) $item->total_deduction)->toBe(5200.0)
        ->and((float) $item->carried_debt)->toBe(0.0);
});

test('an employee hired mid-month is paid from the hire date with no earlier absence', function () {
    // 15 of 30 calendar days left: half the norm, half the basic and half the allowances.
    $employee = policyEmployee($this->branch, '2026-09-16', 5200, 800);
    policyAttendance($employee, 16, 13);

    $item = policyItem($this->branch, $employee);

    expect((float) $item->basic_salary)->toBe(2600.0)
        ->and((float) $item->total_allowance)->toBe(400.0)
        ->and($item->absent_days)->toBe(0)
        ->and((float) $item->total_deduction)->toBe(0.0)
        ->and((float) $item->net_salary)->toBe(3000.0);
});

test('a new hire who misses days after the hire date is charged only for those days', function () {
    $employee = policyEmployee($this->branch, '2026-09-16');
    policyAttendance($employee, 16, 10);

    $item = policyItem($this->branch, $employee);

    expect($item->absent_days)->toBe(3)
        ->and((float) $item->total_deduction)->toBe(600.0)
        ->and((float) $item->net_salary)->toBe(2000.0);
});

test('a new hire absent every day since hiring ends at zero without debt', function () {
    $employee = policyEmployee($this->branch, '2026-09-16');

    $item = policyItem($this->branch, $employee);

    expect($item->absent_days)->toBe(13)
        ->and((float) $item->total_deduction)->toBe(2600.0)
        ->and((float) $item->net_salary)->toBe(0.0)
        ->and((float) $item->carried_debt)->toBe(0.0);
});

test('leave and attendance dated before the hire date count for nothing', function () {
    $employee = policyEmployee($this->branch, '2026-09-16');
    policyAttendance($employee, 1, 13);
    EmployeeLeave::factory()->create([
        'employee_id' => $employee->id,
        'leave_type' => 'unpaid',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-15',
        'status' => 'approved',
    ]);

    $item = policyItem($this->branch, $employee);

    expect($item->absent_days)->toBe(13)
        ->and((float) $item->total_deduction)->toBe(2600.0);
});

test('hire date boundaries', function (string $hireDate, ?float $basic) {
    $employee = policyEmployee($this->branch, $hireDate);

    $item = app(PayrollServiceInterface::class)->generateMonthlyPayroll($this->branch->id, 2026, 9)
        ->items()->where('employee_id', $employee->id)->first();

    expect($item === null ? null : (float) $item->basic_salary)->toBe($basic);
})->with([
    'before the month' => ['2026-08-31', 5200.0],
    'first of the month' => ['2026-09-01', 5200.0],
    'last of the month' => ['2026-09-30', 200.0],
    'after the month has no payroll item' => ['2026-10-01', null],
]);

test('a penalty beyond the earnings still carries as debt, unlike absence', function () {
    $employee = policyEmployee($this->branch, '2020-01-01');
    EmployeeDeduction::factory()->create([
        'employee_id' => $employee->id,
        'amount' => 300,
        'deduction_date' => '2026-09-10',
        'status' => 'approved',
    ]);

    $item = policyItem($this->branch, $employee);

    expect((float) $item->total_deduction)->toBe(5500.0)
        ->and((float) $item->carried_debt)->toBe(300.0);
});
