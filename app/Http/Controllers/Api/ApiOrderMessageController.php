<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\OrderMessage;
use App\Services\FcmPushService;
use App\Services\FirebaseRealtimeService;
use App\Services\FirestoreService;
use App\Support\FirestoreDriverUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApiOrderMessageController extends Controller
{
    public function __construct(
        protected FirebaseRealtimeService $firebase,
        protected FcmPushService $fcmPush,
        protected FirestoreService $firestore,
    ) {}

    private function isCustomerUser(mixed $user): bool
    {
        return is_object($user) && isset($user->id) && (string) $user->id !== '';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function orderMessagesForOrder(string $orderId): Collection
    {
        $byId = [];
        foreach ($this->firestore->where('order_messages', 'order_id', (string) $orderId) as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $row;
            }
        }
        if (ctype_digit((string) $orderId)) {
            foreach ($this->firestore->where('order_messages', 'order_id', (int) $orderId) as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id !== '') {
                    $byId[$id] = $row;
                }
            }
        }

        return collect(array_values($byId));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDriverOrder(object $driver, string $orderId): ?array
    {
        $order = $this->firestore->get('orders', $orderId);
        if (! $order) {
            return null;
        }
        $did = (string) ($order['driver_id'] ?? '');
        if ($did === '' || $did !== (string) $driver->id) {
            return null;
        }

        return $order;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCustomerOrder(object $customer, string $orderId): ?array
    {
        $order = $this->firestore->get('orders', $orderId);
        if (! $order) {
            return null;
        }
        $cid = (string) ($customer->id ?? '');
        if ((string) ($order['customer_id'] ?? '') !== $cid) {
            return null;
        }

        return $order;
    }

    /**
     * @return array<int, string>
     */
    private function orderIdsOwnedByCustomer(string $customerId): array
    {
        $byId = [];
        foreach ($this->firestore->where('orders', 'customer_id', $customerId) as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = true;
            }
        }
        if (ctype_digit($customerId)) {
            foreach ($this->firestore->where('orders', 'customer_id', (int) $customerId) as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id !== '') {
                    $byId[$id] = true;
                }
            }
        }

        return array_keys($byId);
    }

    private function messageTimestamp(array $m): string
    {
        return (string) ($m['created_at'] ?? '');
    }

    private function iso(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return $v;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function formatMessage(array $message, string $currentSenderType): array
    {
        $payload = [
            'id' => (string) ($message['id'] ?? ''),
            'order_id' => (string) ($message['order_id'] ?? ''),
            'driver_id' => (string) ($message['driver_id'] ?? ''),
            'customer_id' => (string) ($message['customer_id'] ?? ''),
            'sender_type' => (string) ($message['sender_type'] ?? ''),
            'message' => (string) ($message['message'] ?? ''),
            'is_mine' => ((string) ($message['sender_type'] ?? '')) === $currentSenderType,
            'created_at' => $this->iso(isset($message['created_at']) ? (string) $message['created_at'] : null),
            'read_at' => $this->iso(isset($message['read_at']) ? (string) $message['read_at'] : null),
            'status' => (string) ($message['status'] ?? OrderMessage::STATUS_ACTIVE),
            'customer_status' => (string) ($message['customer_status'] ?? OrderMessage::CUSTOMER_STATUS_ACTIVE),
        ];

        return $payload;
    }

    /**
     * Driver: list messages for a shipment/order.
     * GET /api/v1/driver/shipments/{id}/messages
     */
    public function driverMessages(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->resolveDriverOrder($driver, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.'], 404);
        }

        if (empty($order['customer_id'])) {
            return response()->json(['success' => false, 'message' => 'Shipment has no linked customer account.'], 422);
        }

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $page = max(1, (int) $request->query('page', 1));
        $status = strtolower((string) $request->query('status', ''));

        $oid = (string) ($order['id'] ?? $id);
        $customerIdOrder = (string) ($order['customer_id'] ?? '');

        $messages = $this->orderMessagesForOrder($oid)->filter(function (array $m) use ($customerIdOrder, $status) {
            if ((string) ($m['customer_id'] ?? '') !== $customerIdOrder) {
                return false;
            }
            $sd = strtolower((string) ($m['status'] ?? ''));
            if ($status === 'active') {
                return $sd === OrderMessage::STATUS_ACTIVE;
            }
            if ($status === 'archive' || $status === 'archived') {
                return $sd === OrderMessage::STATUS_ARCHIVE;
            }

            return true;
        })->sortBy(fn (array $m) => $this->messageTimestamp($m))->values();

        $total = $messages->count();
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($page, $lastPage);
        $items = $messages->forPage($page, $perPage)
            ->map(fn (array $m) => $this->formatMessage($m, OrderMessage::SENDER_DRIVER))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'order_id' => $oid,
            'customer_id' => (string) ($order['customer_id'] ?? ''),
            'driver_id' => (string) $driver->id,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Driver: send message to customer for a shipment/order.
     * POST /api/v1/driver/shipments/{id}/messages
     */
    public function driverSend(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $order = $this->resolveDriverOrder($driver, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.'], 404);
        }

        if (empty($order['customer_id'])) {
            return response()->json(['success' => false, 'message' => 'Shipment has no linked customer account.'], 422);
        }

        $rawStatus = strtolower((string) ($request->input('status', $request->query('status', OrderMessage::STATUS_ACTIVE))));
        $status = $rawStatus === OrderMessage::STATUS_ARCHIVE ? OrderMessage::STATUS_ARCHIVE : OrderMessage::STATUS_ACTIVE;

        $oid = (string) ($order['id'] ?? $id);
        $docId = $this->firestore->add('order_messages', [
            'order_id' => $oid,
            'driver_id' => (string) $driver->id,
            'customer_id' => (string) ($order['customer_id'] ?? ''),
            'sender_type' => OrderMessage::SENDER_DRIVER,
            'message' => trim((string) $request->input('message')),
            'status' => $status,
            'customer_status' => OrderMessage::CUSTOMER_STATUS_ACTIVE,
            'read_at' => null,
        ]);

        $row = $this->firestore->get('order_messages', $docId) ?? array_merge([
            'id' => $docId,
            'order_id' => $oid,
            'driver_id' => (string) $driver->id,
            'customer_id' => (string) ($order['customer_id'] ?? ''),
            'sender_type' => OrderMessage::SENDER_DRIVER,
            'message' => trim((string) $request->input('message')),
            'status' => $status,
            'customer_status' => OrderMessage::CUSTOMER_STATUS_ACTIVE,
        ], ['id' => $docId]);

        $formatted = $this->formatMessage($row, OrderMessage::SENDER_DRIVER);
        $this->firebase->syncOrderMessage($oid, $docId, $formatted);
        $this->fcmPush->sendOrderMessageToCustomer($order, (string) ($row['message'] ?? ''));

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ], 201);
    }

    /**
     * Driver: mark customer messages as read in this shipment thread.
     * POST /api/v1/driver/shipments/{id}/messages/read
     */
    public function driverMarkRead(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->resolveDriverOrder($driver, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.'], 404);
        }

        $oid = (string) ($order['id'] ?? $id);
        $readAt = now()->toIso8601String();

        $messages = $this->orderMessagesForOrder($oid);
        foreach ($messages as $m) {
            if ((string) ($m['sender_type'] ?? '') !== OrderMessage::SENDER_CUSTOMER) {
                continue;
            }
            if (! empty($m['read_at'])) {
                continue;
            }
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['read_at' => $readAt]);
            $this->firebase->updateOrderMessageReadAt($oid, $mid, $readAt);
        }

        return response()->json([
            'success' => true,
            'message' => 'Marked as read.',
        ]);
    }

    /**
     * Driver: list archived message threads (threads with at least one message status = archive).
     * GET /api/v1/driver/messages/archived-threads
     */
    public function driverArchivedThreads(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $driverId = (string) $driver->id;
        $orderIds = [];

        foreach ($this->firestore->where('order_messages', 'driver_id', $driverId) as $row) {
            if (strtolower((string) ($row['status'] ?? '')) !== OrderMessage::STATUS_ARCHIVE) {
                continue;
            }
            $oid = (string) ($row['order_id'] ?? '');
            if ($oid !== '') {
                $orderIds[$oid] = true;
            }
        }
        if (ctype_digit($driverId)) {
            foreach ($this->firestore->where('order_messages', 'driver_id', (int) $driverId) as $row) {
                if (strtolower((string) ($row['status'] ?? '')) !== OrderMessage::STATUS_ARCHIVE) {
                    continue;
                }
                $oid = (string) ($row['order_id'] ?? '');
                if ($oid !== '') {
                    $orderIds[$oid] = true;
                }
            }
        }

        if ($orderIds === []) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $threads = [];
        foreach (array_keys($orderIds) as $oid) {
            $order = $this->firestore->get('orders', $oid);
            if (! $order || empty($order['customer_id'])) {
                continue;
            }
            if ((string) ($order['driver_id'] ?? '') !== $driverId) {
                continue;
            }

            $msgs = $this->orderMessagesForOrder($oid)->filter(function (array $m) {
                return strtolower((string) ($m['status'] ?? '')) === OrderMessage::STATUS_ARCHIVE;
            })->sortByDesc(fn (array $m) => $this->messageTimestamp($m))->values();

            $last = $msgs->first();

            $deliveryDate = (string) ($order['delivery_date'] ?? '');
            $deliveryTime = (string) ($order['delivery_time'] ?? '');
            $expectedOn = ($deliveryDate !== '' && $deliveryTime !== '')
                ? $deliveryDate.' '.$deliveryTime
                : $deliveryDate;

            $threads[] = [
                'shipment_id' => $oid,
                'customer_id' => (string) ($order['customer_id'] ?? ''),
                'customer_name' => trim((string) ($order['customer_name'] ?? 'Customer')),
                'customer_phone' => trim((string) ($order['customer_phone'] ?? '')),
                'delivery_address' => trim((string) ($order['delivery_address'] ?? '')),
                'expected_on' => $expectedOn,
                'status_driver' => (string) ($order['status_driver'] ?? ''),
                'last_message' => $last ? (string) ($last['message'] ?? '') : '',
                'last_message_at' => $last ? $this->iso((string) ($last['created_at'] ?? '')) : null,
            ];
        }

        usort($threads, function ($a, $b) {
            $aAt = (string) ($a['last_message_at'] ?? '');
            $bAt = (string) ($b['last_message_at'] ?? '');
            if ($aAt === $bAt) {
                return strcmp((string) ($b['shipment_id'] ?? ''), (string) ($a['shipment_id'] ?? ''));
            }

            return strcmp($bAt, $aAt);
        });

        return response()->json([
            'success' => true,
            'data' => $threads,
        ]);
    }

    /**
     * Driver: archive all messages in this shipment thread (set message status = archive).
     * POST /api/v1/driver/shipments/{id}/messages/archive
     */
    public function driverArchive(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->resolveDriverOrder($driver, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.'], 404);
        }

        if (empty($order['customer_id'])) {
            return response()->json(['success' => false, 'message' => 'Shipment has no linked customer account.'], 422);
        }

        $oid = (string) ($order['id'] ?? $id);
        $cid = (string) ($order['customer_id'] ?? '');
        $updated = 0;
        foreach ($this->orderMessagesForOrder($oid) as $m) {
            if ((string) ($m['customer_id'] ?? '') !== $cid) {
                continue;
            }
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['status' => OrderMessage::STATUS_ARCHIVE]);
            $updated++;
        }

        $this->firebase->touchOrderMessagesThreadUpdated($oid);

        return response()->json([
            'success' => true,
            'message' => 'Messages archived.',
            'archived_count' => $updated,
        ]);
    }

    /**
     * Driver: restore archived messages in this shipment thread (set message status = active).
     * POST /api/v1/driver/shipments/{id}/messages/unarchive
     */
    public function driverUnarchive(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->resolveDriverOrder($driver, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.'], 404);
        }

        if (empty($order['customer_id'])) {
            return response()->json(['success' => false, 'message' => 'Shipment has no linked customer account.'], 422);
        }

        $oid = (string) ($order['id'] ?? $id);
        $cid = (string) ($order['customer_id'] ?? '');
        $updated = 0;
        foreach ($this->orderMessagesForOrder($oid) as $m) {
            if ((string) ($m['customer_id'] ?? '') !== $cid) {
                continue;
            }
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['status' => OrderMessage::STATUS_ACTIVE]);
            $updated++;
        }

        $this->firebase->touchOrderMessagesThreadUpdated($oid);

        return response()->json([
            'success' => true,
            'message' => 'Messages restored.',
            'restored_count' => $updated,
        ]);
    }

    /**
     * Customer: list messages for an order.
     * GET /api/v1/orders/{id}/messages
     */
    public function customerMessages(Request $request, string $id): JsonResponse
    {
        $customer = $request->user();
        if (! $this->isCustomerUser($customer)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $order = $this->resolveCustomerOrder($customer, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (empty($order['driver_id'])) {
            return response()->json(['success' => false, 'message' => 'No driver assigned yet for this order.'], 422);
        }

        $driverId = (string) ($order['driver_id'] ?? '');
        $driverRow = $driverId !== '' ? $this->firestore->get('drivers', $driverId) : null;
        $driverName = trim((string) ($driverRow['name'] ?? ''));
        $driverPhone = trim((string) ($driverRow['phone'] ?? ''));
        $driverCode = trim((string) ($driverRow['driver_code'] ?? ''));

        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $page = max(1, (int) $request->query('page', 1));
        $cid = (string) $customer->id;
        $oid = (string) ($order['id'] ?? $id);

        $messages = $this->orderMessagesForOrder($oid)->filter(function (array $m) use ($cid) {
            return (string) ($m['customer_id'] ?? '') === $cid
                && strtolower((string) ($m['customer_status'] ?? OrderMessage::CUSTOMER_STATUS_ACTIVE)) === OrderMessage::CUSTOMER_STATUS_ACTIVE;
        })->sortBy(fn (array $m) => $this->messageTimestamp($m))->values();

        $total = $messages->count();
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($page, $lastPage);
        $items = $messages->forPage($page, $perPage)
            ->map(fn (array $m) => $this->formatMessage($m, OrderMessage::SENDER_CUSTOMER))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'order_id' => $oid,
            'customer_id' => $cid,
            'driver_id' => $driverId,
            'driver_name' => $driverName !== '' ? $driverName : 'Driver',
            'driver_phone' => $driverPhone,
            'driver_code' => $driverCode,
            'driver' => $driverRow ? [
                'id' => $driverId,
                'name' => $driverName !== '' ? $driverName : 'Driver',
                'phone' => $driverPhone,
                'driver_code' => $driverCode,
            ] : null,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Customer: send message to driver for an order.
     * POST /api/v1/orders/{id}/messages
     */
    public function customerSend(Request $request, string $id): JsonResponse
    {
        $customer = $request->user();
        if (! $this->isCustomerUser($customer)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $order = $this->resolveCustomerOrder($customer, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (empty($order['driver_id'])) {
            return response()->json(['success' => false, 'message' => 'No driver assigned yet for this order.'], 422);
        }

        $oid = (string) ($order['id'] ?? $id);
        $docId = $this->firestore->add('order_messages', [
            'order_id' => $oid,
            'driver_id' => (string) ($order['driver_id'] ?? ''),
            'customer_id' => (string) $customer->id,
            'sender_type' => OrderMessage::SENDER_CUSTOMER,
            'message' => trim((string) $request->input('message')),
            'status' => OrderMessage::STATUS_ACTIVE,
            'customer_status' => OrderMessage::CUSTOMER_STATUS_ACTIVE,
            'read_at' => null,
        ]);

        $row = $this->firestore->get('order_messages', $docId) ?? array_merge([
            'id' => $docId,
            'order_id' => $oid,
            'driver_id' => (string) ($order['driver_id'] ?? ''),
            'customer_id' => (string) $customer->id,
            'sender_type' => OrderMessage::SENDER_CUSTOMER,
            'message' => trim((string) $request->input('message')),
            'status' => OrderMessage::STATUS_ACTIVE,
            'customer_status' => OrderMessage::CUSTOMER_STATUS_ACTIVE,
        ], ['id' => $docId]);

        $formatted = $this->formatMessage($row, OrderMessage::SENDER_CUSTOMER);
        $this->firebase->syncOrderMessage($oid, $docId, $formatted);
        $this->fcmPush->sendOrderMessageToDriver($order, (string) ($row['message'] ?? ''));

        return response()->json([
            'success' => true,
            'data' => $formatted,
        ], 201);
    }

    /**
     * Customer: mark driver messages as read in this order thread.
     * POST /api/v1/orders/{id}/messages/read
     */
    public function customerMarkRead(Request $request, string $id): JsonResponse
    {
        $customer = $request->user();
        if (! $this->isCustomerUser($customer)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $order = $this->resolveCustomerOrder($customer, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $oid = (string) ($order['id'] ?? $id);
        $cid = (string) $customer->id;
        $readAt = now()->toIso8601String();

        foreach ($this->orderMessagesForOrder($oid) as $m) {
            if ((string) ($m['customer_id'] ?? '') !== $cid) {
                continue;
            }
            if ((string) ($m['sender_type'] ?? '') !== OrderMessage::SENDER_DRIVER) {
                continue;
            }
            if (! empty($m['read_at'])) {
                continue;
            }
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['read_at' => $readAt]);
            $this->firebase->updateOrderMessageReadAt($oid, $mid, $readAt);
        }

        return response()->json([
            'success' => true,
            'message' => 'Marked as read.',
        ]);
    }

    /**
     * Customer: archive all messages in this order thread (soft-delete from customer view).
     * POST /api/v1/orders/{id}/messages/archive
     */
    public function customerArchive(Request $request, string $id): JsonResponse
    {
        $customer = $request->user();
        if (! $this->isCustomerUser($customer)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $order = $this->resolveCustomerOrder($customer, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $oid = (string) ($order['id'] ?? $id);
        $cid = (string) $customer->id;
        $updated = 0;
        foreach ($this->orderMessagesForOrder($oid) as $m) {
            if ((string) ($m['customer_id'] ?? '') !== $cid) {
                continue;
            }
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['customer_status' => OrderMessage::CUSTOMER_STATUS_ARCHIVE]);
            $updated++;
        }

        $this->firebase->touchOrderMessagesThreadUpdated($oid);

        return response()->json([
            'success' => true,
            'message' => 'Messages archived.',
            'archived_count' => $updated,
        ]);
    }

    /**
     * Customer: archive selected messages (soft-delete from customer view).
     * POST /api/v1/orders/{id}/messages/archive-selected
     */
    public function customerArchiveSelected(Request $request, string $id): JsonResponse
    {
        $customer = $request->user();
        if (! $this->isCustomerUser($customer)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $order = $this->resolveCustomerOrder($customer, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $request->validate([
            'message_ids' => ['required', 'array'],
            'message_ids.*' => ['nullable'],
        ]);

        $rawIds = $request->input('message_ids', []);
        $messageIds = array_values(array_unique(array_filter(array_map(function ($v) {
            $s = trim((string) $v);

            return $s !== '' ? $s : null;
        }, is_array($rawIds) ? $rawIds : []))));

        if ($messageIds === []) {
            return response()->json(['success' => false, 'message' => 'No valid message IDs provided.'], 422);
        }

        $customerId = (string) $customer->id;
        $customerOrderIds = $this->orderIdsOwnedByCustomer($customerId);

        if ($customerOrderIds === []) {
            return response()->json([
                'success' => true,
                'message' => 'Selected messages archived.',
                'archived_count' => 0,
            ]);
        }

        $orderIdsTouched = [];
        $updated = 0;
        foreach ($messageIds as $mid) {
            $msg = $this->firestore->get('order_messages', $mid);
            if (! $msg) {
                continue;
            }
            if ((string) ($msg['customer_id'] ?? '') !== $customerId) {
                continue;
            }
            $msgOrderId = (string) ($msg['order_id'] ?? '');
            if (! in_array($msgOrderId, $customerOrderIds, true)) {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['customer_status' => OrderMessage::CUSTOMER_STATUS_ARCHIVE]);
            $updated++;
            $orderIdsTouched[$msgOrderId] = true;
        }

        foreach (array_keys($orderIdsTouched) as $oid) {
            $this->firebase->touchOrderMessagesThreadUpdated($oid);
        }

        return response()->json([
            'success' => true,
            'message' => 'Selected messages archived.',
            'archived_count' => $updated,
        ]);
    }

    /**
     * Customer: restore archived messages in this order thread.
     * POST /api/v1/orders/{id}/messages/unarchive
     */
    public function customerUnarchive(Request $request, string $id): JsonResponse
    {
        $customer = $request->user();
        if (! $this->isCustomerUser($customer)) {
            return response()->json(['success' => false, 'message' => 'Invalid user.'], 401);
        }

        $order = $this->resolveCustomerOrder($customer, $id);
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $oid = (string) ($order['id'] ?? $id);
        $cid = (string) $customer->id;
        $updated = 0;
        foreach ($this->orderMessagesForOrder($oid) as $m) {
            if ((string) ($m['customer_id'] ?? '') !== $cid) {
                continue;
            }
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['customer_status' => OrderMessage::CUSTOMER_STATUS_ACTIVE]);
            $updated++;
        }

        $this->firebase->touchOrderMessagesThreadUpdated($oid);

        return response()->json([
            'success' => true,
            'message' => 'Messages restored.',
            'restored_count' => $updated,
        ]);
    }
}
