<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->owner = User::where('email', 'admin@alhusseini.com')->firstOrFail();
    $this->token = $this->owner->createToken('Device', ['owner:monitor'])->plainTextToken;
    $this->headers = ['Authorization' => 'Bearer ' . $this->token];

    Employee::query()->forceDelete();
});

test('staff attendance returns summary metrics and employee live presence', function () {
    $jobTitle = JobTitle::first();

    // Create 3 active employees
    $emp1 = Employee::create([
        'full_name'            => 'عصام فني البطاريات',
        'employee_code'        => 'EMP-001',
        'job_title_id'         => $jobTitle->id,
        'branch_id'            => 1,
        'phone'                => '01000000001',
        'shift_start_time'     => '09:00:00',
        'shift_end_time'       => '17:00:00',
        'grace_period_minutes' => 15,
        'hire_date'            => '2026-01-01',
        'status'               => 'active',
    ]);

    $emp2 = Employee::create([
        'full_name'            => 'حسام فني الزيت',
        'employee_code'        => 'EMP-002',
        'job_title_id'         => $jobTitle->id,
        'branch_id'            => 1,
        'phone'                => '01000000002',
        'shift_start_time'     => '09:00:00',
        'shift_end_time'       => '17:00:00',
        'grace_period_minutes' => 15,
        'hire_date'            => '2026-01-01',
        'status'               => 'active',
    ]);

    $emp3 = Employee::create([
        'full_name'            => 'أمير عامل التجهيز',
        'employee_code'        => 'EMP-003',
        'job_title_id'         => $jobTitle->id,
        'branch_id'            => 1,
        'phone'                => '01000000003',
        'shift_start_time'     => '09:00:00',
        'shift_end_time'       => '17:00:00',
        'grace_period_minutes' => 15,
        'hire_date'            => '2026-01-01',
        'status'               => 'active',
    ]);

    // Record attendance: emp1 is present, emp2 is late, emp3 has no record (absent)
    Attendance::create([
        'employee_id'  => $emp1->id,
        'work_date'    => Carbon::today(),
        'check_in'     => Carbon::today()->setTime(9, 5),
        'late_minutes' => 0,
        'status'       => 'present',
    ]);

    Attendance::create([
        'employee_id'  => $emp2->id,
        'work_date'    => Carbon::today(),
        'check_in'     => Carbon::today()->setTime(9, 30),
        'late_minutes' => 30,
        'status'       => 'late',
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson(route('api.v1.owner.staff.attendance'));

    $response->assertOk()
        ->assertJson([
            'status'  => 'success',
            'summary' => [
                'total_employees' => 3,
                'present_count'   => 2, // 1 present + 1 late
                'late_count'      => 1,
                'absent_count'    => 1,
                'attendance_rate' => '67%',
            ],
        ]);

    $data = collect($response->json('data'));
    expect($data->firstWhere('employee_code', 'EMP-001')['status'])->toBe('present')
        ->and($data->firstWhere('employee_code', 'EMP-002')['status'])->toBe('late')
        ->and($data->firstWhere('employee_code', 'EMP-003')['status'])->toBe('absent');
});
