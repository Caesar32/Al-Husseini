<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The late-punch dialog showed "undefined" for punch time and delay because the screens read
// check_in_time / check_out_time / lateness_minutes, which the API never returns.

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);
    $this->actingAs(User::where('email', 'admin@alhusseini.com')->firstOrFail());

    $this->employee = Employee::factory()->create([
        'branch_id' => Branch::where('code', 'MAIN')->value('id'),
        'shift_start_time' => '09:00:00',
        'grace_period_minutes' => 15,
    ]);
});

test('a late check-in response carries the fields the attendance screen reads', function () {
    $response = $this->postJson(route('admin.hr.attendance.punch'), [
        'employee_id' => $this->employee->id,
        'timestamp' => '2026-09-15 10:00:00',
        'punch_state' => 'check_in',
    ])->assertOk();

    $attendance = $response->json('attendance');

    expect($attendance['status'])->toBe('late')
        ->and($attendance['late_minutes'])->toBe(60)
        ->and(substr($attendance['check_in'], 11, 5))->toBe('10:00')
        ->and($attendance['employee']['full_name'])->toBe($this->employee->full_name)
        ->and($attendance)->not->toHaveKeys(['check_in_time', 'lateness_minutes']);
});

test('a check-out response carries check_out', function () {
    $this->postJson(route('admin.hr.attendance.punch'), [
        'employee_id' => $this->employee->id, 'timestamp' => '2026-09-15 09:00:00', 'punch_state' => 'check_in',
    ])->assertOk();

    $attendance = $this->postJson(route('admin.hr.attendance.punch'), [
        'employee_id' => $this->employee->id, 'timestamp' => '2026-09-15 17:05:00', 'punch_state' => 'check_out',
    ])->assertOk()->json('attendance');

    expect(substr($attendance['check_out'], 11, 5))->toBe('17:05');
});

test('hr screens only read attendance fields the API returns', function (string $routeName) {
    $html = $this->get(route($routeName))->assertOk()->getContent();

    expect($html)
        ->not->toContain('check_in_time')
        ->not->toContain('check_out_time')
        ->not->toContain('lateness_minutes');
})->with(['admin.hr.attendance', 'admin.hr.employees']);
