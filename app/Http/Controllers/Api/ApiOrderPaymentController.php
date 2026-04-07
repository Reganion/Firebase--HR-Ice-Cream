<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AdminNotification;
use App\Support\CustomerNotification;
use App\Services\FirebaseRealtimeService;
use App\Services\FirestoreService;
use App\Services\PayMongoService;
use App\Support\FirestoreCacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiOrderPaymentController extends Controller
{
    protected $paymongo;

    public function __construct(
        PayMongoService $paymongo,
        protected FirebaseRealtimeService $firebase,
        protected FirestoreService $firestore,
    ) {
        $this->paymongo = $paymongo;
    }

    public function qrindex()
    {
        $paymentIntent = $this->paymongo->createPaymentIntent(100, 'Test QRPH Payment');

        $paymentMethod = $this->paymongo->createQrphPaymentMethod(
            'Juan Dela Cruz',
            'juan@example.com',
            '09171234567'
        );

        $attachResponse = $this->paymongo->attachPaymentMethodToIntent(
            $paymentIntent['id'],
            $paymentMethod['id']
        );

        $qrData = $attachResponse['data']['attributes']['next_action']['code']['image_url'] ?? null;

        return view('payment.qrph.qrph', ['qrData' => $qrData]);
    }

    /**
     * Create a PaymentIntent for customer downpayment (QRPH) and a pending invoice (Firestore `invoices`).
     * Order is created only after payment succeeds (Firestore `orders`).
     */
    public function createDownpayment(Request $request): JsonResponse
    {
        $request->validate([
            'product_name' => 'required|string|max:255',
            'product_type' => 'required|string|max:255',
            'gallon_size' => 'required|string|max:50',
            'delivery_date' => 'required|date',
            'delivery_time' => 'required|string',
            'delivery_address' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'downpayment_percent' => 'required|numeric|min:0.25|max:1.0',
            'quantity' => 'nullable|integer|min:1',
            'qty' => 'nullable|integer|min:1',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $user = $request->user();
        if (! $this->isApiCustomerUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user.',
            ], 401);
        }

        $idempotencyKey = $request->input('idempotency_key');
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->firestore->firstWhere('invoices', 'idempotency_key', $idempotencyKey);
            if ($existing !== null) {
                $ownerId = $existing['customer_id'] ?? null;
                if ($ownerId !== null && (string) $ownerId === (string) $user->id) {
                    $order = $this->resolveOrderForInvoice($existing);
                    $balance = $order !== null
                        ? (float) ($order['balance'] ?? 0)
                        : (float) ($existing['order_payload']['balance'] ?? 0);

                    return response()->json([
                        'success' => true,
                        'message' => 'Downpayment already initialized.',
                        'data' => [
                            'order_id' => $order['id'] ?? null,
                            'invoice_id' => $existing['id'],
                            'payment_intent_id' => $existing['payment_intent_id'] ?? null,
                            'qr_image_url' => $existing['qr_image_url'] ?? null,
                            'downpayment_amount' => (float) ($existing['amount'] ?? 0),
                            'balance' => $balance,
                        ],
                    ]);
                }
            }
        }

        $percent = (float) $request->downpayment_percent;
        $fullAmount = (float) $request->amount;
        $downpaymentAmount = round($fullAmount * $percent, 2);
        $downpaymentCentavos = (int) round($downpaymentAmount * 100);
        $balanceAmount = max(0, $fullAmount - $downpaymentAmount);

        if ($downpaymentCentavos <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Downpayment amount must be greater than zero.',
            ], 422);
        }

        $quantity = max(
            1,
            (int) $request->input('quantity', $request->input('qty', 1))
        );

        $customerFullName = trim((string) ($user->firstname ?? '').' '.(string) ($user->lastname ?? ''));
        $customerName = $customerFullName !== '' ? $customerFullName : 'Guest';
        $customerPhone = (string) ($user->contact_no ?? '');
        $customerImage = $user->image ?? 'img/default-user.png';

        $flavor = $this->firestore->firstWhere('flavors', 'name', (string) $request->product_name);
        $productImage = $flavor['image'] ?? 'img/default-product.png';

        $description = sprintf(
            'Downpayment %.0f%% - %s',
            $percent * 100,
            $request->product_name
        );

        $paymentIntent = $this->paymongo->createPaymentIntent($downpaymentCentavos, $description, $idempotencyKey);
        if (! $paymentIntent || ! isset($paymentIntent['id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Could not initialize payment. Please try again.',
            ], 502);
        }

        $paymentMethod = $this->paymongo->createQrphPaymentMethod(
            $customerName,
            (string) ($user->email ?? ''),
            $customerPhone
        );

        if (! $paymentMethod || ! isset($paymentMethod['id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Could not initialize payment method. Please try again.',
            ], 502);
        }

        $attachResponse = $this->paymongo->attachPaymentMethodToIntent(
            $paymentIntent['id'],
            $paymentMethod['id']
        );

        $qrImageUrl = $attachResponse['data']['attributes']['next_action']['code']['image_url'] ?? null;
        if (! $qrImageUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Could not generate QR code. Please try again.',
            ], 502);
        }

        $orderPayload = [
            'customer_id' => (string) $user->id,
            'transaction_id' => strtoupper(Str::random(10)),
            'product_name' => $request->product_name,
            'product_type' => $request->product_type,
            'gallon_size' => $request->gallon_size,
            'product_image' => $productImage,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_image' => $customerImage,
            'delivery_date' => $request->delivery_date,
            'delivery_time' => $request->delivery_time,
            'delivery_address' => $request->delivery_address,
            'amount' => $fullAmount,
            'downpayment' => $downpaymentAmount,
            'balance' => $balanceAmount,
            'qty' => $quantity,
            'payment_method' => $request->payment_method,
            'status' => 'pending',
        ];

        $invoiceId = $this->firestore->add('invoices', [
            'customer_id' => (string) $user->id,
            'order_id' => null,
            'order_payload' => $orderPayload,
            'idempotency_key' => $idempotencyKey ?: null,
            'payment_intent_id' => $paymentIntent['id'],
            'source_id' => $paymentMethod['id'],
            'amount' => $downpaymentAmount,
            'currency' => 'PHP',
            'status' => 'pending',
            'payment_method' => 'qrph',
            'qr_image_url' => $qrImageUrl,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Downpayment initialized. Scan the QR code to pay.',
            'data' => [
                'order_id' => null,
                'invoice_id' => $invoiceId,
                'payment_intent_id' => $paymentIntent['id'],
                'qr_image_url' => $qrImageUrl,
                'downpayment_amount' => $downpaymentAmount,
                'balance' => $balanceAmount,
            ],
        ]);
    }

    /**
     * GET /api/v1/orders/downpayment/status/{invoiceId}
     */
    public function checkDownpaymentStatus(Request $request, string $invoiceId): JsonResponse
    {
        $user = $request->user();
        if (! $this->isApiCustomerUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user.',
            ], 401);
        }

        $invoice = $this->firestore->get('invoices', $invoiceId);
        if ($invoice === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        $ownerId = $invoice['customer_id'] ?? null;
        if ($ownerId === null || (string) $ownerId !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        if (empty($invoice['payment_intent_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'No payment intent found for this invoice.',
            ], 422);
        }

        $status = $this->paymongo->getPaymentStatus($invoice['payment_intent_id']);
        $invoiceStatus = (string) ($invoice['status'] ?? '');

        if ($status === 'succeeded' && $invoiceStatus !== 'paid') {
            $order = $this->resolveOrderForInvoice($invoice);

            if ($order === null && is_array($invoice['order_payload'] ?? null) && ! empty($invoice['order_payload'])) {
                $payload = $invoice['order_payload'];
                $orderId = $this->firestore->add('orders', [
                    'customer_id' => (string) ($payload['customer_id'] ?? ''),
                    'transaction_id' => $payload['transaction_id'],
                    'product_name' => $payload['product_name'],
                    'product_type' => $payload['product_type'],
                    'gallon_size' => $payload['gallon_size'],
                    'product_image' => $payload['product_image'] ?? 'img/default-product.png',
                    'customer_name' => $payload['customer_name'],
                    'customer_phone' => $payload['customer_phone'],
                    'customer_image' => $payload['customer_image'] ?? 'img/default-user.png',
                    'delivery_date' => $payload['delivery_date'],
                    'delivery_time' => $payload['delivery_time'],
                    'delivery_address' => $payload['delivery_address'],
                    'amount' => $payload['amount'],
                    'downpayment' => $payload['downpayment'],
                    'balance' => $payload['balance'],
                    'qty' => $payload['qty'],
                    'payment_method' => $payload['payment_method'],
                    'status' => 'pending',
                ]);
                $order = $this->firestore->get('orders', $orderId) ?? ['id' => $orderId];

                $this->firestore->update('invoices', $invoiceId, [
                    'order_id' => $orderId,
                ]);
                $invoice['order_id'] = $orderId;

                $productImage = $order['product_image'] ?? 'img/default-product.png';
                $notifId = $this->firestore->add('customer_notifications', [
                    'customer_id' => (string) ($order['customer_id'] ?? ''),
                    'type' => CustomerNotification::TYPE_ORDER_PLACED,
                    'title' => (string) ($order['product_name'] ?? ''),
                    'message' => 'Your downpayment was received. Order confirmed.',
                    'image_url' => $productImage,
                    'related_type' => 'Order',
                    'related_id' => (string) ($order['id'] ?? $orderId),
                    'data' => ['transaction_id' => (string) ($order['transaction_id'] ?? '')],
                    'read_at' => null,
                ]);
                try {
                    $numericNotif = (int) preg_replace('/\D/', '', (string) $notifId) ?: crc32((string) $notifId);
                    $this->firebase->syncNotification(
                        $this->firebaseNumericCustomerId((string) ($order['customer_id'] ?? '')),
                        $numericNotif,
                        [
                            'id' => $notifId,
                            'type' => CustomerNotification::TYPE_ORDER_PLACED,
                            'title' => (string) ($order['product_name'] ?? ''),
                            'message' => 'Your downpayment was received. Order confirmed.',
                            'image_url' => $productImage,
                            'related_type' => 'Order',
                            'related_id' => (string) ($order['id'] ?? $orderId),
                            'data' => ['transaction_id' => (string) ($order['transaction_id'] ?? '')],
                            'read_at' => null,
                            'created_at' => now()->toIso8601String(),
                        ]
                    );
                } catch (\Throwable $e) {
                    report($e);
                }

                $this->createAdminNotificationsOrderNew(
                    (string) ($order['customer_name'] ?? ''),
                    $productImage,
                    (string) ($order['id'] ?? $orderId),
                    (string) ($order['transaction_id'] ?? ''),
                    (string) ($order['product_name'] ?? '')
                );
                $this->firebase->touchOrdersUpdated();
            }

            $this->firestore->update('invoices', $invoiceId, ['status' => 'paid']);
            $invoice['status'] = 'paid';

            $order = $this->resolveOrderForInvoice($invoice);
            if ($order !== null) {
                $currentReceived = (float) ($order['received_amount'] ?? 0.0);
                $newReceived = $currentReceived + (float) ($invoice['amount'] ?? 0);
                $orderAmount = (float) ($order['amount'] ?? 0);
                $this->firestore->update('orders', (string) $order['id'], [
                    'received_amount' => $newReceived,
                    'balance' => max(0, $orderAmount - $newReceived),
                ]);
                $this->firebase->touchOrdersUpdated();
                $order = $this->firestore->get('orders', (string) $order['id']) ?? $order;
            }
        } elseif (in_array($status, ['failed', 'cancelled'], true) && $invoiceStatus !== 'failed') {
            $this->firestore->update('invoices', $invoiceId, ['status' => 'failed']);
            $invoice['status'] = 'failed';

            $order = $this->resolveOrderForInvoice($invoice);
            if ($order !== null && $this->normalizeStatus((string) ($order['status'] ?? '')) === 'pending') {
                $this->firestore->update('orders', (string) $order['id'], [
                    'status' => 'cancelled',
                    'reason' => 'Downpayment failed or cancelled.',
                ]);
                $this->firebase->touchOrdersUpdated();
            }
        }

        $invoice = $this->firestore->get('invoices', $invoiceId) ?? $invoice;
        $order = $this->resolveOrderForInvoice($invoice);
        $orderStatus = $order['status'] ?? null;
        $orderBalance = $order !== null
            ? (float) ($order['balance'] ?? 0)
            : (float) ($invoice['order_payload']['balance'] ?? 0);
        $orderReceivedAmount = $order !== null ? (float) ($order['received_amount'] ?? 0) : 0.0;

        FirestoreCacheKeys::invalidateOrders();

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_status' => $invoice['status'] ?? null,
                'order_id' => $order['id'] ?? null,
                'order_status' => $orderStatus,
                'payment_status' => $status,
                'order_balance' => $orderBalance,
                'order_received_amount' => $orderReceivedAmount,
            ],
        ]);
    }

    /**
     * POST /api/v1/orders/downpayment/cancel/{invoiceId}
     */
    public function cancelDownpayment(Request $request, string $invoiceId): JsonResponse
    {
        $user = $request->user();
        if (! $this->isApiCustomerUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid user.',
            ], 401);
        }

        $invoice = $this->firestore->get('invoices', $invoiceId);
        if ($invoice === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        $ownerId = $invoice['customer_id'] ?? null;
        if ($ownerId === null || (string) $ownerId !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        if (($invoice['status'] ?? '') === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Downpayment is already paid and cannot be cancelled.',
            ], 422);
        }

        $this->firestore->update('invoices', $invoiceId, ['status' => 'failed']);
        $invoice['status'] = 'failed';

        $order = $this->resolveOrderForInvoice($invoice);
        if ($order !== null && $this->normalizeStatus((string) ($order['status'] ?? '')) === 'pending') {
            $this->firestore->update('orders', (string) $order['id'], [
                'status' => 'cancelled',
                'reason' => 'Customer closed payment screen before completing downpayment.',
            ]);
            $this->firebase->touchOrdersUpdated();
        }

        FirestoreCacheKeys::invalidateOrders();

        $orderAfter = $this->resolveOrderForInvoice(array_merge($invoice, ['status' => 'failed']));

        return response()->json([
            'success' => true,
            'message' => 'Downpayment has been cancelled.',
            'data' => [
                'invoice_status' => 'failed',
                'order_status' => $orderAfter['status'] ?? null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function resolveOrderForInvoice(array $invoice): ?array
    {
        $oid = $invoice['order_id'] ?? null;
        if ($oid === null || $oid === '') {
            return null;
        }

        return $this->firestore->get('orders', (string) $oid);
    }

    private function firebaseNumericCustomerId(string $customerId): int
    {
        $customerId = trim($customerId);
        if ($customerId !== '' && ctype_digit($customerId)) {
            return (int) $customerId;
        }

        return (int) (sprintf('%u', crc32($customerId)) % 2147483647);
    }

    private function createAdminNotificationsOrderNew(
        string $customerName,
        string $productImage,
        string $orderId,
        string $transactionId,
        string $productName
    ): void {
        $admins = $this->firestore->all('admins');
        foreach ($admins as $admin) {
            if (empty($admin['id'])) {
                continue;
            }
            $this->firestore->add('admin_notifications', [
                'user_id' => (string) $admin['id'],
                'type' => AdminNotification::TYPE_ORDER_NEW,
                'title' => $customerName,
                'message' => null,
                'image_url' => $productImage,
                'related_type' => 'Order',
                'related_id' => $orderId,
                'data' => [
                    'subtitle' => 'paid downpayment for Order #'.$transactionId,
                    'highlight' => $productName,
                ],
                'read_at' => null,
            ]);
        }
        try {
            $this->firebase->touchAdminNotificationsUpdated();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function normalizeStatus(string $status): string
    {
        return strtolower(trim($status));
    }

    /**
     * API routes use middleware `api.customer`, which sets a Firestore-backed user (stdClass),
     * Firestore customers (not Eloquent). Match {@see ApiOrderController::isCustomerUser()}.
     */
    private function isApiCustomerUser(mixed $user): bool
    {
        return is_object($user) && isset($user->id) && (string) $user->id !== '';
    }
}
