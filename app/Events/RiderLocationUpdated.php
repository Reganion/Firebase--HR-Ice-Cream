<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts driver GPS updates (Firestore-backed drivers, not Eloquent).
 */
class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $driverId,
        public float $lat,
        public float $lng,
        public string $lastUpdatedIso,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('rider-location');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'last_updated' => $this->lastUpdatedIso,
        ];
    }
}
