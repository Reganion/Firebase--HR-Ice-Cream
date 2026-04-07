<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DeliveryService
{
    private function mapsApiKey(): string
    {
        return (string) config('services.google_maps.api_key', '');
    }

    private function mapsProvider(): string
    {
        return (string) config('services.maps.provider', 'google');
    }

    private function nominatimUrl(): string
    {
        return (string) config('services.osm.nominatim_url', 'https://nominatim.openstreetmap.org');
    }

    private function osrmUrl(): string
    {
        return (string) config('services.osm.osrm_url', 'https://router.project-osrm.org');
    }

    private function nominatimEmail(): string
    {
        return (string) config('services.osm.nominatim_email', '');
    }

    private function nominatimUserAgent(): string
    {
        return (string) config('services.osm.user_agent', 'HR-IceCream-API/1.0');
    }

    /**
     * Geocode address using Google Geocoding API.
     *
     * @return array{lat: float|null, lng: float|null}
     */
    public function geocodeAddress($address): array
    {
        $address = trim((string) $address);
        if ($address === '') {
            return ['lat' => null, 'lng' => null];
        }

        $apiKey = $this->mapsApiKey();
        if ($this->mapsProvider() === 'google' && $apiKey !== '') {
            $response = Http::get(
                'https://maps.googleapis.com/maps/api/geocode/json',
                [
                    'address' => $address,
                    'key' => $apiKey,
                ]
            );

            $data = $response->json();

            if (! empty($data['results'][0]['geometry']['location'])) {
                return [
                    'lat' => $data['results'][0]['geometry']['location']['lat'],
                    'lng' => $data['results'][0]['geometry']['location']['lng'],
                ];
            }

            return ['lat' => null, 'lng' => null];
        }

        // Free fallback: Nominatim (OpenStreetMap) - address -> lat/lng
        $query = [
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 0,
        ];
        if ($this->nominatimEmail() !== '') {
            // Nominatim usage policy: include contact email when possible.
            $query['email'] = $this->nominatimEmail();
        }

        $response = Http::withHeaders([
            // Nominatim usage policy: provide a descriptive user-agent.
            'User-Agent' => $this->nominatimUserAgent(),
        ])->get($this->nominatimUrl().'/search', $query);

        $data = $response->json();
        if (is_array($data) && ! empty($data[0]['lat']) && ! empty($data[0]['lon'])) {
            return [
                'lat' => (float) $data[0]['lat'],
                'lng' => (float) $data[0]['lon'],
            ];
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * Driving route summary (polyline) for Google Maps using Directions API.
     *
     * @return array{overview_polyline: string|null, distance_meters: int, duration_seconds: int, duration_in_traffic_seconds: int|null}|null
     */
    public function directionsOverview(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        $apiKey = $this->mapsApiKey();
        if ($this->mapsProvider() === 'google' && $apiKey !== '') {
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', [
                'origin' => $originLat.','.$originLng,
                'destination' => $destLat.','.$destLng,
                'key' => $apiKey,
            ]);

            $data = $response->json();
            if (empty($data['routes'][0])) {
                return null;
            }

            $route = $data['routes'][0];
            $leg = $route['legs'][0] ?? null;
            if (! is_array($leg)) {
                return null;
            }

            $poly = $route['overview_polyline']['points'] ?? null;

            return [
                'overview_polyline' => is_string($poly) ? $poly : null,
                'distance_meters' => (int) ($leg['distance']['value'] ?? 0),
                'duration_seconds' => (int) ($leg['duration']['value'] ?? 0),
                'duration_in_traffic_seconds' => isset($leg['duration_in_traffic']['value'])
                    ? (int) $leg['duration_in_traffic']['value']
                    : null,
            ];
        }

        // Free fallback: OSRM (Open Source Routing Machine)
        // - Returns geometry as an encoded polyline (same polyline format many libs support).
        $coords = $originLng.','.$originLat.';'.$destLng.','.$destLat; // OSRM expects lng,lat

        $response = Http::get($this->osrmUrl().'/route/v1/driving/'.$coords, [
            'overview' => 'full',
            'geometries' => 'polyline',
            'alternatives' => 'false',
            'steps' => 'false',
        ]);

        $data = $response->json();
        if (empty($data['routes'][0])) {
            return null;
        }

        $route = $data['routes'][0];
        $poly = $route['geometry'] ?? null;

        return [
            'overview_polyline' => is_string($poly) ? $poly : null,
            'distance_meters' => (int) ($route['distance'] ?? 0),
            'duration_seconds' => (int) ($route['duration'] ?? 0),
            // OSRM does not provide "duration in traffic" like Google.
            'duration_in_traffic_seconds' => null,
        ];
    }

    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;

    }

    public function isSignificantMovement(float $distanceKm, float $thresholdKm = 0.02): bool
    {
        return $distanceKm >= $thresholdKm;
    }

}