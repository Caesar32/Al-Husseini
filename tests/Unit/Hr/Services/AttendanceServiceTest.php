<?php

use App\Contracts\Hr\AttendanceServiceInterface;
use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->service = app(AttendanceServiceInterface::class);
});

test('on-time punch within grace period marks status as present without lateness', function () {
    $emp = Employee::first();
    // Shift start: 09:00, Grace period: 15 mins -> Punch at 09:10
    $punchTime = Carbon::parse('2026-09-21 09:10:00');

    $att = $this->service->recordPunch($emp->id, $punchTime, 'check_in');

    expect($att)->toBeInstanceOf(Attendance::class)
        ->and($att->status)->toBe('present')
        ->and($att->late_minutes)->toBe(0);
});

test('late punch after grace period calculates exact lateness minutes and marks status as late', function () {
    $emp = Employee::first();
    // Shift start: 09:00, Grace period: 15 mins -> Punch at 09:40 (40 mins late)
    $punchTime = Carbon::parse('2026-09-21 09:40:00');

    $att = $this->service->recordPunch($emp->id, $punchTime, 'check_in');

    expect($att)->toBeInstanceOf(Attendance::class)
        ->and($att->status)->toBe('late')
        ->and($att->late_minutes)->toBe(40);
});

test('check-out before shift end calculates early leave minutes', function () {
    $emp = Employee::first();
    $date = '2026-09-21';
    // Check-in first at 09:00
    $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:00:00"), 'check_in');

    // Shift end: 17:00 -> Punch out at 16:30 (30 mins early leave)
    $punchTime = Carbon::parse("{$date} 16:30:00");
    $att = $this->service->recordPunch($emp->id, $punchTime, 'check_out');

    expect($att->early_leave_minutes)->toBe(30);
});

test('check-out after shift end calculates overtime hours when 30 mins or more', function () {
    $emp = Employee::first();
    $date = '2026-09-21';
    // Check-in first at 09:00
    $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:00:00"), 'check_in');

    // Shift end: 17:00 -> Punch out at 18:30 (90 mins = 1.5 hours overtime)
    $punchTime = Carbon::parse("{$date} 18:30:00");
    $att = $this->service->recordPunch($emp->id, $punchTime, 'check_out');

    expect((float)$att->overtime_hours)->toBe(1.5);
});

test('duplicate check-in within 5 minutes is debounced and maintains original check-in time', function () {
    $emp = Employee::first();
    $date = '2026-09-21';

    $firstPunch = $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:00:00"), 'check_in');
    $secondPunch = $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:02:30"), 'check_in');

    expect($firstPunch->id)->toBe($secondPunch->id)
        ->and($secondPunch->check_in->format('H:i:s'))->toBe('09:00:00');
});

test('duplicate check-in after 5 minutes throws exception preventing duplicate check-in', function () {
    $emp = Employee::first();
    $date = '2026-09-21';

    $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:00:00"), 'check_in');

    expect(fn() => $this->service->recordPunch($emp->id, Carbon::parse("{$date} 10:30:00"), 'check_in'))
        ->toThrow(Exception::class, 'مسجل حضور بالفعل اليوم');
});

test('check-out without check-in throws exception', function () {
    $emp = Employee::first();
    $date = '2026-09-21';

    expect(fn() => $this->service->recordPunch($emp->id, Carbon::parse("{$date} 17:00:00"), 'check_out'))
        ->toThrow(Exception::class, 'لا يمكن تسجيل انصراف لموظف لم يسجل حضوره اليوم');
});

test('check-out before check-in time throws exception', function () {
    $emp = Employee::first();
    $date = '2026-09-21';

    $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:00:00"), 'check_in');

    expect(fn() => $this->service->recordPunch($emp->id, Carbon::parse("{$date} 08:30:00"), 'check_out'))
        ->toThrow(Exception::class, 'لا يمكن أن يسبق أو يساوي وقت الحضور المسجل');
});

test('admin markAbsent resets punch times and sets status to absent', function () {
    $emp = Employee::first();
    $date = '2026-09-21';

    $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:00:00"), 'check_in');
    $absentRecord = $this->service->markAbsent($emp->id, $date, 'لم يستكمل العمل');

    expect($absentRecord->status)->toBe('absent')
        ->and($absentRecord->check_in)->toBeNull();
});

test('service returns daily attendance records and stats accurately', function () {
    $date = '2026-09-21';
    $emp = Employee::first();
    $this->service->recordPunch($emp->id, Carbon::parse("{$date} 09:00:00"), 'check_in');

    $records = $this->service->getDailyAttendance($date);
    expect($records->count())->toBeGreaterThanOrEqual(1);

    $stats = $this->service->getDailyStats($date);
    expect($stats)->toHaveKeys(['total_expected', 'present', 'late', 'absent'])
        ->and($stats['present'])->toBeGreaterThanOrEqual(1);
});
