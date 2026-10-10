<?php

use App\Models\Branch;
use App\Models\JobTitle;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\EmployeeDeduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);

    $admin = User::where('email', 'admin@alhusseini.com')->first();
    $this->actingAs($admin);
});

test('hr employees page returns a successful view', function () {
    $response = $this->get('/admin/hr/employees');
    $response->assertStatus(200);
});

test('hr employees api returns json list', function () {
    $response = $this->getJson('/admin/hr/employees');
    $response->assertStatus(200)
             ->assertJsonStructure(['employees', 'stats']);
});

test('can create an employee with salary structure', function () {
    $branch = Branch::first();
    $jobTitle = JobTitle::first();

    $code = 'TEST-' . rand(1000, 9999);
    $nationalId = '3000101' . rand(1000000, 9999999);
    $phone = '010' . rand(10000000, 99999999);

    $response = $this->postJson('/admin/hr/employees', [
        'branch_id' => $branch->id,
        'department' => $jobTitle->department->name,
        'job_title' => $jobTitle->title,
        'employee_code' => $code,
        'full_name' => 'موظف تجريبي جديد',
        'national_id' => $nationalId,
        'phone' => $phone,
        'hire_date' => '2026-09-01',
        'shift_start_time' => '09:00',
        'shift_end_time' => '17:00',
        'grace_period_minutes' => 15,
        'basic_salary' => 12000,
        'housing_allowance' => 1500,
        'transport_allowance' => 800,
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $this->assertDatabaseHas('employees', [
        'employee_code' => $code,
        'full_name' => 'موظف تجريبي جديد',
    ]);

    $this->assertDatabaseHas('salary_structures', [
        'basic_salary' => 12000,
        'housing_allowance' => 1500,
    ]);
});

test('can update an employee', function () {
    $emp = Employee::first();

    $response = $this->putJson("/admin/hr/employees/{$emp->id}", [
        'branch_id' => $emp->branch_id,
        'department' => $emp->jobTitle->department->name,
        'job_title' => $emp->jobTitle->title,
        'employee_code' => $emp->employee_code,
        'full_name' => 'الاسم المحدث للموظف',
        'national_id' => $emp->national_id,
        'phone' => $emp->phone,
        'hire_date' => $emp->hire_date->toDateString(),
        'shift_start_time' => '08:30',
        'shift_end_time' => '16:30',
        'grace_period_minutes' => 10,
        'status' => 'active',
        'basic_salary' => 14000,
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $this->assertDatabaseHas('employees', [
        'id' => $emp->id,
        'full_name' => 'الاسم المحدث للموظف',
    ]);
});

test('punch records check in and calculates lateness properly', function () {
    $emp = Employee::first();

    $response = $this->postJson('/admin/hr/attendance/punch', [
        'employee_id' => $emp->id,
        'timestamp' => '2026-09-21 09:45:00',
        'punch_state' => 'check_in',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $attendance = Attendance::where('employee_id', $emp->id)->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->status)->toBe('late')
        ->and($attendance->late_minutes)->toBeGreaterThan(0);
});

test('manager can apply deduction to an employee', function () {
    $emp = Employee::first();

    $response = $this->postJson('/admin/hr/deductions', [
        'employee_id' => $emp->id,
        'amount' => 200,
        'reason' => 'تأخير غير مبرر عن فتح المحل',
        'deduction_date' => '2026-09-21',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $this->assertDatabaseHas('employee_deductions', [
        'employee_id' => $emp->id,
        'amount' => 200,
    ]);
});

test('can cancel an employee deduction', function () {
    $emp = Employee::first();
    $ded = EmployeeDeduction::create([
        'employee_id' => $emp->id,
        'amount' => 300,
        'reason' => 'خصم للتجربة',
        'deduction_date' => '2026-09-21',
        'status' => 'approved',
    ]);

    $response = $this->postJson("/admin/hr/deductions/{$ded->id}/status", [
        'status' => 'cancelled',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    expect($ded->fresh()->status)->toBe('cancelled');
});

test('payroll can be generated, approved, and disbursed', function () {
    $branch = Branch::first();

    // 1. Generate
    $genResponse = $this->postJson('/admin/hr/payroll/generate', [
        'branch_id' => $branch->id,
        'year' => 2026,
        'month' => 9,
    ]);

    $genResponse->assertStatus(200)
                ->assertJson(['success' => true]);

    $payrollId = $genResponse->json('payroll_id');
    $payroll = Payroll::find($payrollId);

    expect($payroll)->not->toBeNull()
        ->and($payroll->status)->toBe('draft');

    // 2. Approve
    $apprResponse = $this->postJson("/admin/hr/payroll/{$payrollId}/approve");
    $apprResponse->assertStatus(200)
                 ->assertJson(['success' => true]);
    expect($payroll->fresh()->status)->toBe('approved');

    // 3. Disburse
    $disbResponse = $this->postJson("/admin/hr/payroll/{$payrollId}/disburse");
    $disbResponse->assertStatus(200)
                 ->assertJson(['success' => true]);
    expect($payroll->fresh()->status)->toBe('disbursed');
});

test('admin notifications api works correctly', function () {
    $user = User::first();

    $response = $this->actingAs($user)->getJson('/admin/hr/notifications');
    $response->assertStatus(200)
             ->assertJsonStructure(['unread_count', 'notifications']);

    $readAllResponse = $this->actingAs($user)->postJson('/admin/hr/notifications/read-all');
    $readAllResponse->assertStatus(200)
                    ->assertJson(['success' => true]);
});

test('leave workflow creates request and manager can approve it', function () {
    $emp = Employee::first();

    $response = $this->postJson('/admin/hr/leaves', [
        'employee_id' => $emp->id,
        'leave_type' => 'annual',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-05',
        'reason' => 'إجازة سنوية اعتيادية',
    ]);

    $response->assertStatus(200)
             ->assertJson(['success' => true]);

    $leave = \App\Models\EmployeeLeave::where('employee_id', $emp->id)->first();
    expect($leave)->not->toBeNull()
        ->and($leave->days_count)->toBe(5)
        ->and($leave->status)->toBe('pending');

    // Approve leave
    $approveResponse = $this->postJson("/admin/hr/leaves/{$leave->id}/status", [
        'status' => 'approved',
        'action_notes' => 'معتمد من الإدارة',
    ]);

    $approveResponse->assertStatus(200)
                    ->assertJson(['success' => true]);

    expect($leave->fresh()->status)->toBe('approved');
    expect($emp->fresh()->status)->toBe('on_leave');
});

test('hr service interfaces resolve correctly from container via SOLID provider', function () {
    expect(app(\App\Contracts\Hr\EmployeeServiceInterface::class))->toBeInstanceOf(\App\Services\Hr\EmployeeService::class)
        ->and(app(\App\Contracts\Hr\AttendanceServiceInterface::class))->toBeInstanceOf(\App\Services\Hr\AttendanceService::class)
        ->and(app(\App\Contracts\Hr\PayrollServiceInterface::class))->toBeInstanceOf(\App\Services\Hr\PayrollService::class)
        ->and(app(\App\Contracts\Hr\DeductionServiceInterface::class))->toBeInstanceOf(\App\Services\Hr\DeductionService::class)
        ->and(app(\App\Contracts\Hr\LeaveServiceInterface::class))->toBeInstanceOf(\App\Services\Hr\LeaveService::class)
        ->and(app(\App\Contracts\Hr\NotificationServiceInterface::class))->toBeInstanceOf(\App\Services\Hr\NotificationService::class);
});

