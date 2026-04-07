<?php

namespace App\Services;

/**
 * Creates a Firestore `driver_notifications` document, mirrors it to Realtime Database,
 * and sends FCM to the driver's device (token on `drivers/{id}`).
 */
class DriverNotificationDispatcher
{
    public function __construct(
        protected FirestoreService $firestore,
        protected FirebaseRealtimeService $firebase,
        protected FcmPushService $fcmPush,
    ) {}

    /**
     * @param  array<string, mixed>  $doc  Fields for Firestore: driver_id, type, title, message, image_url, related_type, related_id, data, read_at, etc.
     * @return string New Firestore document id
     */
    public function createAndNotify(string $driverFirestoreId, array $doc): string
    {
        $driverFirestoreId = trim($driverFirestoreId);
        $docId = $this->firestore->add('driver_notifications', $doc);

        $this->firebase->syncDriverNotification($driverFirestoreId, $docId, [
            'id' => $docId,
            'type' => $doc['type'] ?? null,
            'title' => (string) ($doc['title'] ?? ''),
            'message' => (string) ($doc['message'] ?? ''),
            'image_url' => $doc['image_url'] ?? null,
            'related_type' => $doc['related_type'] ?? null,
            'related_id' => $doc['related_id'] ?? null,
            'data' => $doc['data'] ?? null,
            'read_at' => $doc['read_at'] ?? null,
            'created_at' => now()->toIso8601String(),
        ]);

        try {
            $this->fcmPush->pushDriverNotificationFirestore($driverFirestoreId, $docId, [
                'title' => (string) ($doc['title'] ?? ''),
                'message' => (string) ($doc['message'] ?? ''),
                'type' => (string) ($doc['type'] ?? ''),
                'related_type' => (string) ($doc['related_type'] ?? ''),
                'related_id' => $doc['related_id'] !== null && (string) $doc['related_id'] !== ''
                    ? (string) $doc['related_id']
                    : '',
                'data' => is_array($doc['data'] ?? null) ? $doc['data'] : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $docId;
    }
}
