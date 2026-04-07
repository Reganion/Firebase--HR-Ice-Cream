<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ApiFlavorController extends Controller
{
    private const TTL_BEST_POPULAR = 120;

    private const TTL_LIST = 90;

    public function __construct(private FirestoreService $firestore)
    {
    }

    /**
     * Best Sellers: top 5 flavors by completed order count (Firestore `orders` + `flavors`).
     * GET /api/v1/best-sellers
     */
    public function bestSellers(): JsonResponse
    {
        $payload = Cache::remember(FirestoreCacheKeys::API_BEST_SELLERS, self::TTL_BEST_POPULAR, function () {
            $orders = collect($this->firestore->rememberAll('orders', 60));
            $completed = $orders->filter(fn (array $o) => strtolower(trim((string) ($o['status'] ?? ''))) === 'completed');
            $counts = $completed
                ->groupBy(fn (array $o) => trim((string) ($o['product_name'] ?? '')))
                ->map(fn (Collection $group) => $group->count())
                ->sortDesc();

            $topNames = $counts->keys()->filter(fn ($n) => $n !== '')->take(5)->values();
            $allFlavors = collect($this->firestore->rememberAll('flavors', self::TTL_LIST));

            $bestSellers = $topNames->map(function (string $name) use ($allFlavors) {
                return $allFlavors->first(fn (array $f) => strcasecmp(trim((string) ($f['name'] ?? '')), $name) === 0);
            })->filter()->values()->all();

            return array_map(fn (array $f) => $this->ensureStringIds($f), $bestSellers);
        });

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * Popular: top 5 flavors by feedback count, else by completed orders (skip 1st), else latest flavors.
     * GET /api/v1/popular
     */
    public function popular(): JsonResponse
    {
        $payload = Cache::remember(FirestoreCacheKeys::API_POPULAR, self::TTL_BEST_POPULAR, function () {
            $allFlavors = collect($this->firestore->rememberAll('flavors', self::TTL_LIST));

            $feedbackRows = collect($this->firestore->rememberAll('feedback', self::TTL_LIST));
            $popularFlavors = collect();

            if ($feedbackRows->isNotEmpty()) {
                $byFlavor = $feedbackRows
                    ->filter(fn (array $f) => ! empty($f['flavor_id']))
                    ->groupBy(fn (array $f) => (string) $f['flavor_id'])
                    ->map(fn (Collection $group) => $group->count())
                    ->sortDesc();

                $popularFlavors = $byFlavor->keys()->take(5)->map(function (string $id) {
                    return $this->firestore->get('flavors', $id);
                })->filter()->values();
            }

            if ($popularFlavors->isEmpty()) {
                $orders = collect($this->firestore->rememberAll('orders', 60));
                $completed = $orders->filter(fn (array $o) => strtolower(trim((string) ($o['status'] ?? ''))) === 'completed');
                $counts = $completed
                    ->groupBy(fn (array $o) => trim((string) ($o['product_name'] ?? '')))
                    ->map(fn (Collection $group) => $group->count())
                    ->sortDesc();

                $names = $counts->keys()->filter(fn ($n) => $n !== '')->values();
                $slice = $names->slice(1, 5);
                $popularFlavors = $slice->map(function (string $name) use ($allFlavors) {
                    return $allFlavors->first(fn (array $f) => strcasecmp(trim((string) ($f['name'] ?? '')), $name) === 0);
                })->filter()->values();
            }

            if ($popularFlavors->isEmpty()) {
                $popularFlavors = $allFlavors
                    ->sortByDesc(fn (array $f) => (string) ($f['created_at'] ?? ''))
                    ->take(5)
                    ->values();
            }

            return $popularFlavors->map(fn (array $f) => $this->ensureStringIds($f))->all();
        });

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * List all flavors (for Flutter).
     * GET /api/v1/flavors
     */
    public function index(): JsonResponse
    {
        $flavors = Cache::remember(FirestoreCacheKeys::API_FLAVORS_INDEX, self::TTL_LIST, function () {
            return collect($this->firestore->rememberAll('flavors', self::TTL_LIST))
                ->sortByDesc(fn (array $f) => (string) ($f['created_at'] ?? ''))
                ->values()
                ->map(fn (array $f) => $this->ensureStringIds($f))
                ->all();
        });

        return response()->json([
            'success' => true,
            'data' => $flavors,
        ]);
    }

    /**
     * Single flavor by id (Firestore document id).
     */
    public function show(string $id): JsonResponse
    {
        $flavor = $this->firestore->get('flavors', $id);
        if (! $flavor) {
            return response()->json(['success' => false, 'message' => 'Flavor not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->ensureStringIds($flavor)]);
    }

    /**
     * List gallon sizes (for Flutter).
     */
    public function gallons(): JsonResponse
    {
        $gallons = Cache::remember(FirestoreCacheKeys::API_GALLONS_INDEX, self::TTL_LIST, function () {
            return collect($this->firestore->rememberAll('gallons', self::TTL_LIST))
                ->sortBy(fn (array $g) => (float) ($g['size'] ?? 0))
                ->values()
                ->map(fn (array $g) => $this->ensureStringIds($g))
                ->all();
        });

        return response()->json(['success' => true, 'data' => $gallons]);
    }

    /**
     * Firestore document ids must stay strings in JSON (Flutter must not parse them as int).
     */
    private function ensureStringIds(array $row): array
    {
        if (isset($row['id'])) {
            $row['id'] = (string) $row['id'];
        }

        return $row;
    }
}
