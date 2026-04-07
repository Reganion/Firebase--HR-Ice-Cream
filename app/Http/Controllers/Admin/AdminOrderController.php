<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DriverNotificationDispatcher;
use App\Services\FirestoreService;
use App\Services\FirebaseRealtimeService;
use App\Support\FirestoreCacheKeys;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminOrderController extends Controller
{
    private const DRIVER_STATUS_AVAILABLE = 'available';
    private const DRIVER_STATUS_PENDING = 'pending';

    public function __construct(
        protected FirebaseRealtimeService $firebase,
        protected FirestoreService $firestore,
        protected DriverNotificationDispatcher $driverNotifications,
    ) {}

    private function toDatabaseOrderStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) ($status ?? '')));
        $normalized = str_replace('_', '-', preg_replace('/\s+/', '-', $normalized));

        return match ($normalized) {
            '', 'new', 'new-order', 'new_order', 'pending' => 'pending',
            'walk-in', 'walkin' => 'Walk-in',
            'preparing' => 'Preparing',
            'assigned' => 'Assigned',
            'completed', 'delivered' => 'Completed',
            'cancelled', 'canceled' => 'Cancelled',
            'ready' => 'Ready',
            'out-for-delivery', 'out_for_delivery', 'out for delivery' => 'Out for Delivery',
            default => 'pending',
        };
    }

    /**
     * Send a customer notification for a given order status change.
     */
    private function notifyCustomerOrderStatus(array $order): void
    {
        $customerId = (int) ($order['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return;
        }

        $normalized = $this->normalizeOrderStatus((string) ($order['status'] ?? ''));

        $message = match ($normalized) {
            'pending'          => 'Your order request was placed successfully.',
            'assigned'         => 'Your order has been assigned to a driver.',
            'preparing'        => 'Your order is now being prepared.',
            'ready'            => 'Your order is ready for delivery.',
            'out_for_delivery' => 'Your order is out for delivery.',
            'completed'        => 'Order successfully delivered.',
            'cancelled'        => 'Order request cancelled successfully by the owner.',
            default            => null,
        };

        if ($message === null) {
            return;
        }

        $notificationId = $this->firestore->add('customer_notifications', [
            'customer_id'  => $customerId,
            'type'         => 'order_status',
            'title'        => (string) ($order['product_name'] ?? 'Order Update'),
            'message'      => $message,
            'image_url'    => (string) ($order['product_image'] ?? 'img/default-product.png'),
            'related_type' => 'Order',
            'related_id'   => (string) ($order['id'] ?? ''),
            'data'         => [
                'transaction_id' => (string) ($order['transaction_id'] ?? ''),
                'status'         => $normalized,
            ],
            'read_at' => null,
        ]);

        $this->firebase->syncNotification($customerId, (int) preg_replace('/\D/', '', $notificationId), [
            'id' => $notificationId,
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
        ]);
    }

    private function normalizeOrderStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) ($status ?? '')));
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

        if ($normalized === 'out-for-delivery' || $normalized === 'out_for_delivery') {
            return 'out_for_delivery';
        }

        return $normalized;
    }

    /**
     * Return a product image path that exists on disk, or the default placeholder.
     */
    private function resolveProductImagePath(?string $path): string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '' || str_starts_with($path, 'http')) {
            return 'img/default-product.png';
        }
        if (! is_file(public_path($path))) {
            return 'img/default-product.png';
        }
        return $path;
    }

    private function createAdminNotificationForAll(string $type, string $title, string $message, ?string $imageUrl, string $relatedType, string $relatedId, array $data = []): void
    {
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
        $this->firebase->touchAdminNotificationsUpdated();
    }

    private function getCustomerEmail(?string $customerId): ?string
    {
        if (!$customerId) {
            return null;
        }
        $customer = $this->firestore->get('customers', (string) $customerId);
        return $customer['email'] ?? null;
    }

    /**
     * Return orders as JSON for real-time polling on the admin orders page.
     * When scope=this_month, only returns orders from the start of the current month.
     */
    public function listJson(Request $request)
    {
        $scope = $request->get('scope');

        if ($scope === 'records') {
            $orders = collect($this->firestore->whereIn('orders', 'status', [
                'Completed', 'completed',
                'Delivered', 'delivered',
                'Cancelled', 'cancelled', 'Canceled', 'canceled',
            ]));
        } else {
            $orders = collect($this->firestore->all('orders'));
        }

        $driversById = collect($this->firestore->all('drivers'))->keyBy('id');

        if ($scope === 'this_month') {
            $start = Carbon::now()->startOfMonth();
            $orders = $orders->filter(fn ($o) => ! empty($o['created_at']) && Carbon::parse((string) $o['created_at'])->gte($start));
        }

        $customerEmailById = [];
        foreach ($orders->pluck('customer_id')->filter()->unique() as $cid) {
            $key = (string) $cid;
            $cust = $this->firestore->get('customers', $key);
            $customerEmailById[$key] = $cust['email'] ?? null;
        }

        $rank = function (string $status): int {
            $s = strtolower(trim($status));
            return match (true) {
                in_array($s, ['pending', 'new_order'], true) => 1,
                $s === 'preparing' => 2,
                in_array($s, ['walk_in', 'walk-in', 'walk in', 'walkin'], true) => 3,
                $s === 'assigned' => 4,
                $s === 'ready' => 5,
                in_array($s, ['out for delivery', 'out_for_delivery'], true) => 6,
                in_array($s, ['completed', 'delivered'], true) => 7,
                $s === 'cancelled' => 8,
                default => 9,
            };
        };

        $orders = $orders->sort(function ($a, $b) use ($rank) {
            $ar = $rank((string) ($a['status'] ?? ''));
            $br = $rank((string) ($b['status'] ?? ''));
            if ($ar !== $br) {
                return $ar <=> $br;
            }
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        })->values();

        $data = $orders->map(function (array $order) use ($driversById, $customerEmailById) {
            $deliveryDate = !empty($order['delivery_date']) ? Carbon::parse((string) $order['delivery_date']) : null;
            $deliveryTime = !empty($order['delivery_time']) ? Carbon::parse((string) $order['delivery_time']) : null;
            $createdAt = !empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null;
            $status = $this->normalizeOrderStatus((string) ($order['status'] ?? ''));
            $downpayment = (float) ($order['downpayment'] ?? 0.0);
            $amount = (float) ($order['amount'] ?? 0);
            $balance = (float) ($order['balance'] ?? max(0, $amount - $downpayment));
            $driver = !empty($order['driver_id']) ? $driversById->get((string) $order['driver_id']) : null;

            return [
                'id' => (string) ($order['id'] ?? ''),
                'transaction_id' => (string) ($order['transaction_id'] ?? ''),
                'product_name' => (string) ($order['product_name'] ?? ''),
                'product_type' => (string) ($order['product_type'] ?? ''),
                'gallon_size' => (string) ($order['gallon_size'] ?? ''),
                'product_image_url' => asset($this->resolveProductImagePath((string) ($order['product_image'] ?? ''))),
                'customer_name' => (string) ($order['customer_name'] ?? ''),
                'customer_phone' => (string) ($order['customer_phone'] ?? ''),
                'customer_image_url' => asset((string) ($order['customer_image'] ?? 'img/default-user.png')),
                'customer_email' => $customerEmailById[(string) ($order['customer_id'] ?? '')] ?? null,
                'delivery_address' => (string) ($order['delivery_address'] ?? ''),
                'amount' => $amount,
                'downpayment' => $downpayment,
                'balance' => $balance,
                'quantity' => (int) ($order['qty'] ?? 1),
                'payment_method' => (string) ($order['payment_method'] ?? ''),
                'status' => $status,
                'driver_id' => $order['driver_id'] ?? null,
                'driver_name' => $driver['name'] ?? null,
                'driver_phone' => $driver['phone'] ?? null,
                'driver_image_url' => $driver ? asset((string) ($driver['image'] ?? 'img/default-user.png')) : null,
                'status_driver' => strtolower((string) ($order['status_driver'] ?? 'pending')),
                'created_at_formatted' => $createdAt ? $createdAt->format('d M Y') : '—',
                'delivery_date' => $deliveryDate ? $deliveryDate->format('Y-m-d') : '',
                'delivery_time' => $deliveryTime ? $deliveryTime->format('H:i') : '',
                'delivery_date_formatted' => $deliveryDate ? $deliveryDate->format('d M') : '',
                'delivery_time_formatted' => $deliveryTime ? $deliveryTime->format('h:i A') : '',
            ];
        });

        return response()->json(['orders' => $data]);
    }

    /**
     * Return a single order as JSON (for notification order-details modal).
     */
    public function showJson(string $id)
    {
        $order = $this->firestore->get('orders', $id);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $deliveryDate = !empty($order['delivery_date']) ? Carbon::parse((string) $order['delivery_date']) : null;
        $deliveryTime = !empty($order['delivery_time']) ? Carbon::parse((string) $order['delivery_time']) : null;
        $createdAt = !empty($order['created_at']) ? Carbon::parse((string) $order['created_at']) : null;
        $driver = !empty($order['driver_id']) ? $this->firestore->get('drivers', (string) $order['driver_id']) : null;
        $status = $this->normalizeOrderStatus((string) ($order['status'] ?? ''));
        $downpayment = (float) ($order['downpayment'] ?? 0.0);
        $amount = (float) ($order['amount'] ?? 0);
        $balance = (float) ($order['balance'] ?? max(0, $amount - $downpayment));

        $data = [
            'id' => (string) ($order['id'] ?? ''),
            'transaction_id' => (string) ($order['transaction_id'] ?? ''),
            'product_name' => (string) ($order['product_name'] ?? ''),
            'product_type' => (string) ($order['product_type'] ?? ''),
            'gallon_size' => (string) ($order['gallon_size'] ?? ''),
            'product_image_url' => asset($this->resolveProductImagePath((string) ($order['product_image'] ?? ''))),
            'customer_name' => (string) ($order['customer_name'] ?? ''),
            'customer_phone' => (string) ($order['customer_phone'] ?? ''),
            'customer_image_url' => asset((string) ($order['customer_image'] ?? 'img/default-user.png')),
            'customer_email' => $this->getCustomerEmail((string) ($order['customer_id'] ?? '')),
            'delivery_address' => (string) ($order['delivery_address'] ?? ''),
            'amount' => $amount,
            'downpayment' => $downpayment,
            'balance' => $balance,
            'quantity' => (int) ($order['qty'] ?? 1),
            'payment_method' => (string) ($order['payment_method'] ?? ''),
            'status' => $status,
            'driver_id' => $order['driver_id'] ?? null,
            'driver_name' => $driver['name'] ?? null,
            'driver_phone' => $driver['phone'] ?? null,
            'driver_image_url' => $driver ? asset((string) ($driver['image'] ?? 'img/default-user.png')) : null,
            'status_driver' => strtolower((string) ($order['status_driver'] ?? 'pending')),
            'created_at_formatted' => $createdAt ? $createdAt->format('d M Y') : '—',
            'delivery_date' => $deliveryDate ? $deliveryDate->format('Y-m-d') : '',
            'delivery_time' => $deliveryTime ? $deliveryTime->format('H:i') : '',
            'delivery_date_formatted' => $deliveryDate ? $deliveryDate->format('d M Y') : '',
            'delivery_time_formatted' => $deliveryTime ? $deliveryTime->format('h:i A') : '',
        ];

        return response()->json(['order' => $data]);
    }

    public function storeWalkIn(Request $request)
    {
        $request->validate([
            'product_name'     => 'required|string|max:255',
            'product_type'     => 'required|string|max:255',
            'gallon_size'      => 'required|string|max:50',
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'required|string|max:20',
            'delivery_date'    => 'required|date',
            'delivery_time'    => 'required',
            'delivery_address' => 'required|string|max:255',
            'amount'           => 'required|numeric|min:0',
            'payment_method'   => 'required|string|max:50',
            'qty'              => 'nullable|integer|min:1',
        ]);

        $flavor = $this->firestore->firstWhere('flavors', 'name', (string) $request->product_name);
        $productImage = $flavor['image'] ?? 'img/default-product.png';

        $orderId = $this->firestore->add('orders', [
            'transaction_id'   => strtoupper(Str::random(10)),
            'product_name'     => $request->product_name,
            'product_type'     => $request->product_type,
            'gallon_size'      => $request->gallon_size,
            'product_image'    => $productImage,
            'customer_name'    => $request->customer_name,
            'customer_phone'   => $request->customer_phone,
            'customer_image'   => 'img/default-user.png',
            'delivery_date'    => $request->delivery_date,
            'delivery_time'    => $request->delivery_time,
            'delivery_address' => $request->delivery_address,
            'amount'           => $request->amount,
            'qty'              => (int) $request->input('quantity', 1),
            'payment_method'   => $request->payment_method,
            'status'           => $this->toDatabaseOrderStatus('walk_in'),
        ]);
        $order = $this->firestore->get('orders', $orderId) ?? ['id' => $orderId];
        FirestoreCacheKeys::invalidateOrders();

        $this->createAdminNotificationForAll(
            'order_created',
            (string) ($order['customer_name'] ?? 'Order'),
            'Order #' . (string) ($order['transaction_id'] ?? ''),
            $productImage,
            'Order',
            (string) ($order['id'] ?? $orderId),
            ['subtitle' => 'Order #' . (string) ($order['transaction_id'] ?? ''), 'highlight' => (string) ($order['product_name'] ?? '')]
        );

        $this->firebase->touchOrdersUpdated();

        return back()->with('success', 'Walk-in order added successfully.');
    }

    public function updateWalkIn(Request $request, string $id)
    {
        $order = $this->firestore->get('orders', $id);
        if (!$order) {
            return back()->with('error', 'Order not found.');
        }
        $currentStatus = $this->normalizeOrderStatus((string) ($order['status'] ?? ''));

        // For pending/assigned/preparing orders, only allow flavor, quantity, and status edits.
        if (in_array($currentStatus, ['pending', 'assigned', 'preparing'], true)) {
            $request->validate([
                'product_name' => 'required|string|max:255',
                'gallon_size' => 'required|string|max:50',
                'qty' => 'required|integer|min:1',
                'status' => 'required|string',
            ]);

            $requestedStatus = $this->normalizeOrderStatus($request->input('status'));
            $driverAccepted = strtolower((string) ($order['status_driver'] ?? '')) === 'accepted';
            $allowedNext = match ($currentStatus) {
                'pending' => ['pending', 'preparing'],
                'assigned' => $driverAccepted ? ['assigned', 'preparing'] : ['assigned'],
                'preparing' => ['preparing', 'ready'],
                default => [$currentStatus],
            };
            if (!in_array($requestedStatus, $allowedNext, true)) {
                $message = match (true) {
                    $currentStatus === 'preparing' => 'For this order, status can only be Preparing or Ready.',
                    $currentStatus === 'assigned' && !$driverAccepted => 'Cannot change to Preparing until the driver has accepted the order.',
                    default => 'For this order, status can only be the previous status or Preparing.',
                };
                return back()->withErrors(['status' => $message])->withInput();
            }

            $flavor = $this->firestore->firstWhere('flavors', 'name', (string) $request->product_name);
            $gallon = $this->firestore->firstWhere('gallons', 'size', (string) $request->gallon_size);
            $newType = $request->input('product_type');
            if ($newType === null || trim((string) $newType) === '') {
                $newType = ($flavor['category'] ?? null) ?: ($order['product_type'] ?? null);
            }
            $qty = (int) $request->input('qty', 1);
            $unitPrice = (float) (($flavor['price'] ?? 0) + ($gallon['addon_price'] ?? 0));
            $totalAmount = round($unitPrice * max($qty, 1), 2);

            $updateData = [
                'product_name' => $request->product_name,
                'product_type' => $newType,
                'gallon_size' => $request->gallon_size,
                'product_image' => ($flavor['image'] ?? null) ?: (($order['product_image'] ?? 'img/default-product.png')),
                'qty' => $qty,
                'amount' => $totalAmount,
                'status' => $this->toDatabaseOrderStatus($requestedStatus),
            ];
            // Keep status_driver unchanged when only updating order status (Assigned → Preparing → Ready)
            if (!empty($order['driver_id'])) {
                $updateData['status_driver'] = $order['status_driver'] ?? 'accepted';
            }
            $oldStatus = (string) ($order['status'] ?? '');
            $this->firestore->update('orders', $id, $updateData);
            FirestoreCacheKeys::invalidateOrders();
            $updatedOrder = $this->firestore->get('orders', $id) ?? array_merge($order, $updateData, ['id' => $id]);
            if (strtolower($oldStatus) !== strtolower((string) ($updateData['status'] ?? $oldStatus))) {
                $this->notifyCustomerOrderStatus($updatedOrder);
            }

            if (strtolower($oldStatus) !== strtolower((string) ($updateData['status'] ?? $oldStatus)) && $this->normalizeOrderStatus((string) ($updatedOrder['status'] ?? '')) === 'completed') {
                $this->createAdminNotificationForAll(
                    'order_delivered',
                    (string) ($updatedOrder['product_name'] ?? 'Order'),
                    'Successfully delivered',
                    null,
                    'Order',
                    (string) ($updatedOrder['id'] ?? $id),
                    ['subtitle' => 'delivered', 'highlight' => 'Successfully']
                );
            }

            $this->firebase->touchOrdersUpdated();

            return back()->with('success', 'Order updated successfully.');
        }

        $request->validate([
            'product_name'     => 'required|string|max:255',
            'product_type'     => 'required|string|max:255',
            'gallon_size'      => 'required|string|max:50',
            'customer_name'    => 'required|string|max:255',
            'customer_phone'   => 'required|string|max:20',
            'delivery_date'    => 'required|date',
            'delivery_time'    => 'required',
            'delivery_address' => 'required|string|max:255',
            'amount'           => 'required|numeric|min:0',
            'payment_method'   => 'required|string|max:50',
        ]);

        $flavor = $this->firestore->firstWhere('flavors', 'name', (string) $request->product_name);
        $productImage = ($flavor['image'] ?? null) ?: (($order['product_image'] ?? 'img/default-product.png'));

        $updates = [
            'product_name'     => $request->product_name,
            'product_type'     => $request->product_type,
            'gallon_size'      => $request->gallon_size,
            'product_image'    => $productImage,
            'customer_name'    => $request->customer_name,
            'customer_phone'   => $request->customer_phone,
            'delivery_date'    => $request->delivery_date,
            'delivery_time'    => $request->delivery_time,
            'delivery_address' => $request->delivery_address,
            'amount'           => $request->amount,
            'qty'              => (int) $request->input('qty', (int) ($order['qty'] ?? 1)),
            'payment_method'   => $request->payment_method,
        ];
        if ($request->has('status')) {
            $updates['status'] = $this->toDatabaseOrderStatus($request->input('status'));
        }
        // Preserve status_driver when order has a driver (do not overwrite with null)
        if (!empty($order['driver_id'])) {
            $updates['status_driver'] = $order['status_driver'] ?? 'accepted';
        }
        $oldStatus = (string) ($order['status'] ?? '');
        $this->firestore->update('orders', $id, $updates);
        FirestoreCacheKeys::invalidateOrders();
        $updatedOrder = $this->firestore->get('orders', $id) ?? array_merge($order, $updates, ['id' => $id]);
        if (strtolower($oldStatus) !== strtolower((string) ($updates['status'] ?? $oldStatus))) {
            $this->notifyCustomerOrderStatus($updatedOrder);
        }

        if (strtolower($oldStatus) !== strtolower((string) ($updates['status'] ?? $oldStatus)) && $this->normalizeOrderStatus((string) ($updatedOrder['status'] ?? '')) === 'completed') {
            $this->createAdminNotificationForAll(
                'order_delivered',
                (string) ($updatedOrder['product_name'] ?? 'Order'),
                'Successfully delivered',
                null,
                'Order',
                (string) ($updatedOrder['id'] ?? $id),
                ['subtitle' => 'delivered', 'highlight' => 'Successfully']
            );
        }

        $this->firebase->touchOrdersUpdated();

        return back()->with('success', 'Order updated successfully.');
    }

    /**
     * Return only available drivers as JSON for assign modal.
     */
    public function availableDriversJson()
    {
        $drivers = collect($this->firestore->rememberAll('drivers', 30))
            ->filter(fn (array $d) => strtolower((string) ($d['status'] ?? '')) === self::DRIVER_STATUS_AVAILABLE)
            ->sortBy(fn (array $d) => strtolower((string) ($d['name'] ?? '')))
            ->values()
            ->map(function (array $d) {
                return [
                    'id' => (string) ($d['id'] ?? ''),
                    'name' => (string) ($d['name'] ?? ''),
                    'phone' => (string) ($d['phone'] ?? ''),
                    'image_url' => asset((string) ($d['image'] ?? 'img/default-user.png')),
                ];
            });

        return response()->json(['drivers' => $drivers]);
    }

    /**
     * Assign or reassign a driver to an order. Expects JSON: { "driver_id": "<Firestore driver doc id>" }.
     */
    public function assignDriver(Request $request, string $id)
    {
        $request->validate([
            'driver_id' => ['required'],
        ]);

        $order = $this->firestore->get('orders', $id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }
        $driver = $this->firestore->get('drivers', (string) $request->driver_id);
        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 422);
        }
        $status = $this->normalizeOrderStatus((string) ($order['status'] ?? ''));

        if ($status === 'completed') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'This order cannot be assigned a driver.'], 422);
            }
            return back()->with('error', 'This order cannot be assigned a driver.');
        }

        $this->firestore->update('orders', $id, [
            'driver_id' => (string) $request->driver_id,
            'status' => $this->toDatabaseOrderStatus('assigned'),
            'status_driver' => self::DRIVER_STATUS_PENDING,
        ]);
        FirestoreCacheKeys::invalidateOrders();
        $updatedOrder = $this->firestore->get('orders', $id) ?? array_merge($order, ['id' => $id, 'driver_id' => (string) $request->driver_id]);

        $this->driverNotifications->createAndNotify((string) $request->driver_id, [
            'driver_id' => (string) $request->driver_id,
            'type' => 'shipment_assigned',
            'title' => 'New order is available!',
            'message' => 'Admin just assigned you. Click to see full details.',
            'image_url' => (string) ($updatedOrder['product_image'] ?? 'img/default-product.png'),
            'related_type' => 'Order',
            'related_id' => $id,
            'data' => [
                'transaction_id' => (string) ($updatedOrder['transaction_id'] ?? ''),
                'status' => 'assigned',
            ],
            'read_at' => null,
        ]);

        $this->notifyCustomerOrderStatus($updatedOrder);

        $this->firebase->touchOrdersUpdated();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Driver assigned successfully.']);
        }
        return back()->with('success', 'Driver assigned successfully.');
    }
}
