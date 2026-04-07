<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;

/**
 * Service for Firebase Realtime Database (https://icecream-14ae7-default-rtdb.firebaseio.com/).
 * Uses Kreait firebase.database to read/write JSON tree data.
 */
class FirebaseRealtimeService
{
    protected Database $db;

    public function __construct(
        Database $database,
        protected FirestoreService $firestore,
    ) {
        $this->db = $database;
    }

    /**
     * Add a record with auto-generated key. Returns the new key.
     * Adds created_at and updated_at automatically.
     */
    public function add(string $collection, array $data): string
    {
        $data['created_at'] = date('c');
        $data['updated_at'] = date('c');
        $ref = $this->db->getReference($collection)->push($data);
        return $ref->getKey();
    }

    /**
     * Get a record by key. Returns array with 'id' and all fields, or null.
     */
    public function get(string $collection, string $id): ?array
    {
        $value = $this->db->getReference($collection)->getChild($id)->getValue();
        if ($value === null || !is_array($value)) {
            return null;
        }
        $value['id'] = $id;
        return $value;
    }

    /**
     * Set (create or overwrite) a record at the given key.
     */
    public function set(string $collection, string $id, array $data): void
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('c');
        }
        $data['updated_at'] = date('c');
        $this->db->getReference($collection)->getChild($id)->set($data);
    }

    /**
     * Update a record (merge). Adds updated_at.
     */
    public function update(string $collection, string $id, array $data): void
    {
        $data['updated_at'] = date('c');
        $this->db->getReference($collection)->getChild($id)->update($data);
    }

    /**
     * Delete a record.
     */
    public function delete(string $collection, string $id): void
    {
        $this->db->getReference($collection)->getChild($id)->remove();
    }

    /**
     * Get all records in a collection, optionally sorted in PHP.
     *
     * @param  string|null  $orderBy  Field name (e.g. 'created_at')
     * @param  string  $direction  'asc' or 'desc'
     * @return array<int, array>  List of records (each has 'id' key)
     */
    public function all(string $collection, ?string $orderBy = null, string $direction = 'desc'): array
    {
        $value = $this->db->getReference($collection)->getValue();
        if ($value === null || !is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $id => $row) {
            if (is_array($row)) {
                $row['id'] = $id;
                $out[] = $row;
            }
        }
        if ($orderBy !== null) {
            usort($out, function ($a, $b) use ($orderBy, $direction) {
                $aVal = $a[$orderBy] ?? '';
                $bVal = $b[$orderBy] ?? '';
                $cmp = $aVal <=> $bVal;
                return $direction === 'desc' ? -$cmp : $cmp;
            });
        }
        return $out;
    }

    /**
     * Find first record where field equals value (uses Realtime DB query when possible).
     */
    public function firstWhere(string $collection, string $field, mixed $value): ?array
    {
        try {
            $queryValue = $this->db->getReference($collection)
                ->orderByChild($field)
                ->equalTo($value)
                ->limitToFirst(1)
                ->getValue();
        } catch (\Throwable) {
            // Fallback: get all and filter in PHP (e.g. if no index)
            $all = $this->all($collection);
            foreach ($all as $row) {
                if (($row[$field] ?? null) === $value) {
                    return $row;
                }
            }
            return null;
        }
        if (!is_array($queryValue) || empty($queryValue)) {
            return null;
        }
        $id = array_key_first($queryValue);
        $row = $queryValue[$id];
        if (!is_array($row)) {
            return null;
        }
        $row['id'] = $id;
        return $row;
    }

    /**
     * Find records where field equals value.
     *
     * @return array<int, array>
     */
    public function where(string $collection, string $field, mixed $value, ?string $orderBy = null, string $direction = 'desc'): array
    {
        try {
            $queryValue = $this->db->getReference($collection)
                ->orderByChild($field)
                ->equalTo($value)
                ->getValue();
        } catch (\Throwable) {
            $queryValue = null;
        }
        if (!is_array($queryValue)) {
            $all = $this->all($collection);
            $out = [];
            foreach ($all as $row) {
                if (($row[$field] ?? null) === $value) {
                    $out[] = $row;
                }
            }
            if ($orderBy !== null) {
                usort($out, function ($a, $b) use ($orderBy, $direction) {
                    $aVal = $a[$orderBy] ?? '';
                    $bVal = $b[$orderBy] ?? '';
                    $cmp = $aVal <=> $bVal;
                    return $direction === 'desc' ? -$cmp : $cmp;
                });
            }
            return $out;
        }
        $out = [];
        foreach ($queryValue as $id => $row) {
            if (is_array($row)) {
                $row['id'] = $id;
                $out[] = $row;
            }
        }
        if ($orderBy !== null) {
            usort($out, function ($a, $b) use ($orderBy, $direction) {
                $aVal = $a[$orderBy] ?? '';
                $bVal = $b[$orderBy] ?? '';
                $cmp = $aVal <=> $bVal;
                return $direction === 'desc' ? -$cmp : $cmp;
            });
        }
        return $out;
    }

    /**
     * Sync a chat message to Firebase for real-time clients.
     * Path: chats/{customerId}/messages/{messageId}
     */
    public function syncChatMessage(string|int $customerId, string|int $messageId, array $data): void
    {
        try {
            $cid = (string) $customerId;
            $collection = 'chats/'.$cid.'/messages';
            $this->set($collection, (string) $messageId, $data);
            // Bump thread timestamp so mobile clients can listen on chats/{customerId}/last_updated
            $this->set('chats/'.$cid, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Update chat message read_at in Firebase (for real-time read receipts).
     */
    public function updateChatMessageReadAt(string|int $customerId, string|int $messageId, ?string $readAt): void
    {
        try {
            $cid = (string) $customerId;
            $collection = 'chats/'.$cid.'/messages';
            $this->update($collection, (string) $messageId, ['read_at' => $readAt]);
            $this->set('chats/'.$cid, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Mark many chat messages read in Realtime DB with one request (avoids N round-trips per message).
     *
     * @param  array<int, string>  $messageIds
     */
    public function bulkUpdateChatMessagesReadAt(string|int $customerId, array $messageIds, string $readAt): void
    {
        if ($messageIds === []) {
            return;
        }

        try {
            $cid = (string) $customerId;
            $now = date('c');
            $payload = [];
            foreach ($messageIds as $messageId) {
                $mid = (string) $messageId;
                if ($mid === '') {
                    continue;
                }
                $payload[$mid] = [
                    'read_at' => $readAt,
                    'updated_at' => $now,
                ];
            }
            if ($payload === []) {
                return;
            }

            $this->db->getReference('chats/'.$cid.'/messages')->update($payload);
            $this->set('chats/'.$cid, 'last_updated', [
                'value' => $now,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Sync a customer notification to Firebase for real-time clients.
     * Path: notifications/{customerId}/items/{notificationId}
     * Also updates notifications/{customerId}/last_updated so clients can listen for changes.
     */
    public function syncNotification(int $customerId, int $notificationId, array $data): void
    {
        try {
            $itemsPath = 'notifications/' . $customerId . '/items';
            $this->set($itemsPath, (string) $notificationId, $data);
            $this->set('notifications/' . $customerId, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Update notification read_at in Firebase (for real-time read status).
     */
    public function updateNotificationReadAt(int $customerId, int $notificationId, ?string $readAt): void
    {
        try {
            $collection = 'notifications/' . $customerId . '/items';
            $this->update($collection, (string) $notificationId, ['read_at' => $readAt]);
            $this->set('notifications/' . $customerId, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Remove one customer notification node (e.g. after DELETE from API).
     * Path: notifications/{customerId}/items/{notificationId}
     */
    public function deleteCustomerNotificationItem(int $customerId, int $notificationId): void
    {
        try {
            $collection = 'notifications/' . $customerId . '/items';
            $this->delete($collection, (string) $notificationId);
            $this->set('notifications/' . $customerId, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Driver app: mirror of customer notifications tree.
     * Path: driver_notifications/{driverId}/items/{notificationId}
     * Uses string Firestore driver doc ids (same segment as Flutter `resolveDriverId()`).
     */
    public function syncDriverNotification(string|int $driverId, string|int $notificationId, array $data): void
    {
        try {
            $driverSegment = (string) $driverId;
            $notifKey = (string) $notificationId;
            $itemsPath = 'driver_notifications/' . $driverSegment . '/items';
            $this->set($itemsPath, $notifKey, $data);
            $this->set('driver_notifications/' . $driverSegment, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function updateDriverNotificationReadAt(string|int $driverId, string|int $notificationId, ?string $readAt): void
    {
        try {
            $driverSegment = (string) $driverId;
            $notifKey = (string) $notificationId;
            $collection = 'driver_notifications/' . $driverSegment . '/items';
            $this->update($collection, $notifKey, ['read_at' => $readAt]);
            $this->set('driver_notifications/' . $driverSegment, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function deleteDriverNotificationItem(string|int $driverId, string|int $notificationId): void
    {
        try {
            $driverSegment = (string) $driverId;
            $notifKey = (string) $notificationId;
            $collection = 'driver_notifications/' . $driverSegment . '/items';
            $this->delete($collection, $notifKey);
            $this->set('driver_notifications/' . $driverSegment, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Touch admin orders "last updated" so the orders dashboard can listen and refetch.
     * Path: admin/orders_last_updated
     */
    public function touchOrdersUpdated(): void
    {
        try {
            $this->set('admin', 'orders_last_updated', [
                'value' => date('c'),
            ]);
            $this->firestore->mergeAdminBroadcast([
                'orders_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Touch admin chat "last updated" when a customer sends a message so the admin panel can refresh unread/badge.
     * Path: admin/chat_last_updated
     */
    public function touchAdminChatUpdated(): void
    {
        try {
            $this->set('admin', 'chat_last_updated', [
                'value' => date('c'),
            ]);
            $this->firestore->mergeAdminBroadcast([
                'chat_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Touch admin notifications "last updated" so the admin panel can refetch and show new notifications in real time.
     * Path: admin/notifications_last_updated
     */
    public function touchAdminNotificationsUpdated(): void
    {
        try {
            $this->set('admin', 'notifications_last_updated', [
                'value' => date('c'),
            ]);
            $this->firestore->mergeAdminBroadcast([
                'notifications_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Set latest order alert for real-time "New order" toast in admin panel.
     * Path: admin/latest_order_alert
     */
    public function setLatestOrderAlert(array $payload): void
    {
        try {
            $merged = array_merge($payload, [
                'value' => date('c'),
            ]);
            $this->set('admin', 'latest_order_alert', $merged);
            $this->firestore->mergeAdminBroadcast([
                'order_alert' => $merged,
                'orders_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Sync an order message (driver ↔ customer) to Firebase for real-time clients.
     * Path: order_messages/{orderId}/messages/{messageId}
     */
    public function syncOrderMessage(string|int $orderId, string|int $messageId, array $data): void
    {
        try {
            $orderSegment = (string) $orderId;
            $msgKey = (string) $messageId;
            $collection = 'order_messages/' . $orderSegment . '/messages';
            $this->set($collection, $msgKey, $data);
            $this->touchOrderMessagesThreadUpdated($orderSegment);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Update order message read_at in Firebase (for real-time read receipts).
     */
    public function updateOrderMessageReadAt(string|int $orderId, string|int $messageId, ?string $readAt): void
    {
        try {
            $orderSegment = (string) $orderId;
            $msgKey = (string) $messageId;
            $collection = 'order_messages/' . $orderSegment . '/messages';
            $this->update($collection, $msgKey, ['read_at' => $readAt]);
            $this->touchOrderMessagesThreadUpdated($orderSegment);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Bump when order thread changes (new message, read receipts, archive, etc.).
     * Flutter can listen: order_messages/{orderId}/last_updated
     */
    public function touchOrderMessagesThreadUpdated(int|string $orderId): void
    {
        try {
            $this->set('order_messages/'.$orderId, 'last_updated', [
                'value' => date('c'),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
