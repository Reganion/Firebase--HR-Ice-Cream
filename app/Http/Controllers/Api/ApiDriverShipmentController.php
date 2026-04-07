<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AdminNotification;
use App\Support\OrderMessage;
use App\Services\DeliveryService;
use App\Services\DriverNotificationDispatcher;
use App\Services\FirebaseRealtimeService;
use App\Services\FirestoreService;
use App\Support\DriverStatuses;
use App\Support\FirestoreCacheKeys;
use App\Support\FirestoreDriverUser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApiDriverShipmentController extends Controller
{
    /** Stored on Firestore `orders.status` (same as Admin `toDatabaseOrderStatus`). */
    private const STATUS_OUT_FOR_DELIVERY = 'Out for Delivery';

    private const STATUS_COMPLETED = 'Completed';

    protected $deliveryService;

    public function __construct(
        DeliveryService $deliveryService,
        protected FirebaseRealtimeService $firebase,
        protected FirestoreService $firestore,
        protected DriverNotificationDispatcher $driverNotifications,
    ) {
        $this->deliveryService = $deliveryService;
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
     * @return Collection<int, array<string, mixed>>
     */
    private function ordersForDriver(string $driverId): Collection
    {
        $byId = [];
        foreach ($this->firestore->where('orders', 'driver_id', $driverId) as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $row;
            }
        }
        if (ctype_digit($driverId)) {
            foreach ($this->firestore->where('orders', 'driver_id', (int) $driverId) as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id !== '') {
                    $byId[$id] = $row;
                }
            }
        }

        return collect(array_values($byId));
    }

    private function getOrderForDriver(string $driverId, string $orderId): ?array
    {
        $order = $this->firestore->get('orders', $orderId);
        if (! $order) {
            return null;
        }
        $did = (string) ($order['driver_id'] ?? '');
        if ($did === '' || $did !== (string) $driverId) {
            return null;
        }

        return $order;
    }

    private function normalizeStatusLower(?string $status): string
    {
        return strtolower(trim((string) ($status ?? '')));
    }

    private function statusDriverLower(?string $status): string
    {
        return strtolower(trim((string) ($status ?? 'pending')));
    }

    /**
     * @param  array<int, string>  $allowedStatusLower
     */
    private function orderStatusMatches(array $order, array $allowedStatusLower): bool
    {
        $s = $this->normalizeStatusLower((string) ($order['status'] ?? ''));

        return in_array($s, $allowedStatusLower, true);
    }

    private function orderSortTimestamp(array $order): string
    {
        $d = $order['delivery_date'] ?? $order['created_at'] ?? '';

        return (string) $d;
    }

    private function notifyCustomerOrderStatus(array $order): void
    {
        $customerId = trim((string) ($order['customer_id'] ?? ''));
        if ($customerId === '') {
            return;
        }

        $normalized = $this->normalizeOrderStatusForNotify((string) ($order['status'] ?? ''));

        $message = match ($normalized) {
            'out_for_delivery' => 'Your order is out for delivery.',
            'completed', 'delivered' => 'Order successfully delivered.',
            default => null,
        };

        if ($message === null) {
            return;
        }

        $notifId = $this->firestore->add('customer_notifications', [
            'customer_id' => $customerId,
            'type' => 'order_status',
            'title' => (string) ($order['product_name'] ?? 'Order Update'),
            'message' => $message,
            'image_url' => (string) ($order['product_image'] ?? 'img/default-product.png'),
            'related_type' => 'Order',
            'related_id' => (string) ($order['id'] ?? ''),
            'data' => [
                'transaction_id' => (string) ($order['transaction_id'] ?? ''),
                'status' => $normalized,
            ],
            'read_at' => null,
        ]);

        try {
            $numericNotif = (int) preg_replace('/\D/', '', (string) $notifId) ?: crc32((string) $notifId);
            $this->firebase->syncNotification(
                $this->firebaseNumericCustomerId($customerId),
                $numericNotif,
                [
                    'id' => $notifId,
                    'type' => 'order_status',
                    'title' => (string) ($order['product_name'] ?? 'Order Update'),
                    'message' => $message,
                    'image_url' => (string) ($order['product_image'] ?? 'img/default-product.png'),
                    'related_type' => 'Order',
                    'related_id' => (string) ($order['id'] ?? ''),
                    'data' => [
                        'transaction_id' => (string) ($order['transaction_id'] ?? ''),
                        'status' => $normalized,
                    ],
                    'read_at' => null,
                    'created_at' => now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Mirrors Admin `normalizeOrderStatus` for notification text.
     */
    private function normalizeOrderStatusForNotify(string $status): string
    {
        $normalized = strtolower(trim($status));
        $normalized = str_replace('_', '-', preg_replace('/\s+/', '-', $normalized));

        if ($normalized === '' || $normalized === 'new-order' || $normalized === 'new') {
            return 'pending';
        }

        if ($normalized === 'walk-in' || $normalized === 'walkin') {
            return 'walk_in';
        }

        if ($normalized === 'preparing') {
            return 'preparing';
        }

        if ($normalized === 'delivered' || $normalized === 'completed') {
            return 'completed';
        }

        if ($normalized === 'assigned') {
            return 'assigned';
        }

        if ($normalized === 'cancelled' || $normalized === 'canceled') {
            return 'cancelled';
        }

        if ($normalized === 'ready') {
            return 'ready';
        }

        if ($normalized === 'out-for-delivery' || $normalized === 'out_for_delivery' || $normalized === 'out for delivery') {
            return 'out_for_delivery';
        }

        return $normalized;
    }

    private function createAdminNotificationForAll(
        string $type,
        string $title,
        ?string $message,
        ?string $imageUrl,
        string $relatedType,
        string $relatedId,
        array $data = []
    ): void {
        $admins = $this->firestore->all('admins');
        foreach ($admins as $admin) {
            if (empty($admin['id'])) {
                continue;
            }
            $this->firestore->add('admin_notifications', [
                'user_id' => (string) $admin['id'],
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'image_url' => $imageUrl,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'data' => $data,
                'read_at' => null,
            ]);
        }
        try {
            $this->firebase->touchAdminNotificationsUpdated();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Mark Firestore `order_messages` rows for this order as active (driver accepted).
     */
    private function reactivateOrderMessagesFirestore(string $orderId): void
    {
        foreach ($this->orderMessagesForOrder($orderId) as $m) {
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['status' => OrderMessage::STATUS_ACTIVE]);
        }
        try {
            $this->firebase->touchOrderMessagesThreadUpdated($orderId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orderMessagesForOrder(string $orderId): array
    {
        $out = [];
        foreach ($this->firestore->where('order_messages', 'order_id', (string) $orderId) as $row) {
            $out[] = $row;
        }
        if (ctype_digit($orderId)) {
            foreach ($this->firestore->where('order_messages', 'order_id', (int) $orderId) as $row) {
                $out[] = $row;
            }
        }

        return collect($out)->keyBy(fn ($r) => (string) ($r['id'] ?? ''))->values()->all();
    }

    private function archiveOrderMessagesFirestore(string $orderId): void
    {
        foreach ($this->orderMessagesForOrder($orderId) as $m) {
            $mid = (string) ($m['id'] ?? '');
            if ($mid === '') {
                continue;
            }
            $this->firestore->update('order_messages', $mid, ['status' => OrderMessage::STATUS_ARCHIVE]);
        }
        try {
            $this->firebase->touchOrderMessagesThreadUpdated($orderId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function customerHasOtherAcceptedOrder(array $order, string $excludeOrderId): bool
    {
        $customerId = $order['customer_id'] ?? null;
        if ($customerId === null || trim((string) $customerId) === '') {
            return false;
        }
        $cid = trim((string) $customerId);
        $candidates = collect($this->firestore->where('orders', 'customer_id', $cid));
        if ($candidates->isEmpty() && ctype_digit($cid)) {
            $candidates = collect($this->firestore->where('orders', 'customer_id', (int) $cid));
        }

        foreach ($candidates as $o) {
            if ((string) ($o['id'] ?? '') === $excludeOrderId) {
                continue;
            }
            if ($this->statusDriverLower((string) ($o['status_driver'] ?? '')) === 'accepted') {
                return true;
            }
        }

        return false;
    }

    private function deliveryProofUrl(Request $request, ?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        $path = str_replace('\\', '/', trim($path));
        $path = 'storage/'.ltrim($path, '/');
        $base = $request->getSchemeAndHttpHost();
        if ($base !== '') {
            return rtrim($base, '/').'/'.ltrim($path, '/');
        }

        return asset($path);
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function mapShipmentRow(array $order, string $tab, Request $request): array
    {
        $deliveryDate = ! empty($order['delivery_date']) ? Carbon::parse((string) $order['delivery_date']) : null;
        $deliveryTime = ! empty($order['delivery_time']) ? Carbon::parse((string) $order['delivery_time']) : null;
        $schedule = $this->formatSchedule($deliveryDate, $deliveryTime);
        $proofUrl = $this->deliveryProofUrl($request, isset($order['delivery_proof_image']) ? (string) $order['delivery_proof_image'] : null);
        $deliveredTime = ! empty($order['delivered_at']) ? Carbon::parse((string) $order['delivered_at'])->format('h:i A') : null;
        $deliveredDate = $deliveryDate ? $deliveryDate->format('d F Y') : null;
        $deliveredTimeCompact = ! empty($order['delivered_at']) ? strtoupper(Carbon::parse((string) $order['delivered_at'])->format('h:ia')) : null;
        $downpayment = (float) ($order['downpayment'] ?? 0.0);
        $amount = (float) ($order['amount'] ?? 0.0);
        $balance = (float) ($order['balance'] ?? max(0, $amount - $downpayment));
        $sd = $this->statusDriverLower((string) ($order['status_driver'] ?? ''));

        return [
            'id' => isset($order['id']) ? (string) $order['id'] : null,
            'transaction_id' => (string) ($order['transaction_id'] ?? ''),
            'transaction_label' => '#'.(string) ($order['transaction_id'] ?? ''),
            'product_name' => (string) ($order['product_name'] ?? '—'),
            'amount' => $amount,
            'amount_text' => 'PHP '.number_format($amount, 2),
            'downpayment' => $downpayment,
            'balance' => $balance,
            'expected_on' => $schedule,
            'location' => (string) ($order['delivery_address'] ?? '—'),
            'status' => $this->normalizeStatusLower((string) ($order['status'] ?? '')),
            'status_driver' => $sd,
            'received_amount' => isset($order['received_amount']) && $order['received_amount'] !== null ? (float) $order['received_amount'] : null,
            'delivery_payment_method' => (string) ($order['delivery_payment_method'] ?? ''),
            'delivery_proof_url' => $proofUrl,
            'delivery_proof_image' => (string) ($order['delivery_proof_image'] ?? ''),
            'proof_image_url' => $proofUrl,
            'proof_image' => (string) ($order['delivery_proof_image'] ?? ''),
            'expected_time' => $deliveryTime ? $deliveryTime->format('h:i A') : null,
            'time' => $deliveryTime ? $deliveryTime->format('h:i A') : null,
            'delivery_time_compact' => $this->formatTimeCompact(isset($order['delivery_time']) ? (string) $order['delivery_time'] : null),
            'delivered_time' => $deliveredTime,
            'delivered_time_compact' => $deliveredTimeCompact,
            'delivered_date' => $deliveredDate,
            'badge' => $this->badgeForTab($tab),
            'badge_color' => $this->badgeColorForTab($tab),
            'customer_name' => (string) ($order['customer_name'] ?? ''),
            'customer_phone' => (string) ($order['customer_phone'] ?? ''),
            'customer_id' => trim((string) ($order['customer_id'] ?? '')),
            'delivery_lat' => $this->nullableFloatCoord($order['delivery_lat'] ?? null),
            'delivery_lng' => $this->nullableFloatCoord($order['delivery_lng'] ?? null),
            'customer_current_lat' => $this->nullableFloatCoord($order['customer_current_lat'] ?? null),
            'customer_current_lng' => $this->nullableFloatCoord($order['customer_current_lng'] ?? null),
            'customer_location_updated_at' => $order['customer_location_updated_at'] ?? null,
            'delivery_date' => $deliveryDate ? $deliveryDate->format('Y-m-d') : null,
            'delivery_time' => $deliveryTime ? $deliveryTime->format('H:i') : null,
        ];
    }

    /**
     * Driver shipments list by tab (Firestore `orders`).
     * GET /api/v1/driver/shipments?tab=incoming|accepted|completed&search=...
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $driverId = (string) $driver->id;
        $tab = strtolower((string) $request->query('tab', 'incoming'));
        if (! in_array($tab, ['incoming', 'accepted', 'completed'], true)) {
            $tab = 'incoming';
        }

        $statuses = $this->statusesForTab($tab);

        $orders = $this->ordersForDriver($driverId)->filter(function (array $o) use ($tab, $statuses) {
            $sd = $this->statusDriverLower((string) ($o['status_driver'] ?? ''));
            if ($tab === 'incoming' && $sd !== 'pending') {
                return false;
            }
            if ($tab === 'accepted' && $sd !== 'accepted') {
                return false;
            }
            if ($tab === 'completed' && $sd !== 'completed') {
                return false;
            }

            return $this->orderStatusMatches($o, $statuses);
        });

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $orders = $orders->filter(function (array $o) use ($search) {
                $hay = [
                    (string) ($o['transaction_id'] ?? ''),
                    (string) ($o['product_name'] ?? ''),
                    (string) ($o['delivery_address'] ?? ''),
                    (string) ($o['customer_name'] ?? ''),
                ];

                foreach ($hay as $h) {
                    if ($h !== '' && stripos($h, $search) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        $orders = $orders->sortByDesc(fn (array $o) => $this->orderSortTimestamp($o))->values();

        $shipments = $orders->map(fn (array $o) => $this->mapShipmentRow($o, $tab, $request))->values();

        return response()->json([
            'success' => true,
            'tab' => $tab,
            'count' => $shipments->count(),
            'shipments' => $shipments,
        ]);
    }

    /**
     * Driver shipment details.
     * GET /api/v1/driver/shipments/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->getOrderForDriver((string) $driver->id, $id);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.',
            ], 404);
        }

        $deliveryDate = ! empty($order['delivery_date']) ? Carbon::parse((string) $order['delivery_date']) : null;
        $deliveryTime = ! empty($order['delivery_time']) ? Carbon::parse((string) $order['delivery_time']) : null;
        $proofUrl = $this->deliveryProofUrl($request, isset($order['delivery_proof_image']) ? (string) $order['delivery_proof_image'] : null);
        $deliveredTime = ! empty($order['delivered_at']) ? Carbon::parse((string) $order['delivered_at'])->format('h:i A') : null;
        $deliveredDate = $deliveryDate ? $deliveryDate->format('d F Y') : null;
        $deliveredTimeCompact = ! empty($order['delivered_at']) ? strtoupper(Carbon::parse((string) $order['delivered_at'])->format('h:ia')) : null;
        $downpayment = (float) ($order['downpayment'] ?? 0.0);
        $amount = (float) ($order['amount'] ?? 0.0);
        $balance = (float) ($order['balance'] ?? max(0, $amount - $downpayment));
        $sd = $this->statusDriverLower((string) ($order['status_driver'] ?? ''));

        return response()->json([
            'success' => true,
            'shipment' => [
                'id' => isset($order['id']) ? (string) $order['id'] : null,
                'transaction_id' => (string) ($order['transaction_id'] ?? ''),
                'transaction_label' => '#'.(string) ($order['transaction_id'] ?? ''),
                'expected_on' => $this->formatSchedule($deliveryDate, $deliveryTime),
                'customer_name' => (string) ($order['customer_name'] ?? ''),
                'customer_phone' => (string) ($order['customer_phone'] ?? ''),
                'customer_id' => trim((string) ($order['customer_id'] ?? '')),
                'delivery_address' => (string) ($order['delivery_address'] ?? '—'),
                'quantity' => (int) ($order['qty'] ?? 1),
                'size' => (string) ($order['gallon_size'] ?? ''),
                'order_name' => (string) ($order['product_name'] ?? ''),
                'order_type' => (string) ($order['product_type'] ?? ''),
                'cost' => $amount,
                'cost_text' => 'PHP '.number_format($amount, 2),
                'downpayment' => $downpayment,
                'balance' => $balance,
                'status' => $this->normalizeStatusLower((string) ($order['status'] ?? '')),
                'status_driver' => $sd,
                'received_amount' => isset($order['received_amount']) && $order['received_amount'] !== null ? (float) $order['received_amount'] : null,
                'delivery_payment_method' => (string) ($order['delivery_payment_method'] ?? ''),
                'delivery_proof_url' => $proofUrl,
                'delivery_proof_image' => (string) ($order['delivery_proof_image'] ?? ''),
                'proof_image_url' => $proofUrl,
                'proof_image' => (string) ($order['delivery_proof_image'] ?? ''),
                'expected_time' => $deliveryTime ? $deliveryTime->format('h:i A') : null,
                'time' => $deliveryTime ? $deliveryTime->format('h:i A') : null,
                'delivery_time_compact' => $this->formatTimeCompact(isset($order['delivery_time']) ? (string) $order['delivery_time'] : null),
                'delivered_time' => $deliveredTime,
                'delivered_time_compact' => $deliveredTimeCompact,
                'delivered_date' => $deliveredDate,
                'delivery_lat' => $this->nullableFloatCoord($order['delivery_lat'] ?? null),
                'delivery_lng' => $this->nullableFloatCoord($order['delivery_lng'] ?? null),
                'customer_current_lat' => $this->nullableFloatCoord($order['customer_current_lat'] ?? null),
                'customer_current_lng' => $this->nullableFloatCoord($order['customer_current_lng'] ?? null),
                'customer_location_updated_at' => $order['customer_location_updated_at'] ?? null,
            ],
        ]);
    }

    private function nullableFloatCoord(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    /**
     * Driver accepts a shipment.
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->getOrderForDriver((string) $driver->id, $id);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.',
            ], 404);
        }

        if ($this->normalizeStatusLower((string) ($order['status'] ?? '')) !== 'assigned') {
            return response()->json([
                'success' => false,
                'message' => 'Only assigned shipments can be accepted.',
            ], 422);
        }

        if ($this->statusDriverLower((string) ($order['status_driver'] ?? '')) !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Shipment was already accepted.',
            ], 422);
        }

        $this->firestore->update('orders', $id, [
            'status_driver' => 'accepted',
        ]);
        FirestoreCacheKeys::invalidateOrders();

        $this->createAdminNotificationForAll(
            AdminNotification::TYPE_ORDER_DRIVER_ACCEPTED,
            (string) ($order['customer_name'] ?? 'Customer'),
            null,
            (string) ($order['product_image'] ?? 'img/default-product.png'),
            'Order',
            $id,
            [
                'subtitle' => 'Driver accepted Order #'.($order['transaction_id'] ?? ''),
                'highlight' => (string) ($order['product_name'] ?? ''),
            ]
        );

        $this->firebase->touchOrdersUpdated();
        $this->reactivateOrderMessagesFirestore($id);

        return response()->json([
            'success' => true,
            'message' => 'Shipment accepted.',
        ]);
    }

    /**
     * Driver rejects shipment.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->getOrderForDriver((string) $driver->id, $id);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.',
            ], 404);
        }

        $this->firestore->update('orders', $id, [
            'status' => 'pending',
            'driver_id' => null,
            'status_driver' => 'pending',
        ]);
        FirestoreCacheKeys::invalidateOrders();

        $this->firebase->touchOrdersUpdated();

        return response()->json([
            'success' => true,
            'message' => 'Shipment rejected and moved back to pending.',
        ]);
    }

    /**
     * Driver starts the route for an accepted shipment.
     */
    public function deliver(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->getOrderForDriver((string) $driver->id, $id);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.',
            ], 404);
        }

        $orderStatus = $this->normalizeStatusLower((string) ($order['status'] ?? ''));
        $allowedForDeliver = ['assigned', 'preparing', 'ready'];
        if (! in_array($orderStatus, $allowedForDeliver, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only assigned, preparing, or ready shipments can be started for delivery.',
            ], 422);
        }

        if ($this->statusDriverLower((string) ($order['status_driver'] ?? '')) !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Shipment must be accepted before starting delivery.',
            ], 422);
        }

        $this->firestore->update('drivers', (string) $driver->id, ['status' => DriverStatuses::ON_ROUTE]);
        FirestoreCacheKeys::invalidateDrivers();

        $this->firestore->update('orders', $id, [
            'status' => self::STATUS_OUT_FOR_DELIVERY,
            'status_driver' => 'accepted',
        ]);
        FirestoreCacheKeys::invalidateOrders();

        $updated = $this->firestore->get('orders', $id) ?? array_merge($order, [
            'status' => self::STATUS_OUT_FOR_DELIVERY,
            'status_driver' => 'accepted',
        ]);
        $this->notifyCustomerOrderStatus($updated);

        $this->createAdminNotificationForAll(
            AdminNotification::TYPE_ORDER_OUT_FOR_DELIVERY,
            (string) ($order['customer_name'] ?? 'Customer'),
            null,
            (string) ($order['product_image'] ?? 'img/default-product.png'),
            'Order',
            $id,
            [
                'subtitle' => 'Driver is out for delivery for Order #'.($order['transaction_id'] ?? ''),
                'highlight' => (string) ($order['product_name'] ?? ''),
            ]
        );

        $this->firebase->touchOrdersUpdated();

        $coords = $this->deliveryService->geocodeAddress((string) ($order['delivery_address'] ?? ''));

        return response()->json([
            'success' => true,
            'message' => 'Driver is now on route.',
            'destrination' => $coords,
        ]);
    }

    /**
     * Driver completes delivery after collecting amount and proof image.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();
        if (! FirestoreDriverUser::isAuthenticatedUser($driver)) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        $order = $this->getOrderForDriver((string) $driver->id, $id);
        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.',
            ], 404);
        }

        $orderStatus = $this->normalizeStatusLower((string) ($order['status'] ?? ''));
        $allowedForComplete = ['assigned', 'preparing', 'ready', 'out for delivery', 'out_of_delivery'];
        if (! in_array($orderStatus, $allowedForComplete, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only assigned or out-for-delivery shipments can be submitted.',
            ], 422);
        }

        if ($this->statusDriverLower((string) ($order['status_driver'] ?? '')) !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Shipment must be accepted before completion.',
            ], 422);
        }

        $validated = $request->validate([
            'received_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'proof_photo' => ['required', 'image', 'max:5120'],
        ]);

        $proofPath = $request->file('proof_photo')->store('delivery-proofs', 'public');

        $newReceived = (float) $validated['received_amount'];
        $amount = (float) ($order['amount'] ?? 0.0);
        $balance = max(0, $amount - $newReceived);

        $this->firestore->update('orders', $id, [
            'received_amount' => $newReceived,
            'status' => self::STATUS_COMPLETED,
            'status_driver' => 'completed',
            'delivery_payment_method' => (string) ($validated['payment_method'] ?? ''),
            'delivery_proof_image' => $proofPath,
            'delivered_at' => now()->toIso8601String(),
            'balance' => $balance,
        ]);
        FirestoreCacheKeys::invalidateOrders();

        $updated = $this->firestore->get('orders', $id) ?? array_merge($order, [
            'received_amount' => $newReceived,
            'status' => self::STATUS_COMPLETED,
            'status_driver' => 'completed',
            'delivery_proof_image' => $proofPath,
            'delivered_at' => now()->toIso8601String(),
            'balance' => $balance,
        ]);
        $this->notifyCustomerOrderStatus($updated);

        $this->createAdminNotificationForAll(
            AdminNotification::TYPE_DELIVERY_SUCCESS,
            (string) ($order['customer_name'] ?? 'Customer'),
            'Successfully delivered',
            (string) ($order['product_image'] ?? 'img/default-product.png'),
            'Order',
            $id,
            [
                'subtitle' => 'Order #'.($order['transaction_id'] ?? ''),
                'highlight' => 'Delivered successfully',
            ]
        );

        $this->driverNotifications->createAndNotify((string) $driver->id, [
            'driver_id' => (string) $driver->id,
            'type' => 'shipment_completed',
            'title' => 'Delivered Successfully',
            'message' => 'Booking has been delivered completely.',
            'image_url' => (string) ($order['product_image'] ?? 'img/default-product.png'),
            'related_type' => 'Order',
            'related_id' => $id,
            'data' => [
                'transaction_id' => (string) ($order['transaction_id'] ?? ''),
                'status' => 'completed',
            ],
            'read_at' => null,
        ]);

        $this->firebase->touchOrdersUpdated();

        if (! $this->customerHasOtherAcceptedOrder($order, $id)) {
            $this->archiveOrderMessagesFirestore($id);
        }

        $hasOutForDelivery = $this->ordersForDriver((string) $driver->id)
            ->filter(fn (array $o) => (string) ($o['id'] ?? '') !== $id)
            ->contains(function (array $o) {
                $s = $this->normalizeStatusLower((string) ($o['status'] ?? ''));

                return in_array($s, ['out for delivery', 'out_of_delivery'], true);
            });

        if (! $hasOutForDelivery) {
            $this->firestore->update('drivers', (string) $driver->id, ['status' => DriverStatuses::AVAILABLE]);
            FirestoreCacheKeys::invalidateDrivers();
        }

        $proofUrl = $this->deliveryProofUrl($request, $proofPath);

        return response()->json([
            'success' => true,
            'message' => 'Shipment marked completed.',
            'shipment' => [
                'id' => (string) ($updated['id'] ?? $order['id'] ?? $id),
                'status' => 'completed',
                'status_driver' => 'completed',
                'received_amount' => $newReceived,
                'balance' => $balance,
                'delivery_payment_method' => (string) ($validated['payment_method'] ?? ''),
                'delivery_proof_url' => $proofUrl,
                'delivery_proof_image' => $proofPath,
                'proof_image_url' => $proofUrl,
                'proof_image' => $proofPath,
                'delivery_time_compact' => $this->formatTimeCompact(isset($order['delivery_time']) ? (string) $order['delivery_time'] : null),
                'delivered_time' => Carbon::parse((string) $updated['delivered_at'])->format('h:i A'),
                'delivered_time_compact' => strtoupper(Carbon::parse((string) $updated['delivered_at'])->format('h:ia')),
                'delivered_date' => ! empty($order['delivery_date']) ? Carbon::parse((string) $order['delivery_date'])->format('d F Y') : null,
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function statusesForTab(string $tab): array
    {
        return match ($tab) {
            'accepted' => [
                'assigned',
                'preparing',
                'ready',
                'out for delivery',
                'out_of_delivery',
            ],
            'completed' => ['completed'],
            default => ['assigned'],
        };
    }

    private function formatSchedule(?Carbon $deliveryDate, ?Carbon $deliveryTime): string
    {
        if (! $deliveryDate && ! $deliveryTime) {
            return '—';
        }

        $datePart = $deliveryDate ? $deliveryDate->format('d M') : null;
        $timePart = $deliveryTime ? $deliveryTime->format('h:i A') : null;

        if ($datePart && $timePart) {
            return $datePart.', '.$timePart;
        }

        return (string) ($datePart ?? $timePart ?? '—');
    }

    private function badgeForTab(string $tab): string
    {
        return match ($tab) {
            'accepted' => 'Pending',
            'completed' => 'Completed',
            default => 'New',
        };
    }

    private function badgeColorForTab(string $tab): string
    {
        return match ($tab) {
            'accepted' => '#FF6805',
            'completed' => '#00AE2A',
            default => '#007CFF',
        };
    }

    private function formatTimeCompact(?string $time): ?string
    {
        $value = trim((string) ($time ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return strtoupper(Carbon::parse($value)->format('h:ia'));
        } catch (\Throwable) {
            return $value;
        }
    }
}
