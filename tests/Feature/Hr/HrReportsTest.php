<?php

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->admin = User::first();

    $this->late = Employee::factory()->create(['full_name' => 'موظف متأخر للتقرير']);
    $this->absent = Employee::factory()->create(['full_name' => 'موظف غائب للتقرير']);

    Attendance::create([
        'employee_id' => $this->late->id,
        'work_date' => '2026-09-15',
        'check_in' => '2026-09-15 10:00:00',
        'status' => 'late',
        'late_minutes' => 60,
        'source' => 'manual',
    ]);
    Attendance::create([
        'employee_id' => $this->absent->id,
        'work_date' => '2026-09-15',
        'status' => 'absent',
        'source' => 'manual',
    ]);

    EmployeeDeduction::create([
        'employee_id' => $this->late->id,
        'deduction_date' => '2026-09-15',
        'amount' => 50,
        'reason' => 'approved deduction',
        'status' => 'approved',
        'approved_by' => $this->admin->id,
    ]);
    EmployeeDeduction::create([
        'employee_id' => $this->late->id,
        'deduction_date' => '2026-09-15',
        'amount' => 999,
        'reason' => 'pending deduction must not count',
        'status' => 'pending',
    ]);
});

function reportRow(array $rows, int $employeeId): array
{
    return collect($rows)->firstWhere('employee.id', $employeeId);
}

test('reports page renders database departments and no browser mock store', function () {
    $department = Department::first();

    $response = $this->actingAs($this->admin)->get(route('admin.hr.reports'))->assertOk();

    expect($response->getContent())
        ->toContain('<option value="' . e($department->name) . '">')
        ->not->toContain('AlHusseiniHR.');
});

test('daily report aggregates attendance and effective deductions from the database', function () {
    $data = $this->actingAs($this->admin)
        ->getJson(route('admin.hr.reports.daily', ['date' => '2026-09-15']))
        ->assertOk()
        ->json();

    $late = reportRow($data['employees'], $this->late->id);
    $absent = reportRow($data['employees'], $this->absent->id);

    expect($late['status'])->toBe('late')
        ->and($late['latenessMinutes'])->toBe(60)
        ->and($late['punchIn'])->toBe('10:00')
        ->and((float) $late['totalDeductions'])->toBe(50.0)
        ->and($absent['status'])->toBe('absent')
        ->and($data['summary']['lateCount'])->toBe(1)
        ->and($data['summary']['absentCount'])->toBe(1)
        ->and((float) $data['summary']['totalDeductionsAmount'])->toBe(50.0)
        ->and($data['summary']['deductionsCount'])->toBe(1);
});

test('monthly report aggregates per employee including approved leave days', function () {
    EmployeeLeave::create([
        'employee_id' => $this->absent->id,
        'leave_type' => 'annual',
        'start_date' => '2026-09-20',
        'end_date' => '2026-09-22',
        'days_count' => 3,
        'status' => 'approved',
    ]);

    $data = $this->actingAs($this->admin)
        ->getJson(route('admin.hr.reports.monthly', ['year' => 2026, 'month' => 9]))
        ->assertOk()
        ->json();

    $late = reportRow($data['staffReport'], $this->late->id);
    $absent = reportRow($data['staffReport'], $this->absent->id);

    expect($data['startDate'])->toBe('2026-09-01')
        ->and($data['endDate'])->toBe('2026-09-30')
        ->and($late['lateDays'])->toBe(1)
        ->and($late['totalLateMinutes'])->toBe(60)
        ->and((float) $late['totalDeductionsAmount'])->toBe(50.0)
        ->and($absent['absentDays'])->toBe(1)
        ->and($absent['leaveDays'])->toBe(3)
        ->and($data['summary']['totalLeaveDays'])->toBeGreaterThanOrEqual(3);
});

test('range and employee history endpoints return database data', function () {
    $this->actingAs($this->admin)
        ->getJson(route('admin.hr.reports.range', ['start' => '2026-09-30', 'end' => '2026-09-01']))
        ->assertOk()
        ->assertJsonPath('startDate', '2026-09-01')
        ->assertJsonPath('endDate', '2026-09-30');

    $this->actingAs($this->admin)
        ->getJson(route('admin.hr.reports.employee', $this->late))
        ->assertOk()
        ->assertJsonPath('employee.id', $this->late->id)
        ->assertJsonPath('lateDays', 1)
        ->assertJsonCount(1, 'deductions');
});

test('report endpoints require the reports.hr permission', function () {
    $cashier = User::role('cashier')->first();
    expect($cashier->can('reports.hr'))->toBeFalse();

    $this->actingAs($cashier);
    $this->get(route('admin.hr.reports'))->assertForbidden();
    $this->getJson(route('admin.hr.reports.daily', ['date' => '2026-09-15']))->assertForbidden();
    $this->getJson(route('admin.hr.reports.monthly', ['year' => 2026, 'month' => 9]))->assertForbidden();
    $this->getJson(route('admin.hr.reports.range', ['start' => '2026-09-01', 'end' => '2026-09-30']))->assertForbidden();
    $this->getJson(route('admin.hr.reports.employee', $this->late))->assertForbidden();
});

test('report endpoints validate their parameters', function () {
    $this->actingAs($this->admin)
        ->getJson(route('admin.hr.reports.daily', ['date' => 'not-a-date']))
        ->assertStatus(422);
    $this->actingAs($this->admin)
        ->getJson(route('admin.hr.reports.monthly', ['year' => 2026, 'month' => 13]))
        ->assertStatus(422);
});
