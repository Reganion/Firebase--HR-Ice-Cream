<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseRealtimeService;
use App\Services\FirestoreService;
use App\Support\FirestoreDriverUser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApiDriverNotificationController extends Controller
{
    public function __construct(
        protected FirebaseRealtimeService $firebase,
        protected FirestoreService $firestore
    ) {}

    /**
     * Same key as RTDB items path / customer notifications (stable int from Firestore doc id).
     */
    private function notificationPublicIdFromDocId(string $docId): int
    {
        $digits = (int) preg_replace('/\D/', '', $docId);

        return $digits > 0 ? $digits : crc32($docId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationsForDriver(string $driverId): array
    {
        return $this->firestore->whereDriverNotificationsForDriver($driverId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $docs
     */
    private function findDocByPublicId(array $docs, int $publicId): ?array
    {
        foreach ($docs as $doc) {
            $id = (string) ($doc['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if ($this->notificationPublicIdFromDocId($id) === $publicId) {
                return $doc;
            }
        }

        return null;
    }

    private function formatIso(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function formatNotificationForApi(array $doc): array
    {
        $docId = (string) ($doc['id'] ?? '');
        $readAt = $doc['read_at'] ?? null;
        if ($readAt !== null && $readAt !== '') {
            $readAt = $this->formatIso(is_string($readAt) ? $readAt : (string) $readAt);
        } else {
            $readAt = null;
        }

        $createdAt = $doc['created_at'] ?? null;
        if ($createdAt !== null && $createdAt !== '') {
            $createdAt = $this->formatIso(is_string($createdAt) ? $createdAt : (string) $createdAt);
        } else {
            $createdAt = null;
        }

        $relatedRaw = $doc['related_id'] ?? null;
        $relatedForJson = $relatedRaw;
        if ($relatedRaw !== null && $relatedRaw !== '' && is_numeric((string) $relatedRaw)) {
            $relatedForJson = (int) $relatedRaw;
        } elseif ($relatedRaw !== null && $relatedRaw !== '') {
            $relatedForJson = (string) $relatedRaw;
        } else {
            $relatedForJson = null;
        }

        return [
            'id' => $this->notificationPublicIdFromDocId($docId),
            'type' => $doc['type'] ?? null,
            'title' => (string) ($doc['title'] ?? ''),
            'message' => (string) ($doc['message'] ?? ''),
            'image_url' => $doc['image_url'] ?? null,
            'related_type' => $doc['related_type'] ?? null,
            'related_id' => $relatedForJson,
            'data' => $doc['data'] ?? null,
            'read_at' => $readAt,
            'created_at' => $createdAt,
        ];
    }

    private function isUnreadDoc(array $doc): bool
    {
        $r = $doc['read_at'] ?? null;

        return $r === null || $r === '';
    }

    /**
     * List notifications for authenticated driver (Firestore `driver_notifications`).
     * GET /api/v1/driver/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $driverId = trim((string) $driver->id);
        $perPage = min((int) $request->get('per_page', 20), 50);
        $page = max(1, (int) $request->get('page', 1));
        $unreadOnly = $request->boolean('unread_only');

        $docs = $this->notificationsForDriver($driverId);
        $sorted = Collection::make($docs)
            ->sortByDesc(fn (array $d) => (string) ($d['created_at'] ?? ''))
            ->values();

        if ($unreadOnly) {
            $sorted = $sorted->filter(fn (array $d) => $this->isUnreadDoc($d))->values();
        }

        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($page, $lastPage);
        $items = $sorted->forPage($page, $perPage)
            ->map(fn (array $d) => $this->formatNotificationForApi($d))
            ->values()
            ->all();

        $unreadCount = Collection::make($docs)->filter(fn (array $d) => $this->isUnreadDoc($d))->count();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * GET /api/v1/driver/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $driverId = trim((string) $driver->id);
        $docs = $this->notificationsForDriver($driverId);
        $count = Collection::make($docs)->filter(fn (array $d) => $this->isUnreadDoc($d))->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * POST /api/v1/driver/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $driverId = trim((string) $driver->id);
        $docs = $this->notificationsForDriver($driverId);
        $notification = $this->findDocByPublicId($docs, $id);

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $docId = (string) ($notification['id'] ?? '');
        $readAt = now()->toIso8601String();
        $this->firestore->update('driver_notifications', $docId, ['read_at' => $readAt]);
        $this->firebase->updateDriverNotificationReadAt($driverId, $docId, $readAt);

        return response()->json(['success' => true, 'message' => 'Marked as read.']);
    }

    /**
     * POST /api/v1/driver/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $driverId = trim((string) $driver->id);
        $docs = $this->notificationsForDriver($driverId);
        $readAt = now()->toIso8601String();
        $merge = [];
        foreach ($docs as $d) {
            if (! $this->isUnreadDoc($d)) {
                continue;
            }
            $docId = (string) ($d['id'] ?? '');
            if ($docId === '') {
                continue;
            }
            $merge[$docId] = ['read_at' => $readAt];
        }

        if ($merge !== []) {
            $this->firestore->batchMergeDocuments('driver_notifications', $merge);
        }

        foreach ($merge as $docId => $_) {
            $this->firebase->updateDriverNotificationReadAt($driverId, $docId, $readAt);
        }

        return response()->json(['success' => true, 'message' => 'All marked as read.']);
    }

    /**
     * DELETE /api/v1/driver/notifications/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $driverId = trim((string) $driver->id);
        $docs = $this->notificationsForDriver($driverId);
        $notification = $this->findDocByPublicId($docs, $id);

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $docId = (string) ($notification['id'] ?? '');
        $this->firestore->delete('driver_notifications', $docId);
        $this->firebase->deleteDriverNotificationItem($driverId, $docId);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    /**
     * DELETE /api/v1/driver/notifications
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $driverId = trim((string) $driver->id);
        $docs = $this->notificationsForDriver($driverId);
        $deleted = 0;

        foreach ($docs as $d) {
            $docId = (string) ($d['id'] ?? '');
            if ($docId === '') {
                continue;
            }
            $this->firestore->delete('driver_notifications', $docId);
            $this->firebase->deleteDriverNotificationItem($driverId, $docId);
            $deleted++;
        }

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted successfully.',
            'deleted_count' => $deleted,
        ]);
    }
}
