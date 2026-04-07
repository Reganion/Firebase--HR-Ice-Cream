<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminNotification;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AdminNotificationController extends Controller
{
    public function __construct(private FirestoreService $firestore)
    {
    }

    /**
     * Return notifications as JSON for real-time polling (admin layout).
     */
    public function index(Request $request)
    {
        $adminId = session('admin_id');
        if (!$adminId) {
            return response()->json(['notifications' => [], 'unread_count' => 0], 401);
        }

        $raw = $this->firestore->rememberWhereAdminNotificationsForUser((string) $adminId, 30);

        $notifications = collect($raw)
            ->filter(fn ($notif) => AdminNotification::shouldShowInAdminFeed($notif))
            ->sortByDesc(fn ($notif) => (string) ($notif['created_at'] ?? ''))
            ->take(50)
            ->values()
            ->map(function ($notif) {
                $createdAt = !empty($notif['created_at']) ? Carbon::parse((string) $notif['created_at']) : now();
                $readAt = !empty($notif['read_at']) ? Carbon::parse((string) $notif['read_at']) : null;
                return [
                    'id'           => $notif['id'],
                    'type'         => $notif['type'] ?? null,
                    'title'        => $notif['title'] ?? 'Notification',
                    'message'      => $notif['message'] ?? '',
                    'image_url'    => !empty($notif['image_url']) ? URL::asset((string) $notif['image_url']) : null,
                    'read_at'      => $readAt?->toIso8601String(),
                    'data'         => $notif['data'] ?? null,
                    'related_type' => $notif['related_type'] ?? null,
                    'related_id'   => $notif['related_id'] ?? null,
                    'created_at'   => $createdAt->toIso8601String(),
                    'created_at_human' => $createdAt->diffForHumans(),
                ];
            });

        $unreadCount = collect($raw)
            ->filter(fn ($notif) => AdminNotification::shouldShowInAdminFeed($notif))
            ->filter(fn ($notif) => empty($notif['read_at']))
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, int $id)
    {
        $adminId = session('admin_id');
        if (!$adminId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $notification = $this->firestore->get('admin_notifications', (string) $id);
        if (!$notification || ! AdminNotification::firestoreUserIdMatches($notification, $adminId)) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }
        $this->firestore->update('admin_notifications', (string) $id, ['read_at' => now()->toIso8601String()]);
        FirestoreCacheKeys::forgetAdminNotificationsFeed((string) $adminId);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read for the current admin.
     */
    public function markAllRead(Request $request)
    {
        $adminId = session('admin_id');
        if (!$adminId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $notifications = $this->firestore->whereAdminNotificationsForUser($adminId);
        foreach ($notifications as $notif) {
            if (empty($notif['read_at']) && !empty($notif['id'])) {
                $this->firestore->update('admin_notifications', (string) $notif['id'], ['read_at' => now()->toIso8601String()]);
            }
        }
        FirestoreCacheKeys::forgetAdminNotificationsFeed((string) $adminId);

        return response()->json(['success' => true]);
    }
}
