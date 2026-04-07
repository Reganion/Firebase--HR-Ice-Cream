<?php

namespace App\Support;

/**
 * Driver API bearer token sessions: token → driver id in cache.
 * Tokens persist until explicit logout (or cache flush); each authenticated request refreshes the entry.
 */
final class ApiDriverSession
{
    public const CACHE_PREFIX = 'api_driver_session:';

    public const ONLINE_KEY_PREFIX = 'api_driver_online:';

    /**
     * Sliding TTL for the "online" presence flag when the app is backgrounded without logout.
     */
    public const ONLINE_PRESENCE_TTL_MINUTES = 60 * 24 * 365;
}
