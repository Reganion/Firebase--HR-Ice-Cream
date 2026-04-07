<?php

namespace App\Services;

use App\Support\FirestoreCacheKeys;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Contract\Firestore as FirestoreContract;

class FirestoreService
{
    protected ?FirestoreClient $db = null;

    public function __construct(FirestoreContract $firestore)
    {
        $this->db = $firestore->database();
    }

    /**
     * Add a document with auto-generated ID. Returns the new document ID.
     * Adds created_at automatically.
     */
    public function add(string $collection, array $data): string
    {
        $data['created_at'] = date('c');
        $data['updated_at'] = date('c');
        $ref = $this->db->collection($collection)->add($this->toFirestore($data));
        return $ref->id();
    }

    /**
     * Get a document by ID. Returns array with 'id' and all fields, or null.
     */
    public function get(string $collection, string $id): ?array
    {
        $snap = $this->db->collection($collection)->document($id)->snapshot();
        if (!$snap->exists()) {
            return null;
        }
        return $this->snapshotToArray($snap);
    }

    /**
     * Set (create or overwrite) a document.
     */
    public function set(string $collection, string $id, array $data): void
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('c');
        }
        $data['updated_at'] = date('c');
        $this->db->collection($collection)->document($id)->set($this->toFirestore($data));
    }

    /**
     * Update a document (merge). Adds updated_at.
     */
    public function update(string $collection, string $id, array $data): void
    {
        $data['updated_at'] = date('c');
        // Firestore PHP update() expects field-path patch format.
        // Use merge set() so associative arrays from controllers work safely.
        $this->db->collection($collection)->document($id)->set($this->toFirestore($data), ['merge' => true]);
    }

    /**
     * Merge fields into a single doc so the admin panel can use Firestore listeners (instead of RTDB-only signals).
     * Path: admin_signals/broadcast
     *
     * @param  array<string, mixed>  $data
     */
    public function mergeAdminBroadcast(array $data): void
    {
        if ($data === []) {
            return;
        }
        try {
            $ref = $this->db->collection('admin_signals')->document('broadcast');
            $payload = $this->toFirestore($data);
            $payload['updated_at'] = date('c');
            $ref->set($payload, ['merge' => true]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Merge-update many documents (max 500 writes per Firestore batch).
     *
     * @param  array<string, array<string, mixed>>  $docIdToPartialData  Document id => partial fields
     */
    public function batchMergeDocuments(string $collection, array $docIdToPartialData): void
    {
        if ($docIdToPartialData === []) {
            return;
        }

        $chunks = array_chunk($docIdToPartialData, 500, true);
        foreach ($chunks as $chunk) {
            $batch = $this->db->batch();
            foreach ($chunk as $docId => $data) {
                if (! is_array($data)) {
                    continue;
                }
                $data['updated_at'] = date('c');
                $ref = $this->db->collection($collection)->document((string) $docId);
                $batch->set($ref, $this->toFirestore($data), ['merge' => true]);
            }
            $batch->commit();
        }
    }

    /**
     * Delete a document.
     */
    public function delete(string $collection, string $id): void
    {
        $this->db->collection($collection)->document($id)->delete();
    }

    /**
     * Delete all documents in a collection where field equals value (equality filter only).
     *
     * @return int Number of documents deleted
     */
    public function deleteWhere(string $collection, string $field, mixed $value): int
    {
        $docs = $this->where($collection, $field, $value);
        $n = 0;
        foreach ($docs as $doc) {
            if (! empty($doc['id'])) {
                $this->delete($collection, (string) $doc['id']);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Get all documents in a collection, optionally ordered.
     *
     * @param  string  $orderBy  Field name (e.g. 'created_at')
     * @param  string  $direction  'asc' or 'desc'
     * @return array<int, array>  List of doc arrays (each has 'id' key)
     */
    public function all(string $collection, ?string $orderBy = null, string $direction = 'desc'): array
    {
        $query = $this->db->collection($collection);
        if ($orderBy !== null) {
            $query = $query->orderBy($orderBy, $direction);
        }
        $snaps = $query->documents();
        $out = [];
        foreach ($snaps as $snap) {
            if ($snap->exists()) {
                $out[] = $this->snapshotToArray($snap);
            }
        }
        return $out;
    }

    /**
     * Cached full-collection read (same as all() with no server-side order).
     * Use for read-heavy pages; invalidate via FirestoreCacheKeys when data changes.
     */
    public function rememberAll(string $collection, int $ttlSeconds = 90): array
    {
        $key = FirestoreCacheKeys::listKey($collection);

        return Cache::remember($key, $ttlSeconds, fn () => $this->all($collection));
    }

    /**
     * Find first document where field equals value.
     */
    public function firstWhere(string $collection, string $field, mixed $value): ?array
    {
        $query = $this->db->collection($collection)->where($field, '=', $value)->limit(1);
        $snaps = $query->documents();
        foreach ($snaps as $snap) {
            if ($snap->exists()) {
                return $this->snapshotToArray($snap);
            }
        }
        return null;
    }

    /**
     * Documents where field value is in the given list (Firestore "in" query; max 30 values per chunk).
     *
     * @param  array<int|string>  $values
     * @return array<int, array<string, mixed>>
     */
    public function whereIn(string $collection, string $field, array $values): array
    {
        $values = array_values(array_unique(array_filter($values, static fn ($v) => $v !== null && $v !== '')));
        if ($values === []) {
            return [];
        }

        $byId = [];
        foreach (array_chunk($values, 30) as $chunk) {
            $query = $this->db->collection($collection)->where($field, 'in', $chunk);
            foreach ($query->documents() as $snap) {
                if ($snap->exists()) {
                    $row = $this->snapshotToArray($snap);
                    if (! empty($row['id'])) {
                        $byId[(string) $row['id']] = $row;
                    }
                }
            }
        }

        return array_values($byId);
    }

    /**
     * Find documents where field equals value, optionally ordered.
     *
     * @return array<int, array>
     */
    public function where(string $collection, string $field, mixed $value, ?string $orderBy = null, string $direction = 'desc'): array
    {
        $query = $this->db->collection($collection)->where($field, '=', $value);
        if ($orderBy !== null) {
            $query = $query->orderBy($orderBy, $direction);
        }
        $snaps = $query->documents();
        $out = [];
        foreach ($snaps as $snap) {
            if ($snap->exists()) {
                $out[] = $this->snapshotToArray($snap);
            }
        }
        return $out;
    }

    /**
     * Equality on user_id is type-sensitive in Firestore; API writers use string ids.
     * Merge string + int queries and de-duplicate by document id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function whereAdminNotificationsForUser(string|int $adminId): array
    {
        $stringId = (string) $adminId;
        $byString = $this->where('admin_notifications', 'user_id', $stringId);
        if (! is_numeric($stringId)) {
            return $byString;
        }
        $intId = (int) $stringId;
        $byInt = $this->where('admin_notifications', 'user_id', $intId);

        return collect($byString)->merge($byInt)->keyBy('id')->values()->all();
    }

    /**
     * Cached variant of whereAdminNotificationsForUser (Firestore may run two queries per call for legacy int user_id).
     * Invalidate via FirestoreCacheKeys::forgetAdminNotificationsFeed when read state changes.
     */
    public function rememberWhereAdminNotificationsForUser(string|int $adminId, int $ttlSeconds = 30): array
    {
        $key = FirestoreCacheKeys::adminNotificationsFeedKey((string) $adminId);

        return Cache::remember($key, $ttlSeconds, fn () => $this->whereAdminNotificationsForUser($adminId));
    }

    /**
     * Resolve admin document for an authenticated session (cached). Skips caching when not found.
     */
    public function resolveAdminForSession(string $adminId, string $adminEmail): ?array
    {
        $adminEmail = strtolower(trim($adminEmail));
        $cacheKey = $adminId !== ''
            ? FirestoreCacheKeys::adminDocKey($adminId)
            : ($adminEmail !== '' ? FirestoreCacheKeys::adminDocByEmailKey($adminEmail) : null);

        if ($cacheKey === null) {
            return null;
        }

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return is_array($cached) ? $cached : null;
        }

        $admin = $adminId !== '' ? $this->get('admins', $adminId) : null;
        if (! $admin && $adminEmail !== '') {
            $admin = $this->firstWhere('admins', 'email', $adminEmail);
        }
        if ($admin) {
            Cache::put($cacheKey, $admin, 120);
        }

        return $admin;
    }

    /**
     * Equality on customer_id is type-sensitive; merge string + int queries and de-duplicate by doc id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function whereCustomerNotificationsForCustomer(string|int $customerId): array
    {
        $stringId = (string) $customerId;
        $byString = $this->where('customer_notifications', 'customer_id', $stringId);
        if (! is_numeric($stringId)) {
            return $byString;
        }
        $intId = (int) $stringId;
        $byInt = $this->where('customer_notifications', 'customer_id', $intId);

        return collect($byString)->merge($byInt)->keyBy('id')->values()->all();
    }

    /**
     * Driver notifications (Firestore `driver_notifications`), merged for string/int driver_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function whereDriverNotificationsForDriver(string|int $driverId): array
    {
        $stringId = (string) $driverId;
        $byString = $this->where('driver_notifications', 'driver_id', $stringId);
        if (! is_numeric($stringId)) {
            return $byString;
        }
        $intId = (int) $stringId;
        $byInt = $this->where('driver_notifications', 'driver_id', $intId);

        return collect($byString)->merge($byInt)->keyBy('id')->values()->all();
    }

    /**
     * Convert DocumentSnapshot to array with 'id'.
     */
    protected function snapshotToArray($snapshot): array
    {
        $data = $snapshot->data();
        if (!is_array($data)) {
            $data = [];
        }
        foreach ($data as $k => $v) {
            if ($v instanceof \Google\Cloud\Firestore\Timestamp) {
                $data[$k] = $v->format('c');
            }
        }
        $data['id'] = $snapshot->id();
        return $data;
    }

    /**
     * Prepare array for Firestore (e.g. convert DateTime to string).
     */
    protected function toFirestore(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if ($v instanceof \DateTimeInterface) {
                $out[$k] = $v->format('c');
            } elseif (is_array($v)) {
                $out[$k] = $this->toFirestore($v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
