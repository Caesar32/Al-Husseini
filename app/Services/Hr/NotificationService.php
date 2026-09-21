<?php

namespace App\Services\Hr;

use App\Contracts\Hr\NotificationServiceInterface;
use App\Models\User;

class NotificationService implements NotificationServiceInterface
{
    /**
     * {@inheritDoc}
     */
    public function getUserNotifications(User $user, int $limit = 20): array
    {
        return [
            'notifications' => $user->notifications()->latest()->take($limit)->get(),
            'unread_count' => $user->unreadNotifications()->count(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function markNotificationAsRead(User $user, string $id): bool
    {
        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function markAllNotificationsAsRead(User $user): bool
    {
        $user->unreadNotifications->markAsRead();
        return true;
    }
}
