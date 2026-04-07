<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class FcmPushService
{
    public function __construct(
        protected Messaging $messaging,
        protected FirestoreService $firestore,
    ) {}

    /**
     * FCM to the driver's physical device when a Firestore `driver_notifications` row is created
     * (e.g. admin assigns a shipment). Requires `fcm_token` on `drivers/{driverFirestoreId}`.
     *
     * @param  array<string, mixed>  $doc  title, message, type, related_type, related_id, data (optional)
     */
    public function pushDriverNotificationFirestore(string $driverFirestoreId, string $notificationDocId, array $doc): void
    {
        $driverFirestoreId = trim($driverFirestoreId);
        if ($driverFirestoreId === '') {
            return;
        }

        $token = $this->freshDriverFcmTokenFromFirestore($driverFirestoreId);
        if ($token === '') {
            Log::info('FCM skipped: driver has no fcm_token (Firestore)', ['driver_id' => $driverFirestoreId]);

            return;
        }

        $title = trim((string) ($doc['title'] ?? 'Notification'));
        $msg = (string) ($doc['message'] ?? '');
        $body = $this->notificationBody($msg, is_array($doc['data'] ?? null) ? $doc['data'] : null);
        if (trim($body) === '' && trim($msg) !== '') {
            $body = trim($msg);
        }
        if (trim($body) === '') {
            $body = 'You have a new notification.';
        }

        $this->sendToToken(
            $token,
            $title !== '' ? $title : 'Notification',
            $body,
            [
                'channel' => 'driver_notifications',
                'notification_id' => $notificationDocId,
                'type' => (string) ($doc['type'] ?? ''),
                'related_type' => (string) ($doc['related_type'] ?? ''),
                'related_id' => (string) ($doc['related_id'] ?? ''),
            ],
            customerFirestoreIdForCleanup: null,
            driverFirestoreIdForCleanup: $driverFirestoreId
        );
    }

    /**
     * @param  array<string, mixed>  $order  Firestore `orders` document
     */
    public function sendOrderMessageToCustomer(array $order, string $message): void
    {
        $customerId = trim((string) ($order['customer_id'] ?? ''));
        if ($customerId === '') {
            return;
        }

        $token = $this->freshCustomerFcmTokenFromFirestore($customerId);
        if ($token === '') {
            return;
        }

        $driverId = trim((string) ($order['driver_id'] ?? ''));
        $driverRow = $driverId !== '' ? $this->firestore->get('drivers', $driverId) : null;
        $driverName = trim((string) ($driverRow['name'] ?? 'Driver'));
        $title = $driverName !== '' ? $driverName : 'Driver';
        $body = trim($message) !== '' ? trim($message) : 'You have a new message.';

        $this->sendToToken($token, $title, $body, [
            'channel' => 'order_messages',
            'order_id' => (string) ($order['id'] ?? ''),
            'transaction_id' => (string) ($order['transaction_id'] ?? ''),
            'sender' => 'driver',
        ], customerFirestoreIdForCleanup: $customerId);
    }

    /**
     * @param  array<string, mixed>  $order  Firestore `orders` document
     */
    public function sendOrderMessageToDriver(array $order, string $message): void
    {
        $driverId = trim((string) ($order['driver_id'] ?? ''));
        if ($driverId === '') {
            return;
        }

        $token = $this->freshDriverFcmTokenFromFirestore($driverId);
        if ($token === '') {
            return;
        }

        $customerName = trim((string) ($order['customer_name'] ?? 'Customer'));
        $title = $customerName !== '' ? $customerName : 'Customer';
        $body = trim($message) !== '' ? trim($message) : 'You have a new message.';

        $this->sendToToken($token, $title, $body, [
            'channel' => 'order_messages',
            'order_id' => (string) ($order['id'] ?? ''),
            'transaction_id' => (string) ($order['transaction_id'] ?? ''),
            'sender' => 'customer',
        ], driverFirestoreIdForCleanup: $driverId);
    }

    private function freshCustomerFcmTokenFromFirestore(string $customerDocId): string
    {
        $customerDocId = trim($customerDocId);
        if ($customerDocId === '') {
            return '';
        }
        $row = $this->firestore->get('customers', $customerDocId);

        return trim((string) ($row['fcm_token'] ?? ''));
    }

    private function freshDriverFcmTokenFromFirestore(string $driverDocId): string
    {
        $driverDocId = trim($driverDocId);
        if ($driverDocId === '') {
            return '';
        }
        $row = $this->firestore->get('drivers', $driverDocId);

        return trim((string) ($row['fcm_token'] ?? ''));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function sendToToken(
        string $token,
        string $title,
        string $body,
        array $data,
        ?string $customerFirestoreIdForCleanup = null,
        ?string $driverFirestoreIdForCleanup = null,
    ): void {
        $stringData = $this->stringifyData($data);

        try {
            $message = CloudMessage::fromArray([
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringData,
                'android' => [
                    'priority' => 'HIGH',
                ],
            ]);

            $this->messaging->send($message);
        } catch (Throwable $e) {
            try {
                $fallback = CloudMessage::withTarget('token', $token)
                    ->withNotification(Notification::create($title, $body))
                    ->withData($stringData);
                $this->messaging->send($fallback);
            } catch (Throwable $e2) {
                $this->maybeClearStaleToken($token, $e2, $customerFirestoreIdForCleanup, $driverFirestoreIdForCleanup);
                report($e2);
            }
        }
    }

    private function maybeClearStaleToken(
        string $token,
        Throwable $e,
        ?string $customerFirestoreId = null,
        ?string $driverFirestoreId = null,
    ): void {
        $msg = strtolower($e->getMessage());
        $stale = str_contains($msg, 'registration-token-not-registered')
            || str_contains($msg, 'not a valid fcm registration token')
            || str_contains($msg, 'requested entity was not found')
            || str_contains($msg, 'unregistered');

        if (! $stale) {
            return;
        }

        if ($customerFirestoreId !== null && trim($customerFirestoreId) !== '') {
            try {
                $row = $this->firestore->get('customers', $customerFirestoreId);
                if ($row && (($row['fcm_token'] ?? null) === $token)) {
                    $this->firestore->update('customers', $customerFirestoreId, [
                        'fcm_token' => null,
                        'fcm_platform' => null,
                    ]);
                }
            } catch (Throwable) {
            }
        }

        if ($driverFirestoreId !== null && trim($driverFirestoreId) !== '') {
            try {
                $row = $this->firestore->get('drivers', $driverFirestoreId);
                if ($row && (($row['fcm_token'] ?? null) === $token)) {
                    $this->firestore->update('drivers', $driverFirestoreId, [
                        'fcm_token' => null,
                        'fcm_platform' => null,
                    ]);
                }
            } catch (Throwable) {
            }
        }
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function notificationBody(string $message, ?array $data): string
    {
        $message = trim($message);
        if ($message !== '') {
            return $message;
        }

        $subtitle = trim((string) ($data['subtitle'] ?? ''));
        $highlight = trim((string) ($data['highlight'] ?? ''));
        $chunks = array_values(array_filter([$subtitle, $highlight], fn ($v) => $v !== ''));

        if (count($chunks) > 0) {
            return implode(' ', $chunks);
        }

        return 'You have a new notification.';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                $out[(string) $key] = json_encode($value);

                continue;
            }
            $out[(string) $key] = (string) ($value ?? '');
        }

        return $out;
    }
}
