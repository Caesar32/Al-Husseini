<?php

use App\Jobs\DispatchOwnerPushNotification;
use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(InitialDataSeeder::class);

    $this->owner = User::where('email', 'admin@alhusseini.com')->firstOrFail();
});

test('push notification job can be dispatched and queued', function () {
    Queue::fake();

    DispatchOwnerPushNotification::dispatch(
        'إغلاق وردية الكاشير',
        'تم إغلاق وردية كاشير اليوم بإجمالي مبيعات 34,500 ج.م',
        ['type' => 'shift_closed', 'shift_id' => 1]
    );

    Queue::assertPushed(DispatchOwnerPushNotification::class, function ($job) {
        return $job->title === 'إغلاق وردية الكاشير'
            && $job->data['type'] === 'shift_closed';
    });
});

test('push notification job executes and finds owner devices', function () {
    // Register 2 devices for the owner
    UserDevice::create([
        'user_id'      => $this->owner->id,
        'platform'     => 'ios',
        'device_token' => 'apns-token-owner-iphone',
        'device_name'  => 'Ahmed iPhone 16 Pro',
    ]);

    UserDevice::create([
        'user_id'      => $this->owner->id,
        'platform'     => 'android',
        'device_token' => 'fcm-token-owner-tablet',
        'device_name'  => 'Samsung Tablet',
    ]);

    $job = new DispatchOwnerPushNotification(
        'فاتورة استثنائية',
        'تم إصدار فاتورة بقيمة 18,000 ج.م',
        ['invoice_id' => 101]
    );

    // Should execute handle() with zero errors
    $job->handle();

    expect(UserDevice::where('user_id', $this->owner->id)->count())->toBe(2);
});

test('push notification job is dispatched only after the surrounding transaction commits (B4)', function () {
    expect((new DispatchOwnerPushNotification('t', 'b'))->afterCommit)->toBeTrue();
});

test('push notification job is not handled when the surrounding transaction rolls back (B4)', function () {
    config(['queue.default' => 'sync']);
    Log::spy();

    try {
        DB::transaction(function () {
            DispatchOwnerPushNotification::dispatch('t', 'b');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Log::shouldNotHaveReceived('info', [Mockery::on(fn ($message) => str_starts_with($message, '[Owner Push]'))]);
});

test('push notification job is handled once the surrounding transaction commits (B4)', function () {
    config(['queue.default' => 'sync']);
    Log::spy();

    DB::transaction(function () {
        DispatchOwnerPushNotification::dispatch('t', 'b');
    });

    Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_starts_with($message, '[Owner Push]'));
});

test('push notification job never logs the device token (B5)', function () {
    UserDevice::create([
        'user_id'      => $this->owner->id,
        'platform'     => 'ios',
        'device_token' => 'apns-secret-device-token-value',
        'device_name'  => 'Owner iPhone',
    ]);
    Log::spy();

    (new DispatchOwnerPushNotification('t', 'b'))->handle();

    Log::shouldHaveReceived('debug')->withArgs(fn ($message) => str_starts_with($message, '[Owner Push] Sent to device ID'));
    Log::shouldNotHaveReceived('debug', [Mockery::on(fn ($message) => str_contains($message, 'apns-secret'))]);
});
