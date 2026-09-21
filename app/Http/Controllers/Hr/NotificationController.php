<?php

namespace App\Http\Controllers\Hr;

use App\Contracts\Hr\NotificationServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationServiceInterface $notificationService
    ) {}

    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }

        $result = $this->notificationService->getUserNotifications($user);

        return response()->json($result);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $user = Auth::user();
        if ($user) {
            $this->notificationService->markNotificationAsRead($user, $id);
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        if ($user) {
            $this->notificationService->markAllNotificationsAsRead($user);
        }

        return response()->json(['success' => true]);
    }
}
