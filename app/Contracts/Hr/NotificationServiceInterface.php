<?php

namespace App\Contracts\Hr;

use App\Models\User;

interface NotificationServiceInterface
{
    /**
     * Get recent notifications and count of unread items for the specified user.
     *
     * @return array{notifications: \Illuminate\Support\Collection, unread_count: int}
     */
    public function getUserNotifications(User $user, int $limit = 20): array;

    /**
     * Mark a specific notification as read for the user.
     */
    public function markNotificationAsRead(User $user, string $id): bool;

    /**
     * Mark all unread notifications as read for the user.
     */
    public function markAllNotificationsAsRead(User $user): bool;
}
