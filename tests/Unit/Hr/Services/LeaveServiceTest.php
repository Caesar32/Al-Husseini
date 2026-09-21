<?php

use App\Contracts\Hr\LeaveServiceInterface;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\User;
use App\Notifications\LeaveRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->service = app(LeaveServiceInterface::class);
});

test('service applies for leave, calculates days and notifies administrators', function () {
    Notification::fake();

    $emp = Employee::first();

    $leave = $this->service->applyForLeave([
        'employee_id' => $emp->id,
        'leave_type' => 'annual',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-04',
        'reason' => 'إجازة عائلية',
    ]);

    expect($leave)->toBeInstanceOf(EmployeeLeave::class)
        ->and($leave->days_count)->toBe(4)
        ->and($leave->status)->toBe('pending');

    Notification::assertSentTo(
        User::role(['super-admin', 'branch-manager'])->get(),
        LeaveRequestedNotification::class
    );
});

test('approving leave updates leave status and synchronizes employee status to on_leave', function () {
    $emp = Employee::first();
    $admin = User::first();

    $leave = $this->service->applyForLeave([
        'employee_id' => $emp->id,
        'leave_type' => 'annual',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-04',
        'reason' => 'إجازة',
    ]);

    $updated = $this->service->updateLeaveStatus($leave, 'approved', 'موافق عليها', $admin->id);

    expect($updated)->toBeTrue();
    expect($leave->fresh()->status)->toBe('approved')
        ->and($leave->fresh()->actioned_by)->toBe($admin->id)
        ->and($leave->fresh()->action_notes)->toBe('موافق عليها');

    expect($emp->fresh()->status)->toBe('on_leave');
});

test('rejecting leave updates status to rejected without changing employee status', function () {
    $emp = Employee::first();
    $emp->update(['status' => 'active']);

    $leave = $this->service->applyForLeave([
        'employee_id' => $emp->id,
        'leave_type' => 'unpaid',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-02',
    ]);

    $this->service->updateLeaveStatus($leave, 'rejected', 'ضغط عمل بالفرع');

    expect($leave->fresh()->status)->toBe('rejected');
    expect($emp->fresh()->status)->toBe('active');
});
