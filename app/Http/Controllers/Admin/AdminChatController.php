<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseRealtimeService;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminChatController extends Controller
{
    public function __construct(
        protected FirebaseRealtimeService $firebase,
        protected FirestoreService $firestore
    ) {}

    private function customerFullName(array $customer): string
    {
        $full = trim((string) (($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? '')));
        return $full !== '' ? $full : 'Customer';
    }

    private function customerMessages(string $customerId): array
    {
        // Firestore equality is type-sensitive; some legacy rows may store customer_id as int.
        // Do not pass orderBy here — composite index (customer_id + created_at) is not enabled by default;
        // sorting is done in PHP after load.
        $rows = $this->firestore->where('chat_messages', 'customer_id', $customerId);
        if ($rows === [] && ctype_digit($customerId) && strlen($customerId) <= 18) {
            $asInt = (int) $customerId;
            if ((string) $asInt === $customerId) {
                $rows = $this->firestore->where('chat_messages', 'customer_id', $asInt);
            }
        }

        return collect($rows)
            ->sortBy(fn (array $m) => (string) ($m['created_at'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * List customers / chat threads for the sidebar (Firestore + last activity sort).
     * GET /admin/chat/customers?q=search
     *
     * Threads with recent messages are listed first (not limited to the first N customers alphabetically).
     */
    public function customers(Request $request): JsonResponse
    {
        $search = strtolower(trim((string) $request->get('q', '')));

        $messagesByCustomer = collect($this->firestore->rememberAll('chat_messages', 15))
            ->groupBy(function (array $m) {
                $raw = $m['customer_id'] ?? null;
                if (is_int($raw) || is_float($raw)) {
                    return (string) (int) $raw;
                }

                return trim((string) ($raw ?? ''));
            });

        $customersById = collect($this->firestore->rememberAll('customers', 60))
            ->keyBy(fn (array $c) => (string) ($c['id'] ?? ''));

        $rows = [];

        foreach ($customersById as $cid => $c) {
            $msgs = collect($messagesByCustomer->get($cid, []));
            $rows[] = $this->threadRowForCustomer($c, $msgs);
        }

        // Messages whose customer_id no longer exists in `customers` (still show the thread).
        foreach ($messagesByCustomer as $cid => $msgs) {
            if ($cid === '' || $customersById->has($cid)) {
                continue;
            }
            $collection = collect($msgs);
            if ($collection->isEmpty()) {
                continue;
            }
            $last = $collection->sortByDesc(fn (array $m) => (string) ($m['created_at'] ?? ''))->first();
            $rows[] = [
                'id' => $cid,
                'full_name' => 'Customer (#'.$cid.')',
                'firstname' => '',
                'lastname' => '',
                'email' => '',
                'contact_no' => '',
                'image_url' => asset('img/default-user.png'),
                'last_message_preview' => $last ? $this->preview($last) : null,
                'last_message_at' => $last['created_at'] ?? null,
                'unread_count' => $collection
                    ->filter(fn (array $m) => strtolower((string) ($m['sender_type'] ?? '')) === 'customer' && empty($m['read_at']))
                    ->count(),
                'orphan_thread' => true,
            ];
        }

        $list = collect($rows)
            ->sort(function (array $a, array $b) {
                $aAt = $a['last_message_at'] ?? null;
                $bAt = $b['last_message_at'] ?? null;
                $aHas = $aAt !== null && $aAt !== '';
                $bHas = $bAt !== null && $bAt !== '';
                if ($aHas !== $bHas) {
                    return $aHas ? -1 : 1;
                }
                if ($aHas && $bHas) {
                    return strcmp((string) $bAt, (string) $aAt);
                }

                return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
            })
            ->values();

        if ($search !== '') {
            $list = $list->filter(function (array $row) use ($search) {
                $haystack = strtolower(
                    (string) ($row['full_name'] ?? '').
                    ' '.(string) ($row['email'] ?? '').
                    ' '.(string) ($row['contact_no'] ?? '').
                    ' '.(string) ($row['id'] ?? '').
                    ' '.(string) ($row['last_message_preview'] ?? '')
                );

                return str_contains($haystack, $search);
            })->values();
        }

        $data = $list->take(150)->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * @param  Collection<int, array>  $messages
     */
    private function threadRowForCustomer(array $customer, Collection $messages): array
    {
        $cid = (string) ($customer['id'] ?? '');
        $sorted = $messages->sortByDesc(fn (array $m) => (string) ($m['created_at'] ?? ''))->values();
        $lastMessage = $sorted->first();
        $unreadCount = $messages
            ->filter(fn (array $m) => strtolower((string) ($m['sender_type'] ?? '')) === 'customer' && empty($m['read_at']))
            ->count();

        return [
            'id' => $cid,
            'full_name' => $this->customerFullName($customer),
            'firstname' => (string) ($customer['firstname'] ?? ''),
            'lastname' => (string) ($customer['lastname'] ?? ''),
            'email' => (string) ($customer['email'] ?? ''),
            'contact_no' => (string) ($customer['contact_no'] ?? ''),
            'image_url' => asset((string) ($customer['image'] ?? 'img/default-user.png')),
            'last_message_preview' => $lastMessage ? $this->preview($lastMessage) : null,
            'last_message_at' => $lastMessage['created_at'] ?? null,
            'unread_count' => $unreadCount,
            'orphan_thread' => false,
        ];
    }

    /**
     * Unread summary for chat head when panel is minimized.
     * GET /admin/chat/unread-summary
     */
    public function unreadSummary(): JsonResponse
    {
        $messages = collect($this->firestore->rememberAll('chat_messages', 15));
        $customersById = collect($this->firestore->rememberAll('customers', 60))
            ->keyBy(fn (array $c) => (string) ($c['id'] ?? ''));

        $unreadMessages = $messages
            ->filter(fn (array $m) => strtolower((string) ($m['sender_type'] ?? '')) === 'customer' && empty($m['read_at']))
            ->sortByDesc(fn (array $m) => (string) ($m['created_at'] ?? ''))
            ->values();

        $lastUnread = $unreadMessages->first();
        $totalUnread = $unreadMessages->count();

        $lastFrom = null;
        if ($lastUnread && ! empty($lastUnread['customer_id'])) {
            $c = $customersById->get((string) $lastUnread['customer_id']);
            if ($c) {
                $lastFrom = [
                    'customer_id' => (string) ($c['id'] ?? ''),
                    'full_name' => $this->customerFullName($c),
                    'image_url' => asset((string) ($c['image'] ?? 'img/default-user.png')),
                    'preview' => $this->preview($lastUnread),
                ];
            }
        }

        $senders = $unreadMessages
            ->groupBy(fn (array $m) => (string) ($m['customer_id'] ?? ''))
            ->map(function ($rows, $customerId) use ($customersById) {
                $latestUnread = collect($rows)->sortByDesc(fn (array $m) => (string) ($m['created_at'] ?? ''))->first();
                $customer = $customersById->get((string) $customerId);
                if (! $latestUnread || ! $customer) {
                    return null;
                }

                return [
                    'customer_id' => (string) $customerId,
                    'full_name' => $this->customerFullName($customer),
                    'image_url' => asset((string) ($customer['image'] ?? 'img/default-user.png')),
                    'preview' => $this->preview($latestUnread),
                    'unread_count' => count($rows),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'unread_count' => $totalUnread,
            'last_from' => $lastFrom,
            'senders' => $senders,
        ]);
    }

    /**
     * Get customer profile and chat messages.
     * GET /admin/chat/customers/{id}
     */
    public function show(int|string $id): JsonResponse
    {
        $idStr = (string) $id;

        try {
            $customer = $this->firestore->get('customers', $idStr);

            $rawMessages = collect($this->customerMessages($idStr))
                ->sortBy(fn (array $m) => (string) ($m['created_at'] ?? ''))
                ->values();

            // Thread exists in chat but customer doc was removed — still show conversation.
            if (! $customer) {
                if ($rawMessages->isEmpty()) {
                    return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
                }
                $customer = [
                    'id' => $idStr,
                    'firstname' => '',
                    'lastname' => '',
                    'email' => '',
                    'contact_no' => '',
                    'image' => 'img/default-user.png',
                ];
            }

            $readAt = now()->toIso8601String();
            $toMarkIds = [];
            foreach ($rawMessages as $row) {
                if (strtolower((string) ($row['sender_type'] ?? '')) === 'customer' && empty($row['read_at'])) {
                    $msgId = (string) ($row['id'] ?? '');
                    if ($msgId !== '') {
                        $toMarkIds[] = $msgId;
                    }
                }
            }
            if ($toMarkIds !== []) {
                $toMarkIds = array_values(array_unique($toMarkIds));
                try {
                    $merge = [];
                    foreach ($toMarkIds as $msgId) {
                        $merge[$msgId] = ['read_at' => $readAt];
                    }
                    $this->firestore->batchMergeDocuments('chat_messages', $merge);
                    $this->firebase->bulkUpdateChatMessagesReadAt($idStr, $toMarkIds, $readAt);
                    FirestoreCacheKeys::invalidateChat();
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $markSet = array_flip($toMarkIds);
            $messages = $rawMessages->map(function (array $m) use ($readAt, $markSet) {
                $id = (string) ($m['id'] ?? '');
                if ($id !== '' && isset($markSet[$id])) {
                    $m['read_at'] = $readAt;
                }

                return $this->formatMessage($m);
            });

            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => (string) ($customer['id'] ?? $idStr),
                    'full_name' => $this->customerFullName($customer),
                    'firstname' => (string) ($customer['firstname'] ?? ''),
                    'lastname' => (string) ($customer['lastname'] ?? ''),
                    'email' => (string) ($customer['email'] ?? ''),
                    'contact_no' => (string) ($customer['contact_no'] ?? ''),
                    'image_url' => asset((string) ($customer['image'] ?? 'img/default-user.png')),
                ],
                'messages' => $messages->values()->all(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not load conversation.',
            ], 500);
        }
    }

    /**
     * Get new messages for a customer after a given message id (for real-time polling).
     * GET /admin/chat/customers/{id}/messages?after_id=123
     */
    public function messagesSince(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Polling endpoint is disabled. Use Firebase realtime listeners.',
            'messages' => [],
        ], 410);
    }

    /**
     * Send a message to a customer (admin → customer).
     * POST /admin/chat/customers/{id}/messages
     * body: optional text, image: optional file
     */
    public function sendMessage(Request $request, int|string $id): JsonResponse
    {
        $idStr = (string) $id;
        $customer = $this->firestore->get('customers', $idStr);
        if (! $customer && count($this->customerMessages($idStr)) === 0) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $body = $request->input('body');
        $imagePath = null;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid() && str_starts_with($file->getMimeType(), 'image/')) {
                $dir = public_path('img/chat');
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $name = 'chat_' . (string) $id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                if ($file->move($dir, $name)) {
                    $imagePath = 'img/chat/' . $name;
                }
            }
        }

        if (empty(trim($body ?? '')) && !$imagePath) {
            return response()->json([
                'success' => false,
                'message' => 'Provide a message (body) or an image.',
            ], 422);
        }

        $messageId = (string) now()->format('YmdHis') . (string) random_int(1000, 9999);
        $payload = [
            'customer_id' => (string) $id,
            'sender_type' => 'admin',
            'body' => trim($body ?? '') ?: null,
            'image_path' => $imagePath,
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
        $this->firestore->set('chat_messages', $messageId, $payload);

        $message = $this->firestore->get('chat_messages', $messageId) ?? array_merge($payload, ['id' => $messageId]);
        $formatted = $this->formatMessage($message);
        $this->firebase->syncChatMessage((string) $id, $messageId, $formatted);
        $this->firebase->touchAdminChatUpdated();
        FirestoreCacheKeys::invalidateChat();

        return response()->json([
            'success' => true,
            'message' => $formatted,
        ]);
    }

    private function preview(array $m): string
    {
        if (!empty($m['image_path'])) {
            return '[Image]';
        }
        $text = (string) ($m['body'] ?? '');
        return strlen($text) > 50 ? substr($text, 0, 50) . '…' : $text;
    }

    private function formatMessage(array $m): array
    {
        $imageUrl = null;
        $imagePath = (string) ($m['image_path'] ?? '');
        if ($imagePath !== '') {
            $imageUrl = str_starts_with($imagePath, 'img/') ? asset($imagePath) : asset('storage/' . $imagePath);
        }
        return [
            'id' => (string) ($m['id'] ?? ''),
            'sender_type' => (string) ($m['sender_type'] ?? ''),
            'body' => $m['body'] ?? null,
            'image_url' => $imageUrl,
            'created_at' => $this->safeIso8601($m['created_at'] ?? null),
            'read_at' => $this->safeIso8601Nullable($m['read_at'] ?? null),
        ];
    }

    private function safeIso8601(mixed $value): string
    {
        if ($value === null || $value === '') {
            return now()->toIso8601String();
        }
        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return now()->toIso8601String();
        }
    }

    private function safeIso8601Nullable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
