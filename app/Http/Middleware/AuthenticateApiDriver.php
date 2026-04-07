<?php

namespace App\Http\Middleware;

use App\Services\FirestoreService;
use App\Support\ApiDriverSession;
use App\Support\DriverStatuses;
use App\Support\FirestoreCacheKeys;
use App\Support\FirestoreDriverUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiDriver
{
    public function __construct(
        protected FirestoreService $firestore,
    ) {}

    /**
     * Handle an incoming request. Expects Bearer token or X-Session-Token header.
     * Token is stored in cache as api_driver_session:{token} => driver_id (Firestore document id).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated. Send Authorization: Bearer {token} or X-Session-Token header.',
            ], 401);
        }

        $driverId = Cache::get(ApiDriverSession::CACHE_PREFIX.$token);
        if (! $driverId) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please log in again.',
            ], 401);
        }

        $row = $this->firestore->get('drivers', (string) $driverId);
        if (! $row) {
            Cache::forget(ApiDriverSession::CACHE_PREFIX.$token);

            return response()->json([
                'success' => false,
                'message' => 'Session invalid. Please log in again.',
            ], 401);
        }

        $status = (string) ($row['status'] ?? '');
        if ($status === DriverStatuses::DEACTIVATE || $status === DriverStatuses::ARCHIVE) {
            Cache::forget(ApiDriverSession::CACHE_PREFIX.$token);

            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive or archived. Please contact admin.',
            ], 403);
        }

        // Keep session entry and online marker alive while the driver is actively authenticated.
        Cache::forever(ApiDriverSession::CACHE_PREFIX.$token, $row['id']);
        Cache::put(
            ApiDriverSession::ONLINE_KEY_PREFIX.(string) $row['id'],
            true,
            now()->addMinutes(ApiDriverSession::ONLINE_PRESENCE_TTL_MINUTES)
        );

        if ($status !== DriverStatuses::ON_ROUTE && $status !== DriverStatuses::AVAILABLE) {
            $this->firestore->update('drivers', (string) $row['id'], ['status' => DriverStatuses::AVAILABLE]);
            FirestoreCacheKeys::invalidateDrivers();
            $row = $this->firestore->get('drivers', (string) $row['id']) ?? $row;
            $row['status'] = DriverStatuses::AVAILABLE;
        }

        $user = FirestoreDriverUser::fromArray($row);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return $request->header('X-Session-Token') ?: null;
    }
}
