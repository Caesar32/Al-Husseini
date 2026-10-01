<?php

use App\Contracts\Hr\AttendanceServiceInterface;
use App\Contracts\Hr\PayrollServiceInterface;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Notifications\EmployeeLateNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->admin = User::first();
    $this->actingAs($this->admin);
    $this->payrollService = app(PayrollServiceInterface::class);
    $this->branch = Branch::factory()->create();
});

function payrollEmployee(Branch $branch, array $attributes = []): Employee
{
    $employee = Employee::factory()->create(array_merge(['branch_id' => $branch->id], $attributes));
    SalaryStructure::factory()->create([
        'employee_id' => $employee->id,
        'basic_salary' => 6000,
        'housing_allowance' => 500,
        'transport_allowance' => 500,
        'other_allowances' => 0,
        'effective_from' => '2026-01-01',
        'is_current' => true,
    ]);

    return $employee;
}

function fullMonthAttendance(Employee $employee, array $overtimeByDay = []): void
{
    // 26 present days in September 2026 (days 5..30).
    foreach (range(5, 30) as $day) {
        Attendance::create([
            'employee_id' => $employee->id,
            'work_date' => sprintf('2026-09-%02d', $day),
            'status' => 'present',
            'overtime_hours' => $overtimeByDay[$day] ?? 0,
        ]);
    }
}

test('a payroll batch with overtime can be approved and disbursed', function () {
    $employee = payrollEmployee($this->branch);
    fullMonthAttendance($employee, [10 => 2, 11 => 2]);

    $payroll = $this->payrollService->generateMonthlyPayroll($this->branch->id, 2026, 9);
    $item = $payroll->items()->where('employee_id', $employee->id)->first();

    // 4 overtime hours * (6000 / 30 / 8) * 1.5 = 150
    expect((float) $item->total_overtime)->toBe(150.0)
        ->and((float) $item->net_salary)->toBe(7150.0);

    expect($this->payrollService->evaluateConsistency($payroll->fresh())['consistent'])->toBeTrue();

    $this->postJson(route('admin.hr.payroll.approve', $payroll))->assertOk();
    $this->postJson(route('admin.hr.payroll.disburse', $payroll))->assertOk();

    expect($payroll->fresh()->status)->toBe('disbursed');
});

test('payroll register shows a consistent overtime batch as approvable, not needing review', function () {
    $employee = payrollEmployee($this->branch);
    fullMonthAttendance($employee, [10 => 2]);
    $payroll = $this->payrollService->generateMonthlyPayroll($this->branch->id, 2026, 9);

    $html = $this->get(route('admin.hr.payroll'))->assertOk()->getContent();

    expect($html)->toContain('data-needs-review="0"')
        ->not->toContain('data-needs-review="1"')
        ->toContain('btn-approve-payroll" data-id="' . $payroll->id . '"');
});

test('payroll register flags a batch whose stored header no longer matches its items', function () {
    $employee = payrollEmployee($this->branch);
    fullMonthAttendance($employee);
    $payroll = $this->payrollService->generateMonthlyPayroll($this->branch->id, 2026, 9);
    $payroll->update(['total_net' => 1]);

    $html = $this->get(route('admin.hr.payroll'))->assertOk()->getContent();

    expect($html)->toContain('data-needs-review="1"')
        ->not->toContain('btn-approve-payroll" data-id="' . $payroll->id . '"');
});

test('payroll details page renders stored items as HTML', function () {
    $employee = payrollEmployee($this->branch);
    fullMonthAttendance($employee, [10 => 2]);
    $payroll = $this->payrollService->generateMonthlyPayroll($this->branch->id, 2026, 9);

    $this->get(route('admin.hr.payroll.show', $payroll))
        ->assertOk()
        ->assertSee($employee->full_name)
        ->assertSee('7,075.00');

    $this->getJson(route('admin.hr.payroll.show', $payroll))
        ->assertOk()
        ->assertJsonPath('consistency.consistent', true)
        ->assertJsonPath('items.0.employee_id', $employee->id);
});

test('employees on approved leave are included in payroll generation', function () {
    $employee = payrollEmployee($this->branch, ['status' => 'on_leave']);
    fullMonthAttendance($employee);

    $payroll = $this->payrollService->generateMonthlyPayroll($this->branch->id, 2026, 9);

    expect($payroll->items()->where('employee_id', $employee->id)->exists())->toBeTrue();
});

test('a late check-in during approved leave keeps holiday status and creates no late penalty', function () {
    $employee = payrollEmployee($this->branch);
    EmployeeLeave::create([
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_date' => '2026-09-14',
        'end_date' => '2026-09-16',
        'days_count' => 3,
        'status' => 'approved',
    ]);

    $attendance = app(AttendanceServiceInterface::class)
        ->recordPunch($employee->id, Carbon::parse('2026-09-15 10:30:00'), 'check_in');

    expect($attendance->fresh()->status)->toBe('holiday')
        ->and($attendance->fresh()->late_minutes)->toBe(0)
        ->and(EmployeeDeduction::where('attendance_id', $attendance->id)->exists())->toBeFalse();
});

test('the late notification is sent once per day, not again on check-out', function () {
    Notification::fake();
    // Late alerts go to admins whose branch is null or matches the employee's branch.
    $employee = payrollEmployee(Branch::findOrFail($this->admin->branch_id));
    $service = app(AttendanceServiceInterface::class);

    $service->recordPunch($employee->id, Carbon::parse('2026-09-15 10:00:00'), 'check_in');
    $service->recordPunch($employee->id, Carbon::parse('2026-09-15 18:00:00'), 'check_out');

    Notification::assertSentToTimes($this->admin, EmployeeLateNotification::class, 1);
});

test('disbursal marks exactly the withheld deductions as applied and they become immutable', function () {
    $employee = payrollEmployee($this->branch);
    fullMonthAttendance($employee);
    $withheld = EmployeeDeduction::create([
        'employee_id' => $employee->id,
        'deduction_date' => '2026-09-20',
        'amount' => 100,
        'reason' => 'withheld deduction',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
    ]);

    $payroll = $this->payrollService->generateMonthlyPayroll($this->branch->id, 2026, 9);
    expect((float) $payroll->items()->first()->total_deduction)->toBe(100.0);

    // Approved after generation: not part of this payroll, must not be marked applied.
    $late = EmployeeDeduction::create([
        'employee_id' => $employee->id,
        'deduction_date' => '2026-09-25',
        'amount' => 40,
        'reason' => 'approved after generation',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
    ]);

    $this->payrollService->approvePayroll($payroll, $this->admin->id);
    $this->payrollService->disbursePayroll($payroll);

    expect($withheld->fresh()->status)->toBe('applied')
        ->and($late->fresh()->status)->toBe('approved');

    $this->postJson(route('admin.hr.deductions.status', $withheld), ['status' => 'cancelled'])
        ->assertStatus(422);
    expect($withheld->fresh()->status)->toBe('applied');
});
