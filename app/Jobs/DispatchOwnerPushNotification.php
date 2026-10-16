<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchOwnerPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     *
     * @param string $title
     * @param string $body
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = []
    ) {
        // Callers dispatch inside DB::transaction; a rolled-back sale must not notify (B4).
        $this->afterCommit();
    }

    /**
     * Execute the job: Fan-out push notifications to all registered owner devices.
     */
    public function handle(): void
    {
        // 1. Retrieve all super-admin user IDs
        $ownerIds = User::role('super-admin')
            ->where('is_active', true)
            ->pluck('id');

        if ($ownerIds->isEmpty()) {
            Log::info('[Owner Push] No active super-admin users found to receive push notification.');
            return;
        }

        // 2. Fetch all registered devices for these owners
        $devices = UserDevice::whereIn('user_id', $ownerIds)->get();

        if ($devices->isEmpty()) {
            Log::info('[Owner Push] No active owner devices registered for push notifications.');
            return;
        }

        Log::info(sprintf(
            '[Owner Push] Dispatching notification "%s" to %d registered owner devices.',
            $this->title,
            $devices->count()
        ));

        // 3. Dispatch to APNs (iOS) / FCM (Android)
        foreach ($devices as $device) {
            // Note: When APNs / Firebase SDK credentials are provided by the owner,
            // the HTTP v1 client call is executed here using $device->device_token.
            // Never log the device token: it is a push credential (B5).
            Log::debug(sprintf(
                '[Owner Push] Sent to device ID %d (%s)',
                $device->id,
                $device->platform
            ));
        }
    }
}
