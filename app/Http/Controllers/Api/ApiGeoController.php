<?php

namespace App\Http\Controllers\Api;

use App\Events\RiderLocationUpdated;
use App\Http\Controllers\Controller;
use App\Services\DeliveryService;
use App\Services\FirestoreService;
use App\Support\FirestoreCacheKeys;
use App\Support\FirestoreDriverUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Map providers: Google Maps or free OSM (Nominatim + OSRM + raster tiles) for study / low-traffic use.
 */
class ApiGeoController extends Controller
{
    public function __construct(
        protected DeliveryService $deliveryService,
        protected FirestoreService $firestore,
    ) {}

    /**
     * Public config for Flutter: Google Maps key, or OSM tile template for flutter_map (no Google billing).
     */
    public function mapsConfig(): JsonResponse
    {
        $provider = (string) config('services.maps.provider', 'google');

        if ($provider === 'osm') {
            return response()->json([
                'provider' => 'osm',
                'osm' => [
                    'tile_url_template' => (string) config('services.osm.tile_url_template', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
                    'tile_attribution' => (string) config('services.osm.tile_attribution', '© OpenStreetMap contributors'),
                ],
            ]);
        }

        $key = (string) config('services.google_maps.client_key', '');

        return response()->json([
            'provider' => 'google',
            'google_maps' => [
                'api_key' => $key,
            ],
        ]);
    }

    public function geocodeOrderAddress(string $orderId): JsonResponse
    {
        $order = $this->firestore->get('orders', $orderId);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $dest = $this->destinationForOrder($order);
        if (($dest['lat'] ?? null) !== null && ($dest['lng'] ?? null) !== null) {
            return response()->json([
                'order_id' => $orderId,
                'lat' => $dest['lat'],
                'lng' => $dest['lng'],
                'source' => $this->destinationSource($order),
            ]);
        }

        return response()->json([
            'order_id' => $orderId,
            'lat' => null,
            'lng' => null,
            'source' => null,
        ]);
    }

    /**
     * Customer: push live GPS for an order (optional; both apps read via GET …/geo/orders/{id}/track).
     */
    public function customerUpdateLocation(Request $request): JsonResponse
    {
        $user = $request->user();
        $authId = is_object($user) && isset($user->id) ? trim((string) $user->id) : '';
        if ($authId === '') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $data = $request->validate([
            'order_id' => 'required|string',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $order = $this->firestore->get('orders', (string) $data['order_id']);
        if (! $order || ! $this->customerOwnsOrder($order['customer_id'] ?? null, $authId)) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $now = now()->toIso8601String();
        $this->firestore->update('orders', (string) $data['order_id'], [
            'customer_current_lat' => (float) $data['lat'],
            'customer_current_lng' => (float) $data['lng'],
            'customer_location_updated_at' => $now,
        ]);
        FirestoreCacheKeys::invalidateOrders();

        return response()->json([
            'success' => true,
            'updated_at' => $now,
        ]);
    }

    /**
     * Customer: live map data for an order (destination, assigned driver position, optional route polyline).
     */
    public function trackOrderForCustomer(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $authId = is_object($user) && isset($user->id) ? trim((string) $user->id) : '';
        if ($authId === '') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $order = $this->firestore->get('orders', $orderId);
        if (! $order || ! $this->customerOwnsOrder($order['customer_id'] ?? null, $authId)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json($this->buildTrackPayload($order));
    }

    /**
     * Driver: same tracker payload; only when this driver is assigned to the order.
     */
    public function trackOrderForDriver(Request $request, string $orderId): JsonResponse
    {
        if (! FirestoreDriverUser::isAuthenticatedUser($request->user())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $driverId = (string) $request->user()->id;
        $order = $this->firestore->get('orders', $orderId);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $assigned = trim((string) ($order['driver_id'] ?? ''));
        if ($assigned === '' || $assigned !== $driverId) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return response()->json($this->buildTrackPayload($order));
    }

    /**
     * Driver GPS ping (authenticated). Prefer this over legacy unauthenticated /geo/location.
     */
    public function driverUpdateLocation(Request $request): JsonResponse
    {
        if (! FirestoreDriverUser::isAuthenticatedUser($request->user())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $driverId = (string) $request->user()->id;

        $data = $request->validate([
            'order_id' => 'nullable|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $driver = $this->firestore->get('drivers', $driverId);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        if (! empty($data['order_id'])) {
            $order = $this->firestore->get('orders', (string) $data['order_id']);
            if (! $order) {
                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }
        }

        $now = now()->toIso8601String();
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        $this->firestore->update('drivers', $driverId, [
            'current_lat' => $lat,
            'current_lng' => $lng,
            'last_updated' => $now,
        ]);
        FirestoreCacheKeys::invalidateDrivers();

        $saveHistory = false;
        $last = $this->latestRiderLocationForDriver($driverId);
        if (! $last) {
            $saveHistory = true;
        } else {
            $distance = $this->deliveryService->calculateDistance(
                (float) ($last['lat'] ?? 0),
                (float) ($last['lng'] ?? 0),
                $lat,
                $lng
            );
            $saveHistory = $this->deliveryService->isSignificantMovement($distance);
        }

        if ($saveHistory) {
            $this->firestore->add('rider_locations', [
                'driver_id' => $driverId,
                'order_id' => ! empty($data['order_id']) ? (string) $data['order_id'] : null,
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }

        broadcast(new RiderLocationUpdated($driverId, $lat, $lng, $now));

        return response()->json(['success' => true], 200);
    }

    /**
     * Legacy driver GPS (body includes driver_id). Prefer POST /v1/driver/geo/location with driver session.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'driver_id' => 'required|string',
            'order_id' => 'nullable|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $driverId = trim((string) $data['driver_id']);
        $driver = $this->firestore->get('drivers', $driverId);
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver not found.'], 404);
        }

        if (! empty($data['order_id'])) {
            $order = $this->firestore->get('orders', (string) $data['order_id']);
            if (! $order) {
                return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
            }
        }

        $now = now()->toIso8601String();
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        $this->firestore->update('drivers', $driverId, [
            'current_lat' => $lat,
            'current_lng' => $lng,
            'last_updated' => $now,
        ]);
        FirestoreCacheKeys::invalidateDrivers();

        $saveHistory = false;
        $last = $this->latestRiderLocationForDriver($driverId);
        if (! $last) {
            $saveHistory = true;
        } else {
            $distance = $this->deliveryService->calculateDistance(
                (float) ($last['lat'] ?? 0),
                (float) ($last['lng'] ?? 0),
                $lat,
                $lng
            );
            $saveHistory = $this->deliveryService->isSignificantMovement($distance);
        }

        if ($saveHistory) {
            $this->firestore->add('rider_locations', [
                'driver_id' => $driverId,
                'order_id' => ! empty($data['order_id']) ? (string) $data['order_id'] : null,
                'lat' => $lat,
                'lng' => $lng,
            ]);
        }

        broadcast(new RiderLocationUpdated($driverId, $lat, $lng, $now));

        return response()->json(['success' => true], 200);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTrackPayload(array $order): array
    {
        $orderId = (string) ($order['id'] ?? '');
        $address = trim((string) ($order['delivery_address'] ?? ''));
        $destination = $this->destinationForOrder($order);

        $status = (string) ($order['status'] ?? '');
        $driverPayload = null;
        $routePayload = null;

        $cLat = $order['customer_current_lat'] ?? null;
        $cLng = $order['customer_current_lng'] ?? null;
        $hasCustomerLive = is_numeric($cLat) && is_numeric($cLng)
            && ((float) $cLat != 0.0 || (float) $cLng != 0.0);
        $customerLocation = $hasCustomerLive ? [
            'lat' => (float) $cLat,
            'lng' => (float) $cLng,
            'updated_at' => $order['customer_location_updated_at'] ?? null,
        ] : null;

        $driverId = $order['driver_id'] ?? null;
        if ($driverId !== null && $driverId !== '') {
            $driver = $this->firestore->get('drivers', (string) $driverId);
            if (is_array($driver)) {
                $dLat = (float) ($driver['current_lat'] ?? 0);
                $dLng = (float) ($driver['current_lng'] ?? 0);
                $hasCoords = $dLat != 0.0 || $dLng != 0.0;
                $driverPayload = [
                    'id' => (string) ($driver['id'] ?? ''),
                    'name' => (string) ($driver['name'] ?? ''),
                    'phone' => (string) ($driver['phone'] ?? ''),
                    'image_url' => $this->assetUrl((string) ($driver['image'] ?? 'img/default-user.png')),
                    'location' => $hasCoords ? ['lat' => $dLat, 'lng' => $dLng] : null,
                    'last_updated' => $driver['last_updated'] ?? null,
                ];

                $destLat = $destination['lat'] ?? null;
                $destLng = $destination['lng'] ?? null;
                if (
                    $hasCoords
                    && $destLat !== null && $destLng !== null
                    && is_numeric($destLat) && is_numeric($destLng)
                ) {
                    $routePayload = $this->deliveryService->directionsOverview(
                        $dLat,
                        $dLng,
                        (float) $destLat,
                        (float) $destLng
                    );
                }
            }
        }

        return [
            'order_id' => $orderId,
            'status' => $status,
            'transaction_id' => (string) ($order['transaction_id'] ?? ''),
            'delivery_address' => $address,
            'destination' => $destination,
            'destination_source' => $this->destinationSource($order),
            'customer_location' => $customerLocation,
            'driver' => $driverPayload,
            'route' => $routePayload,
        ];
    }

    /**
     * Drop-off coordinates: prefer Firestore delivery_lat/lng, else geocode delivery_address.
     *
     * @return array{lat: float|null, lng: float|null}
     */
    private function destinationForOrder(array $order): array
    {
        $dlat = $order['delivery_lat'] ?? null;
        $dlng = $order['delivery_lng'] ?? null;
        if ($dlat !== null && $dlng !== null && is_numeric($dlat) && is_numeric($dlng)) {
            $la = (float) $dlat;
            $ln = (float) $dlng;
            if ($la >= -90.0 && $la <= 90.0 && $ln >= -180.0 && $ln <= 180.0) {
                return ['lat' => $la, 'lng' => $ln];
            }
        }

        $address = trim((string) ($order['delivery_address'] ?? ''));
        if ($address === '') {
            return ['lat' => null, 'lng' => null];
        }

        return $this->deliveryService->geocodeAddress($address);
    }

    private function destinationSource(array $order): string
    {
        $dlat = $order['delivery_lat'] ?? null;
        $dlng = $order['delivery_lng'] ?? null;
        if ($dlat !== null && $dlng !== null && is_numeric($dlat) && is_numeric($dlng)) {
            $la = (float) $dlat;
            $ln = (float) $dlng;
            if ($la >= -90.0 && $la <= 90.0 && $ln >= -180.0 && $ln <= 180.0) {
                return 'stored';
            }
        }

        $address = trim((string) ($order['delivery_address'] ?? ''));

        return $address !== '' ? 'geocoded' : 'none';
    }

    private function assetUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return asset($path);
    }

    /**
     * Same rules as ApiOrderController::customerIdMatchesOrderRow.
     */
    private function customerOwnsOrder(mixed $storedCustomerId, string $authCustomerId): bool
    {
        $a = trim((string) ($storedCustomerId ?? ''));
        $b = trim($authCustomerId);
        if ($b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        if ($a !== '' && is_numeric($a) && is_numeric($b) && (int) $a === (int) $b) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestRiderLocationForDriver(string $driverId): ?array
    {
        $rows = $this->firestore->where('rider_locations', 'driver_id', $driverId);
        if ($rows === []) {
            return null;
        }
        usort($rows, static function (array $a, array $b): int {
            $ta = (string) ($a['created_at'] ?? '');
            $tb = (string) ($b['created_at'] ?? '');

            return $tb <=> $ta;
        });

        return $rows[0];
    }
}
