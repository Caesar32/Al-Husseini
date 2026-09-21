<?php

use App\Contracts\Hr\NotificationServiceInterface;
use App\Models\User;
use App\Models\Employee;
use App\Models\Attendance;
use App\Notifications\EmployeeLateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\InitialDataSeeder::class);
    $this->service = app(NotificationServiceInterface::class);
});

test('service returns notifications and unread count for user', function () {
    $user = User::first();
    $emp = Employee::first();
    $att = Attendance::first() ?? Attendance::create([
        'employee_id' => $emp->id,
        'work_date' => '2026-09-21',
        'status' => 'late',
        'late_minutes' => 20,
    ]);

    $user->notify(new EmployeeLateNotification($att));

    $data = $this->service->getUserNotifications($user);

    expect($data)->toHaveKeys(['notifications', 'unread_count'])
        ->and($data['unread_count'])->toBeGreaterThanOrEqual(1);
});

test('service marks single notification as read', function () {
    $user = User::first();
    $emp = Employee::first();
    $att = Attendance::first() ?? Attendance::create([
        'employee_id' => $emp->id,
        'work_date' => '2026-09-21',
        'status' => 'late',
        'late_minutes' => 20,
    ]);

    $user->notify(new EmployeeLateNotification($att));
    $notification = $user->unreadNotifications->first();

    $marked = $this->service->markNotificationAsRead($user, $notification->id);

    expect($marked)->toBeTrue();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('service marks all user notifications as read', function () {
    $user = User::first();
    $emp = Employee::first();
    $att = Attendance::first() ?? Attendance::create([
        'employee_id' => $emp->id,
        'work_date' => '2026-09-21',
        'status' => 'late',
        'late_minutes' => 20,
    ]);

    $user->notify(new EmployeeLateNotification($att));

    $this->service->markAllNotificationsAsRead($user);

    expect($user->fresh()->unreadNotifications->count())->toBe(0);
});
