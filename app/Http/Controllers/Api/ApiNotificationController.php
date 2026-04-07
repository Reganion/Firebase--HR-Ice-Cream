<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseRealtimeService;
use App\Services\FirestoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApiNotificationController extends Controller
{
    public function __construct(
        protected FirebaseRealtimeService $firebase,
        protected FirestoreService $firestore,
    ) {}

    /**
     * API customer guard: who is logged in (session user), not notification storage.
     */
    private function isCustomerUser(mixed $user): bool
    {
        return is_object($user) && isset($user->id) && (string) $user->id !== '';
    }

    /**
     * Same key as FirebaseRealtimeService::syncNotification / mobile RTDB items path.
     */
    private function notificationRtdbKeyFromDocId(string $docId): int
    {
        $digits = (int) preg_replace('/\D/', '', $docId);

        return $digits > 0 ? $digits : crc32($docId);
    }

    /**
     * Firebase Realtime paths use int customerId; derive a stable int from Firestore string ids.
     */
    private function firebaseNumericCustomerId(string $customerId): int
    {
        $customerId = trim($customerId);
        if ($customerId !== '' && ctype_digit($customerId)) {
            return (int) $customerId;
        }

        return (int) (sprintf('%u', crc32($customerId)) % 2147483647);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationsForCustomer(string $customerId): array
    {
        return $this->firestore->whereCustomerNotificationsForCustomer($customerId);
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
            if ($this->notificationRtdbKeyFromDocId($id) === $publicId) {
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

        return [
            'id' => $this->notificationRtdbKeyFromDocId($docId),
            'type' => $doc['type'] ?? null,
            'title' => (string) ($doc['title'] ?? ''),
            'message' => (string) ($doc['message'] ?? ''),
            'image_url' => $doc['image_url'] ?? null,
            'related_type' => $doc['related_type'] ?? null,
            'related_id' => isset($doc['related_id']) ? (string) $doc['related_id'] : null,
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
     * List notifications for the authenticated customer (Firestore `customer_notifications`).
     * GET /api/v1/notifications
     * Query: ?page=1&per_page=20&unread_only=0
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isCustomerUser($user)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $customerId = trim((string) $user->id);
        $perPage = min((int) $request->get('per_page', 20), 50);
        $page = max(1, (int) $request->get('page', 1));
        $unreadOnly = $request->boolean('unread_only');

        $docs = $this->notificationsForCustomer($customerId);
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
     * Get unread count only (for badge).
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isCustomerUser($user)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $customerId = trim((string) $user->id);
        $docs = $this->notificationsForCustomer($customerId);
        $count = Collection::make($docs)->filter(fn (array $d) => $this->isUnreadDoc($d))->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }

    /**
     * Mark a single notification as read.
     * POST /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->isCustomerUser($user)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $customerId = trim((string) $user->id);
        $docs = $this->notificationsForCustomer($customerId);
        $notification = $this->findDocByPublicId($docs, $id);

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $docId = (string) ($notification['id'] ?? '');
        $readAt = now()->toIso8601String();
        $this->firestore->update('customer_notifications', $docId, ['read_at' => $readAt]);
        $this->firebase->updateNotificationReadAt(
            $this->firebaseNumericCustomerId($customerId),
            $id,
            $readAt
        );

        return response()->json(['success' => true, 'message' => 'Marked as read.']);
    }

    /**
     * Mark all notifications as read for the current customer.
     * POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isCustomerUser($user)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $customerId = trim((string) $user->id);
        $docs = $this->notificationsForCustomer($customerId);
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
            $this->firestore->batchMergeDocuments('customer_notifications', $merge);
        }

        $numericCustomer = $this->firebaseNumericCustomerId($customerId);
        foreach ($merge as $docId => $_) {
            $publicId = $this->notificationRtdbKeyFromDocId($docId);
            $this->firebase->updateNotificationReadAt($numericCustomer, $publicId, $readAt);
        }

        return response()->json(['success' => true, 'message' => 'All marked as read.']);
    }

    /**
     * Delete a single notification for the current customer.
     * DELETE /api/v1/notifications/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->isCustomerUser($user)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $customerId = trim((string) $user->id);
        $docs = $this->notificationsForCustomer($customerId);
        $notification = $this->findDocByPublicId($docs, $id);

        if (! $notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $docId = (string) ($notification['id'] ?? '');
        $this->firestore->delete('customer_notifications', $docId);
        $this->firebase->deleteCustomerNotificationItem(
            $this->firebaseNumericCustomerId($customerId),
            $id
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    /**
     * Delete all notifications for the current customer.
     * DELETE /api/v1/notifications
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isCustomerUser($user)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $customerId = trim((string) $user->id);
        $docs = $this->notificationsForCustomer($customerId);
        $numericCustomer = $this->firebaseNumericCustomerId($customerId);
        $deleted = 0;

        foreach ($docs as $d) {
            $docId = (string) ($d['id'] ?? '');
            if ($docId === '') {
                continue;
            }
            $this->firestore->delete('customer_notifications', $docId);
            $publicId = $this->notificationRtdbKeyFromDocId($docId);
            $this->firebase->deleteCustomerNotificationItem($numericCustomer, $publicId);
            $deleted++;
        }

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted successfully.',
            'deleted_count' => $deleted,
        ]);
    }
}
