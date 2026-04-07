<?php

namespace App\Http\Middleware;

use App\Services\FirestoreService;
use App\Support\FirestoreCustomerUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiCustomer
{
    public const CACHE_PREFIX = 'api_customer_session:';
    public const TTL_MINUTES = 60 * 24 * 7; // 7 days

    /**
     * Handle an incoming request. Expects Bearer token or X-Session-Token header.
     * Token is stored in cache as api_customer_session:{token} => customer_id (Firestore doc id).
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

        $customerId = Cache::get(self::CACHE_PREFIX . $token);

        if (! $customerId) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please log in again.',
            ], 401);
        }

        $row = app(FirestoreService::class)->get('customers', (string) $customerId);
        $customer = FirestoreCustomerUser::fromArray($row);

        if (! $customer) {
            Cache::forget(self::CACHE_PREFIX . $token);
            return response()->json([
                'success' => false,
                'message' => 'Session invalid. Please log in again.',
            ], 401);
        }

        $request->setUserResolver(fn () => $customer);

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
